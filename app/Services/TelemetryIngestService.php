<?php

namespace App\Services;

use App\Events\VehicleTelemetryEvent;
use App\Models\ClientDevice;
use App\Models\Register;
use App\Models\VehicleSensor;
use App\Models\DiagnosticTroubleCode;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TelemetryIngestService
{
    /**
     * Punto de entrada único para procesar una trama de telemetría,
     * venga de HTTP o de MQTT.
     *
     * Estructura esperada:
     * [
     *   'id'  => 'CANAM_FREZAMALA',
     *   'idc' => 'CAM20200000002',
     *   'dt'  => '2025-11-26 16:00:56',
     *   's'   => [ '0x0C' => ['v' => 1800], ... ],
     *   'DTC' => ['P0420', ...] | []
     * ]
     */

    public function ingest(array $data): void
    {
        // Forzamos que NO sea debug cuando llamen por HTTP
        $data['d'] = false;

        $this->ingestFromJob($data);
    }


    public function ingestFromJob(array $data): void
    {
        $startTime = microtime(true);

        // Flag debug: true = NO persistir en BD
        $debug   = filter_var($data['d'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $persist = !$debug;

        // Validación básica
        $validator = Validator::make($data, [
            'id'  => 'required|exists:device_inventories,serial_number',
            'idc' => 'required|exists:vehicles,vin',
            'dt'  => 'required|string',
            's'   => 'required|array',
            'DTC' => 'nullable|array',
            // 'd' opcional
        ]);

        if ($validator->fails()) {
            Log::warning('📥 Telemetry rejected - Validation failed (Job)', [
                'device' => $data['id'] ?? null,
                'errors' => $validator->errors()->toArray(),
            ]);
            return;
        }

        $data = $validator->validated();

        try {
            // 1) Timestamp
            $timestampResult = $this->validateAndCorrectTimestamp($data['dt']);
            $data['dt'] = $timestampResult['timestamp'];

            // 2) Dispositivo y vehículo
            $clientDevice = $this->getClientDevice($data['id']);

            if (!$clientDevice) {
                Log::warning('🚫 Device not found (Job)', [
                    'device' => $data['id'],
                ]);
                return;
            }

            $clientVehicle = $clientDevice->vehicles()
                ->where('vin', $data['idc'])
                ->first();

            if (!$clientVehicle) {
                Log::warning('🚫 Vehicle not found (Job)', [
                    'device'  => $data['id'],
                    'vehicle' => $data['idc'],
                ]);
                return;
            }

            // 3) Sensores + DTC (con flag $persist)
            $telemetryData = $this->processSensors(
                $data,
                $clientDevice,
                $clientVehicle,
                $persist
            );

            $dtcCodes = $this->processDTCs(
                $data['DTC'] ?? [],
                $clientVehicle,
                $data['dt'],
                $persist
            );

            // 4) Broadcast realtime SIEMPRE (aunque sea debug)
            broadcast(new VehicleTelemetryEvent(
                $clientVehicle->id,
                $clientDevice->id,
                $telemetryData,
                $dtcCodes,
                $data['dt']
            ));

            // 5) Cache + estado sensores (puedes decidir si hacerlo en debug o no)
            $this->updateCache($clientVehicle->id, $telemetryData, $dtcCodes);
            $this->updateSensorStatus($data['idc'], array_keys($telemetryData), $persist);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            // Log::info('📥 Telemetry processed (Job)', [
            //     'device'              => $data['id'],
            //     'vehicle'             => $data['idc'],
            //     'timestamp'           => $data['dt'],
            //     'timestamp_corrected' => $timestampResult['corrected'] ?? false,
            //     'correction_reason'   => $timestampResult['reason'] ?? null,
            //     'sensors_count'       => count($telemetryData),
            //     'dtc_codes'           => array_column($dtcCodes, 'code'),
            //     'execution_ms'        => $executionTime,
            //     'debug'               => $debug,
            //     'persist'             => $persist,
            // ]);
        } catch (\Throwable $e) {
            Log::error('❌ Telemetry failed (Job)', [
                'device'  => $data['id'] ?? 'unknown',
                'vehicle' => $data['idc'] ?? 'unknown',
                'error'   => $e->getMessage(),
                'file'    => basename($e->getFile()) . ':' . $e->getLine(),
            ]);
        }
    }
    /**
     * Obtiene el ClientDevice con sus vehículos, desde cache si es posible.
     */
    function getClientDevice(string $serialNumber): ?ClientDevice
    {
        $cacheKey = 'client_device_with_vehicles_' . $serialNumber;

        return Cache::remember($cacheKey, 300, function () use ($serialNumber) {
            return ClientDevice::with('vehicles')
                ->whereHas('DeviceInventory', fn($q) => $q->where('serial_number', $serialNumber))
                ->first();
        });
    }

    /**
     * Procesa los datos de sensores.
     */
    function processSensors(array $data, ClientDevice $device, $vehicle, bool $persist = true): array
    {
        $telemetryData = [];

        // ⚡ Prefetch de todos los VehicleSensor de este vehículo, indexados por PID
        $vehicleSensors = VehicleSensor::whereHas('vehicle', fn($q) => $q->where('vin', $data['idc']))
            ->with('sensor')
            ->get()
            ->keyBy(fn(VehicleSensor $vs) => $vs->sensor->pid);

        foreach ($data['s'] as $sensorHex => $sensorData) {
            if (!isset($sensorData['v']) || $sensorData['v'] === null) {
                continue;
            }

            /** @var VehicleSensor|null $vehicleSensor */
            $vehicleSensor = $vehicleSensors[$sensorHex] ?? null;

            if (!$vehicleSensor) {
                // Este PID no está mapeado para este vehículo
                continue;
            }

            // 🔹 Solo persistimos si NO es debug
            if ($persist) {
                Register::create([
                    'client_device_id'  => $device->id,
                    'vehicle_id'        => $vehicle->id,
                    'vehicle_sensor_id' => $vehicleSensor->id,
                    'value'             => $sensorData['v'],
                    'recorded_at'       => $data['dt'],
                ]);
                Log::debug('💾 Sensor data stored', [
                    'device'       => $data['id'],
                    'vehicle'      => $data['idc'],
                    'sensor_pid'   => $sensorHex,
                    'raw_value'    => $sensorData['v'],
                    'sensor_name'  => $vehicleSensor->sensor->name,
                ]);
            }

            $processedValue = $this->processSensorValue($sensorData['v'], $vehicleSensor->sensor);

            $telemetryData[$sensorHex] = [
                'pid'             => $sensorHex,
                'raw_value'       => $sensorData['v'],
                'processed_value' => $processedValue,
                'unit'            => $vehicleSensor->sensor->unit,
                'name'            => $vehicleSensor->sensor->name,
                'timestamp'       => $data['dt'],
            ];
        }

        return $telemetryData;
    }
    /**
     * Procesa los códigos DTC.
     */
    function processDTCs(array $dtcList, $vehicle, string $timestamp, bool $persist = true): array
    {
        if (empty($dtcList)) {
            return [];
        }

        $dtcCodes = [];

        foreach ($dtcList as $code) {
            // 🔹 Solo tocamos BD si NO es debug
            if ($persist) {
                $dtc = DiagnosticTroubleCode::firstOrCreate(
                    ['vehicle_id' => $vehicle->id, 'code' => $code],
                    ['detected_at' => $timestamp, 'is_active' => true]
                );

                if (!$dtc->wasRecentlyCreated && !$dtc->is_active) {
                    $dtc->update([
                        'is_active'     => true,
                        'redetected_at' => $timestamp,
                    ]);
                }
            }

            $dtcCodes[] = [
                'code'        => $code,
                'description' => $this->getDTCDescription($code),
                'severity'    => $this->getDTCSeverity($code),
            ];
        }

        return $dtcCodes;
    }


    /**
     * Actualiza el cache de telemetría
     */
    private function updateCache(int $vehicleId, array $telemetryData, array $dtcCodes): void
    {
        $processedReadings = array_map(fn($s) => $s['processed_value'], $telemetryData);

        Cache::put("vehicle_telemetry_{$vehicleId}", $processedReadings, 300);

        if (!empty($dtcCodes)) {
            Cache::put("vehicle_dtc_{$vehicleId}", $dtcCodes, 300);
        }
    }
    /**
     * Actualiza el estado de los sensores de un vehículo.
     */
    function updateSensorStatus(string $vin, array $sensorPids, bool $persist = true): void
    {
        // En debug NO tocamos BD
        if (!$persist || empty($sensorPids)) {
            return;
        }

        $vehicleSensorIds = VehicleSensor::whereHas('vehicle', fn($q) => $q->where('vin', $vin))
            ->whereHas('sensor', fn($q) => $q->whereIn('pid', $sensorPids))
            ->pluck('id');

        if ($vehicleSensorIds->isEmpty()) {
            return;
        }

        VehicleSensor::whereIn('id', $vehicleSensorIds)
            ->update([
                'is_active'      => true,
                'last_reading_at' => now(),
            ]);
    }


    /**
     * Resume los sensores para el log
     */
    private function summarizeSensors(array $telemetryData): array
    {
        return array_map(fn($s) => [
            'name' => $s['name'],
            'value' => $s['processed_value'] . ' ' . $s['unit'],
        ], $telemetryData);
    }

    /**
     * Procesa el valor del sensor con su fórmula
     */
    private function processSensorValue($rawValue, $sensor)
    {
        if (!$sensor->requires_calculation || !$sensor->calculation_formula) {
            return $rawValue;
        }

        try {
            $formula = str_replace(['A', 'B'], [$rawValue, 0], $sensor->calculation_formula);
            return round(eval("return $formula;"), 2);
        } catch (Exception $e) {
            return $rawValue;
        }
    }

    /**
     * Valida y corrige el timestamp
     */
    private function validateAndCorrectTimestamp(string $timestamp): array
    {
        $currentTime = Carbon::now();

        try {
            $dataTime = Carbon::parse($timestamp);
        } catch (Exception $e) {
            return [
                'timestamp' => $currentTime->format('Y-m-d H:i:s'),
                'corrected' => true,
                'reason' => 'invalid_format',
            ];
        }

        $diffSeconds = abs($currentTime->diffInSeconds($dataTime));

        if ($diffSeconds > 3600) {
            return [
                'timestamp' => $currentTime->format('Y-m-d H:i:s'),
                'corrected' => true,
                'reason' => 'out_of_range',
                'diff_hours' => round($diffSeconds / 3600, 2),
            ];
        }

        return [
            'timestamp' => $dataTime->format('Y-m-d H:i:s'),
            'corrected' => false,
            'reason' => 'valid',
        ];
    }

    /**
     * Obtiene descripción del código DTC
     */
    public function getDTCDescription(string $code): string
    {
        $prefixes = [
            'P0' => 'Powertrain Issue',
            'P1' => 'Manufacturer-Specific Powertrain',
            'P2' => 'Fuel/Air Monitoring Issue',
            'P3' => 'Ignition Issue',
            'B0' => 'Body Issue',
            'C0' => 'Chassis Issue',
            'U0' => 'Network Issue',
        ];

        return $prefixes[substr($code, 0, 2)] ?? 'Unknown Issue';
    }

    /**
     * Determina la severidad del código DTC
     */
    public function getDTCSeverity(string $code): string
    {
        return match (substr($code, 0, 1)) {
            'P' => 'high',
            'B', 'C' => 'medium',
            'U' => 'low',
            default => 'unknown',
        };
    }
}
