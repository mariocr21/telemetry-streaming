<?php

namespace App\Http\Controllers;

use App\Models\ClientDevice;
use App\Models\Register;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Obtenemos los dispositivos, mediante el cliente o si es superadmin, todos los dispositivos
        if ($request->user()->isSuperAdmin()) {
            $devices = ClientDevice::with('DeviceInventory', 'vehicles')->whereHas('vehicles', function ($query) {
                $query->where('status', true);
            })->get();
        } else {
            $devices = ClientDevice::with('DeviceInventory', 'vehicles')
                ->where('client_id', $request->user()->client_id)
                ->whereHas('vehicles', function ($query) {
                    $query->where('status', true);
                })
                ->get();
        }

        Log::info('Dashboard devices retrieved', [
            'device_count' => $devices->count(),
        ]);
        return Inertia::render('Dashboard', [
            'devices' => $devices,
        ]);
    }

    public function getDeviceVehicleActive(Request $request, ClientDevice $clientDevice)
    {
        $vehicle = $clientDevice->vehicles()->where('status', true)->first();
        
        if (!$vehicle) {
            return response()->json([
                'message' => 'No active vehicle found for this device.',
            ], 404);
        }

        // Cargar el vehículo con sus sensores
        $vehicle->load('vehicleSensors.sensor');

        // Obtener las últimas lecturas de cada sensor
        $latestReadings = $this->getLatestSensorReadings($vehicle->id);
        
        // Determinar el estado de conexión basado en la última lectura
        $connectionStatus = $this->determineConnectionStatus($vehicle->id);

        Log::info('Vehicle data retrieved with latest readings', [
            'vehicle_id' => $vehicle->id,
            'sensors_count' => $vehicle->vehicleSensors->count(),
            'latest_readings_count' => count($latestReadings['data']),
            'connection_status' => $connectionStatus
        ]);

        return response()->json([
            'vehicle' => $vehicle,
            'latest_readings' => $latestReadings,
            'connection_status' => $connectionStatus
        ]);
    }

    /**
     * Obtener las últimas lecturas de cada sensor del vehículo
     */
    private function getLatestSensorReadings($vehicleId)
    {
        Log::info('Fetching latest sensor readings for vehicle', ['vehicle_id' => $vehicleId]);
        
        // Primero intentar desde caché
        $cachedData = Cache::get("vehicle_telemetry_{$vehicleId}");
        
        if ($cachedData && !empty($cachedData)) {
            Log::info('Using cached telemetry data', ['vehicle_id' => $vehicleId]);
            return [
                'data' => $cachedData,
                'source' => 'cache',
                'timestamp' => Cache::get("vehicle_telemetry_timestamp_{$vehicleId}", now()->toISOString())
            ];
        }

        Log::info('No cache found, querying database for latest readings', ['vehicle_id' => $vehicleId]);
        
        // Usar tu estructura existente: registers -> vehicle_sensors -> vehicles
        $twentyFourHoursAgo = now()->subHours(24);
        
        $latestReadings = DB::table('registers as r')
            ->join('vehicle_sensors as vs', 'r.vehicle_sensor_id', '=', 'vs.id')
            ->join('sensors as s', 'vs.sensor_id', '=', 's.id')
            ->select([
                's.pid',
                's.name',
                's.unit',
                's.requires_calculation',
                's.calculation_formula',
                'r.value as raw_value',
                'r.recorded_at',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY s.pid ORDER BY r.recorded_at DESC) as row_num')
            ])
            ->where('vs.vehicle_id', $vehicleId) // Usar vehicle_id desde vehicle_sensors
            ->where('r.recorded_at', '>=', $twentyFourHoursAgo)
            ->get()
            ->where('row_num', 1) // Solo la lectura más reciente de cada sensor
            ->keyBy('pid');

        $processedReadings = [];
        $latestTimestamp = null;

        foreach ($latestReadings as $reading) {
            $processedValue = $this->processSensorValue(
                $reading->raw_value,
                $reading
            );

            $processedReadings[$reading->pid] = $processedValue;
            
            // Mantener track del timestamp más reciente
            $recordedAt = Carbon::parse($reading->recorded_at);
            if (!$latestTimestamp || $recordedAt->gt($latestTimestamp)) {
                $latestTimestamp = $recordedAt;
            }
        }

        Log::info('Latest readings retrieved from database', [
            'vehicle_id' => $vehicleId,
            'readings_count' => count($processedReadings),
            'latest_timestamp' => $latestTimestamp?->toISOString()
        ]);

        return [
            'data' => $processedReadings,
            'source' => 'database',
            'timestamp' => $latestTimestamp?->toISOString() ?? now()->toISOString()
        ];
    }

    /**
     * Determinar el estado de conexión basado en la última actividad
     */
    private function determineConnectionStatus($vehicleId)
    {
        // Usar tu estructura existente: buscar a través de vehicle_sensors
        $lastRegister = Register::whereHas('sensor', function($query) use ($vehicleId) {
                $query->where('vehicle_id', $vehicleId);
            })
            ->orderBy('recorded_at', 'desc')
            ->first();

        if (!$lastRegister) {
            return [
                'is_online' => false,
                'status' => 'never_connected',
                'last_seen' => null,
                'minutes_since_last_reading' => null,
                'seconds_since_last_reading' => null,
                'human_readable_last_seen' => 'Sin datos registrados'
            ];
        }

        $lastSeenAt = Carbon::parse($lastRegister->recorded_at);
        $secondsSinceLastReading = $lastSeenAt->diffInSeconds(now());
        $minutesSinceLastReading = floor($secondsSinceLastReading / 60);
        $isOnline = $secondsSinceLastReading <= 120; // Consideramos online si la última lectura fue hace menos de 2 minutos (120 segundos)

        return [
            'is_online' => $isOnline,
            'status' => $isOnline ? 'online' : 'offline',
            'last_seen' => $lastSeenAt->toISOString(),
            'seconds_since_last_reading' => $secondsSinceLastReading,
            'minutes_since_last_reading' => $minutesSinceLastReading,
            'human_readable_last_seen' => $this->getHumanReadableTime($lastSeenAt),
            'formatted_inactivity' => $this->formatInactivityTime($secondsSinceLastReading)
        ];
    }

    /**
     * Formatear tiempo de inactividad sin decimales
     */
    private function formatInactivityTime($seconds)
    {
        if ($seconds < 60) {
            return $seconds . 's';
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            return $minutes . 'min';
        } else {
            $hours = floor($seconds / 3600);
            $remainingMinutes = floor(($seconds % 3600) / 60);
            return $remainingMinutes > 0 ? "{$hours}h {$remainingMinutes}min" : "{$hours}h";
        }
    }

    /**
     * Procesar valor de sensor aplicando fórmulas si es necesario
     */
    private function processSensorValue($rawValue, $sensorData)
    {
        if (!$sensorData->requires_calculation || !$sensorData->calculation_formula) {
            return (float) $rawValue;
        }

        try {
            $formula = $sensorData->calculation_formula;
            $A = (float) $rawValue;
            $B = 0; // Para datos de 2 bytes, implementar después

            // Reemplazar variables en la fórmula de manera segura
            $calculatedFormula = str_replace(['A', 'B'], [$A, $B], $formula);

            // Evaluar de manera segura usando eval (considera usar una librería más segura en producción)
            $result = eval("return $calculatedFormula;");

            return round((float) $result, 2);
        } catch (\Exception $e) {
            Log::error('Error calculating sensor value', [
                'raw_value' => $rawValue,
                'formula' => $sensorData->calculation_formula,
                'error' => $e->getMessage()
            ]);
            return (float) $rawValue;
        }
    }

    /**
     * Obtener tiempo en formato legible para humanos
     */
    private function getHumanReadableTime(Carbon $timestamp)
    {
        $diffInSeconds = $timestamp->diffInSeconds(now());
        
        if ($diffInSeconds < 60) {
            // Menos de 1 minuto - mostrar segundos
            return $diffInSeconds == 1 ? 'Hace 1 segundo' : "Hace {$diffInSeconds} segundos";
        } elseif ($diffInSeconds < 3600) {
            // Menos de 1 hora - mostrar minutos
            $minutes = floor($diffInSeconds / 60);
            return $minutes == 1 ? 'Hace 1 minuto' : "Hace {$minutes} minutos";
        } elseif ($diffInSeconds < 86400) {
            // Menos de 24 horas - mostrar horas
            $hours = floor($diffInSeconds / 3600);
            return $hours == 1 ? 'Hace 1 hora' : "Hace {$hours} horas";
        } else {
            // 24 horas o más - mostrar días
            $days = floor($diffInSeconds / 86400);
            return $days == 1 ? 'Hace 1 día' : "Hace {$days} días";
        }
    }

    /**
     * Endpoint para verificar el estado de conexión de un vehículo
     */
    public function getVehicleConnectionStatus($vehicleId)
    {
        $connectionStatus = $this->determineConnectionStatus($vehicleId);
        
        return response()->json($connectionStatus);
    }

    /**
     * Endpoint para forzar actualización de caché
     */
    public function refreshVehicleCache($vehicleId)
    {
        // Limpiar caché existente
        Cache::forget("vehicle_telemetry_{$vehicleId}");
        Cache::forget("vehicle_telemetry_timestamp_{$vehicleId}");
        
        // Obtener nuevas lecturas
        $latestReadings = $this->getLatestSensorReadings($vehicleId);
        
        return response()->json([
            'message' => 'Cache refreshed successfully',
            'latest_readings' => $latestReadings
        ]);
    }
}