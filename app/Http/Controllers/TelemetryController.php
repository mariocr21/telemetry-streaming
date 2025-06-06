<?php

namespace App\Http\Controllers;

use App\Events\VehicleTelemetryEvent;
use App\Models\ClientDevice;
use App\Models\Register;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TelemetryController extends Controller
{
    /**
     * Obtener vehículo por ID de dispositivo
     */
    public function getVehicleByDevice($deviceId)
    {
        try {
            $device = ClientDevice::with([
                'vehicles.vehicle_sensors.sensor',
                'device_inventory'
            ])->findOrFail($deviceId);

            $vehicle = $device->vehicles->first();

            if (!$vehicle) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay vehículo asociado a este dispositivo'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'vehicle' => $vehicle,
                'device' => $device
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos del vehículo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener historial de telemetría
     */
    public function getHistory(Request $request, $vehicleId)
    {
        $request->validate([
            'hours' => 'integer|min:1|max:168', // Máximo 7 días
            'sensor_pids' => 'array',
            'sensor_pids.*' => 'string'
        ]);

        $hours = $request->get('hours', 24); // Por defecto últimas 24 horas
        $sensorPids = $request->get('sensor_pids', []);

        try {
            $query = Register::with(['sensor.sensor'])
                ->where('vehicle_id', $vehicleId)
                ->where('recorded_at', '>=', Carbon::now()->subHours($hours))
                ->orderBy('recorded_at', 'desc');

            // Filtrar por PIDs específicos si se proporcionan
            if (!empty($sensorPids)) {
                $query->whereHas('sensor.sensor', function ($q) use ($sensorPids) {
                    $q->whereIn('pid', $sensorPids);
                });
            }

            $records = $query->limit(1000)->get(); // Limitar resultados

            // Agrupar por sensor para gráficos
            $groupedData = $records->groupBy(function ($record) {
                return $record->sensor->sensor->pid;
            })->map(function ($sensorRecords, $pid) {
                return [
                    'pid' => $pid,
                    'name' => $sensorRecords->first()->sensor->sensor->name,
                    'unit' => $sensorRecords->first()->sensor->sensor->unit,
                    'data' => $sensorRecords->map(function ($record) {
                        return [
                            'timestamp' => $record->recorded_at->toISOString(),
                            'value' => $record->value,
                            'formatted_time' => $record->recorded_at->format('H:i:s')
                        ];
                    })->values()
                ];
            })->values();

            return response()->json([
                'success' => true,
                'vehicle_id' => $vehicleId,
                'period_hours' => $hours,
                'total_records' => $records->count(),
                'sensors' => $groupedData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener historial: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de telemetría
     */
    public function getStats($vehicleId)
    {
        try {
            $vehicle = Vehicle::with('vehicle_sensors.sensor')->findOrFail($vehicleId);

            // Estadísticas de las últimas 24 horas
            $stats = [];
            $since = Carbon::now()->subDay();

            foreach ($vehicle->vehicle_sensors as $vehicleSensor) {
                if (!$vehicleSensor->is_active) continue;

                $records = Register::where('vehicle_sensor_id', $vehicleSensor->id)
                    ->where('recorded_at', '>=', $since)
                    ->select('value')
                    ->get();

                if ($records->isEmpty()) continue;

                $values = $records->pluck('value');

                $stats[] = [
                    'pid' => $vehicleSensor->sensor->pid,
                    'name' => $vehicleSensor->sensor->name,
                    'unit' => $vehicleSensor->sensor->unit,
                    'count' => $records->count(),
                    'min' => $values->min(),
                    'max' => $values->max(),
                    'avg' => round($values->avg(), 2),
                    'latest' => $values->first(),
                    'latest_at' => $records->first()?->recorded_at?->toISOString()
                ];
            }

            // Estadísticas generales
            $totalReadings = Register::where('vehicle_id', $vehicleId)
                ->where('recorded_at', '>=', $since)
                ->count();

            $activeSensors = $vehicle->vehicle_sensors->where('is_active', true)->count();

            return response()->json([
                'success' => true,
                'vehicle_id' => $vehicleId,
                'period' => '24h',
                'summary' => [
                    'total_readings' => $totalReadings,
                    'active_sensors' => $activeSensors,
                    'avg_readings_per_hour' => round($totalReadings / 24, 1)
                ],
                'sensors' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Simular datos de telemetría (solo para testing)
     */
    public function simulateData(Request $request, $vehicleId)
    {
        if (!app()->environment('local')) {
            return response()->json(['error' => 'Solo disponible en desarrollo'], 403);
        }

        try {
            $vehicle = Vehicle::with('vehicle_sensors.sensor')->findOrFail($vehicleId);
            $telemetryData = [];

            foreach ($vehicle->vehicle_sensors as $vehicleSensor) {
                if (!$vehicleSensor->is_active) continue;

                $sensor = $vehicleSensor->sensor;

                // Generar valor aleatorio según el tipo de sensor
                $value = $this->generateRealisticValue($sensor->pid, $sensor->min_value, $sensor->max_value);

                $telemetryData[$sensor->pid] = [
                    'pid' => $sensor->pid,
                    'raw_value' => $value,
                    'processed_value' => $value,
                    'unit' => $sensor->unit,
                    'name' => $sensor->name,
                    'timestamp' => now()->toISOString()
                ];

                // Guardar en BD
                Register::create([
                    'vehicle_id' => $vehicleId,
                    'vehicle_sensor_id' => $vehicleSensor->id,
                    'value' => $value,
                    'recorded_at' => now()
                ]);
            }

            // Broadcast evento
            broadcast(new VehicleTelemetryEvent($vehicleId, $vehicle->client_device_id, $telemetryData));

            // Cache
            $processedReadings = collect($telemetryData)->mapWithKeys(function ($data, $pid) {
                return [$pid => $data['processed_value']];
            })->toArray();

            Cache::put("vehicle_telemetry_{$vehicleId}", $processedReadings, 300);

            return response()->json([
                'success' => true,
                'message' => 'Datos simulados enviados',
                'sensors_count' => count($telemetryData),
                'data' => $telemetryData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error simulando datos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test WebSocket connection
     */
    public function testWebSocket($vehicleId)
    {
        if (!app()->environment('local')) {
            return response()->json(['error' => 'Solo disponible en desarrollo'], 403);
        }

        try {
            $testData = [
                '0x0C' => [
                    'pid' => '0x0C',
                    'raw_value' => 1500,
                    'processed_value' => 1500,
                    'unit' => 'RPM',
                    'name' => 'Engine RPM',
                    'timestamp' => now()->toISOString()
                ],
                '0x0D' => [
                    'pid' => '0x0D',
                    'raw_value' => 60,
                    'processed_value' => 60,
                    'unit' => 'km/h',
                    'name' => 'Vehicle Speed',
                    'timestamp' => now()->toISOString()
                ]
            ];

            broadcast(new VehicleTelemetryEvent($vehicleId, 1, $testData));

            return response()->json([
                'success' => true,
                'message' => 'Test WebSocket enviado',
                'data' => $testData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en test WebSocket: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar valores realistas para sensores
     */
    private function generateRealisticValue($pid, $min = null, $max = null)
    {
        switch ($pid) {
            case '0x0C': // RPM
                return rand(800, 3000);
            case '0x0D': // Velocidad
                return rand(0, 120);
            case '0x05': // Temperatura motor
                return rand(80, 100);
            case '0x2F': // Combustible
                return rand(10, 100);
            case '0x0B': // Presión MAP
                return rand(80, 120);
            case '0x42': // Batería
                return round(rand(115, 135) / 10, 1); // 11.5 - 13.5V
            case '0x11': // Throttle
                return rand(0, 100);
            case '0x04': // Carga motor
                return rand(0, 80);
            case '0x0F': // Aire admisión
                return rand(20, 40);
            case '0x10': // MAF
                return round(rand(0, 200) / 10, 1); // 0-20.0 g/s
            default:
                return rand($min ?? 0, $max ?? 255);
        }
    }
}
