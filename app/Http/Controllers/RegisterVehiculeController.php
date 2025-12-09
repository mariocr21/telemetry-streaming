<?php

namespace App\Http\Controllers;

use App\Events\VehicleTelemetryEvent;
use App\Models\ClientDevice;
use App\Models\Register;
use App\Models\VehicleSensor;
use App\Models\DiagnosticTroubleCode;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RegisterVehiculeController extends Controller
{
    public function store(Request $request)
    {
        $startTime = microtime(true);
        
        // Mapear formato nuevo si es necesario
        $requestData = $this->normalizeRequestData($request);
        
        try {
            $data = $request->merge($requestData)->validate([
                'id' => 'required|exists:device_inventories,serial_number',
                'idc' => 'required|exists:vehicles,vin',
                'dt' => 'required|string',
                's' => 'required|array',
                'DTC' => 'nullable|array',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('📥 Telemetry rejected - Validation failed', [
                'device' => $request->input('id') ?? $request->input('device_id'),
                'errors' => $e->errors(),
            ]);
            
            return response()->json(['status' => 'error', 'errors' => $e->errors()], 422);
        }

        try {
            // Validar timestamp
            $timestampResult = $this->validateAndCorrectTimestamp($data['dt']);
            $data['dt'] = $timestampResult['timestamp'];

            // Obtener dispositivo y vehículo
            $clientDevice = $this->getClientDevice($data['id']);
            if (!$clientDevice) {
                return response()->json(['status' => 'error', 'message' => 'Device not found'], 404);
            }

            $clientVehicle = $clientDevice->vehicles()->where('vin', $data['idc'])->first();
            if (!$clientVehicle) {
                return response()->json(['status' => 'error', 'message' => 'Vehicle not found'], 404);
            }

            // Procesar sensores
            $telemetryData = $this->processSensors($data, $clientDevice, $clientVehicle);
            
            // Procesar DTCs
            $dtcCodes = $this->processDTCs($data['DTC'] ?? [], $clientVehicle, $data['dt']);

            // Broadcast y cache
            broadcast(new VehicleTelemetryEvent(
                $clientVehicle->id,
                $clientDevice->id,
                $telemetryData,
                $dtcCodes
            ));

            $this->updateCache($clientVehicle->id, $telemetryData, $dtcCodes);
            $this->updateSensorStatus($data['idc'], array_keys($telemetryData));

            // Log único y limpio del proceso completo
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::info('📥 Telemetry processed', [
                'device' => $data['id'],
                'vehicle' => $data['idc'],
                'timestamp' => $data['dt'],
                'timestamp_corrected' => $timestampResult['corrected'],
                'sensors_count' => count($telemetryData),
                'sensors' => $this->summarizeSensors($telemetryData),
                'dtc_count' => count($dtcCodes),
                'dtc_codes' => array_column($dtcCodes, 'code'),
                'execution_ms' => $executionTime,
            ]);

            return response()->json(['status' => 'success']);
            
        } catch (Exception $e) {
            Log::error('❌ Telemetry failed', [
                'device' => $data['id'] ?? 'unknown',
                'vehicle' => $data['idc'] ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => basename($e->getFile()) . ':' . $e->getLine(),
            ]);
            
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Normaliza el formato de la request
     */
    private function normalizeRequestData(Request $request): array
    {
        $data = $request->all();
        
        if (isset($data['device_id']) && isset($data['vehicle_vin'])) {
            return [
                'id' => $data['device_id'],
                'idc' => $data['vehicle_vin'],
                'dt' => $data['timestamp'],
                's' => $data['sensors'] ?? [],
                'DTC' => $data['dtc_codes'] ?? [],
            ];
        }
        
        return $data;
    }

    /**
     * Obtiene el dispositivo del cliente
     */
    private function getClientDevice(string $serialNumber): ?ClientDevice
    {
        return ClientDevice::with('vehicles')
            ->whereHas('DeviceInventory', fn($q) => $q->where('serial_number', $serialNumber))
            ->first();
    }

    /**
     * Procesa los datos de sensores
     */
    private function processSensors(array $data, ClientDevice $device, $vehicle): array
    {
        $telemetryData = [];

        foreach ($data['s'] as $sensorHex => $sensorData) {
            if (!isset($sensorData['v']) || $sensorData['v'] === null) {
                continue;
            }

            $vehicleSensor = VehicleSensor::whereHas('vehicle', fn($q) => $q->where('vin', $data['idc']))
                ->whereHas('sensor', fn($q) => $q->where('pid', $sensorHex))
                ->with('sensor')
                ->first();

            if (!$vehicleSensor) {
                continue;
            }

            Register::create([
                'client_device_id' => $device->id,
                'vehicle_id' => $vehicle->id,
                'vehicle_sensor_id' => $vehicleSensor->id,
                'value' => $sensorData['v'],
                'recorded_at' => $data['dt'],
            ]);

            $processedValue = $this->processSensorValue($sensorData['v'], $vehicleSensor->sensor);

            $telemetryData[$sensorHex] = [
                'pid' => $sensorHex,
                'raw_value' => $sensorData['v'],
                'processed_value' => $processedValue,
                'unit' => $vehicleSensor->sensor->unit,
                'name' => $vehicleSensor->sensor->name,
                'timestamp' => $data['dt'],
            ];
        }

        return $telemetryData;
    }

    /**
     * Procesa los códigos DTC
     */
    private function processDTCs(array $dtcList, $vehicle, string $timestamp): array
    {
        if (empty($dtcList)) {
            return [];
        }

        $dtcCodes = [];

        foreach ($dtcList as $code) {
            $dtc = DiagnosticTroubleCode::firstOrCreate(
                ['vehicle_id' => $vehicle->id, 'code' => $code],
                ['detected_at' => $timestamp, 'is_active' => true]
            );

            if (!$dtc->wasRecentlyCreated && !$dtc->is_active) {
                $dtc->update(['is_active' => true, 'redetected_at' => $timestamp]);
            }

            $dtcCodes[] = [
                'code' => $code,
                'description' => $this->getDTCDescription($code),
                'severity' => $this->getDTCSeverity($code),
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
     * Actualiza el estado de los sensores
     */
    private function updateSensorStatus(string $vin, array $sensorPids): void
    {
        if (empty($sensorPids)) {
            return;
        }

        $vehicleSensorIds = VehicleSensor::whereHas('vehicle', fn($q) => $q->where('vin', $vin))
            ->whereHas('sensor', fn($q) => $q->whereIn('pid', $sensorPids))
            ->pluck('id');

        VehicleSensor::whereIn('id', $vehicleSensorIds)
            ->update(['is_active' => true, 'last_reading_at' => now()]);
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
    private function getDTCDescription(string $code): string
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
    private function getDTCSeverity(string $code): string
    {
        return match (substr($code, 0, 1)) {
            'P' => 'high',
            'B', 'C' => 'medium',
            'U' => 'low',
            default => 'unknown',
        };
    }

    /**
     * Obtiene la última telemetría desde cache
     */
    public function getLatestTelemetry(int $vehicleId)
    {
        Log::info('📊 Telemetry requested', ['vehicle_id' => $vehicleId]);

        return response()->json([
            'vehicle_id' => $vehicleId,
            'timestamp' => now()->toISOString(),
            'data' => Cache::get("vehicle_telemetry_{$vehicleId}", []),
            'dtc_codes' => Cache::get("vehicle_dtc_{$vehicleId}", []),
        ]);
    }

    /**
     * Obtiene los códigos DTC activos
     */
    public function getActiveDTC(int $vehicleId)
    {
        $dtcCodes = Cache::get("vehicle_dtc_{$vehicleId}");

        if (empty($dtcCodes)) {
            $dtcCodes = DiagnosticTroubleCode::where('vehicle_id', $vehicleId)
                ->where('is_active', true)
                ->get()
                ->map(fn($dtc) => [
                    'code' => $dtc->code,
                    'description' => $this->getDTCDescription($dtc->code),
                    'severity' => $this->getDTCSeverity($dtc->code),
                    'detected_at' => $dtc->detected_at,
                ])
                ->toArray();
        }

        Log::info('🔧 DTC requested', [
            'vehicle_id' => $vehicleId,
            'active_codes' => count($dtcCodes),
        ]);

        return response()->json([
            'vehicle_id' => $vehicleId,
            'timestamp' => now()->toISOString(),
            'dtc_codes' => $dtcCodes,
        ]);
    }
}