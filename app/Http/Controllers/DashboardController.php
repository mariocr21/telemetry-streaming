<?php

namespace App\Http\Controllers;

use App\Http\Resources\DeviceClientCollection;
use App\Http\Resources\DeviceClientResource;
use App\Models\ClientDevice;
use App\Models\DiagnosticTroubleCode;
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
            'devices' => new DeviceClientCollection($devices),
        ]);
    }

    /**
     * Endpoint que obtiene la data completa del vehículo activo, incluyendo sensores clasificados.
     */
    public function getDeviceVehicleActive(Request $request, ClientDevice $clientDevice)
    {
        $vehicle = $clientDevice->vehicles()->where('status', true)->first();

        if (!$vehicle) {
            return response()->json([
                'message' => 'No active vehicle found for this device.',
            ], 404);
        }

        // Cargar el vehículo con sus sensores
        $vehicle->load([
            'vehicleSensors' => function ($query) {
                $query->where('is_active', true);
            },
            'vehicleSensors.sensor'
        ]);

        // Obtener las últimas lecturas de cada sensor (los valores ya están procesados/calculados)
        $latestReadings = $this->getLatestSensorReadings($vehicle->id);

        // Determinar el estado de conexión basado en la última lectura
        $connectionStatus = $this->determineConnectionStatus($vehicle->id);

        // Obtener códigos DTC activos
        $dtcCodes = $this->getActiveDTCs($vehicle->id);

        // CLASIFICAR Y ESTRUCTURAR LOS SENSORES
        $structuredSensors = $this->structureAndClassifySensors(
            $vehicle->vehicleSensors,
            $latestReadings['data']
        );

        Log::info('Vehicle data retrieved with latest readings', [
            'vehicle_id' => $vehicle->id,
        ]);

        return response()->json([
            'vehicle' => $vehicle,
            'latest_readings' => $latestReadings,
            'structured_sensors' => $structuredSensors, // CLAVE: Datos pre-estructurados
            'connection_status' => $connectionStatus,
            'dtc_codes' => $dtcCodes
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
     * Obtener códigos DTC activos para un vehículo
     */
    private function getActiveDTCs($vehicleId)
    {
        // Primero intentar obtener desde caché
        $cachedDtcCodes = Cache::get("vehicle_dtc_{$vehicleId}");

        if ($cachedDtcCodes) {
            Log::info('Using cached DTC data', ['vehicle_id' => $vehicleId]);
            return $cachedDtcCodes;
        }

        // Si no hay en caché, obtener de la base de datos
        $dtcCodes = DiagnosticTroubleCode::where('vehicle_id', $vehicleId)
            ->where('is_active', true)
            ->get()
            ->map(function ($dtc) {
                return [
                    'id' => $dtc->id,
                    'code' => $dtc->code,
                    'description' => $dtc->description ?? $this->getDTCDescription($dtc->code),
                    'severity' => $dtc->severity ?? $this->getDTCSeverity($dtc->code),
                    'detected_at' => $dtc->detected_at,
                ];
            })
            ->toArray();

        // Guardar en caché para futuras peticiones
        if (!empty($dtcCodes)) {
            Cache::put(
                "vehicle_dtc_{$vehicleId}",
                $dtcCodes,
                300 // 5 minutos
            );
        }

        return $dtcCodes;
    }

    /**
     * Método para obtener descripción de código DTC
     */
    private function getDTCDescription($code)
    {
        $prefixes = [
            'P0' => 'Powertrain Issue',
            'P1' => 'Manufacturer-Specific Powertrain Issue',
            'P2' => 'Powertrain Issue (Fuel/Air Monitoring)',
            'P3' => 'Powertrain Issue (Ignition)',
            'B0' => 'Body Issue',
            'C0' => 'Chassis Issue',
            'U0' => 'Network Issue',
        ];

        $prefix = substr($code, 0, 2);

        return $prefixes[$prefix] ?? 'Unknown Issue';
    }

    /**
     * Método para determinar la severidad del código DTC
     */
    private function getDTCSeverity($code)
    {
        $prefix = substr($code, 0, 1);

        switch ($prefix) {
            case 'P':
                return 'high'; // Problemas del motor suelen ser más críticos
            case 'B':
                return 'medium';
            case 'C':
                return 'medium';
            case 'U':
                return 'low';
            default:
                return 'unknown';
        }
    }

    /**
     * Determinar el estado de conexión basado en la última actividad
     */
    private function determineConnectionStatus($vehicleId)
    {
        // Usar tu estructura existente: buscar a través de vehicle_sensors
        $lastRegister = Register::whereHas('sensor', function ($query) use ($vehicleId) {
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
     * Endpoint para obtener los códigos DTC activos
     */
    public function getVehicleDTCs($vehicleId)
    {
        $dtcCodes = $this->getActiveDTCs($vehicleId);

        return response()->json([
            'vehicle_id' => $vehicleId,
            'timestamp' => now()->toISOString(),
            'dtc_codes' => $dtcCodes,
            'active_count' => count($dtcCodes)
        ]);
    }

    /**
     * Endpoint para forzar actualización de caché
     */
    public function refreshVehicleCache($vehicleId)
    {
        // Limpiar caché existente
        Cache::forget("vehicle_telemetry_{$vehicleId}");
        Cache::forget("vehicle_telemetry_timestamp_{$vehicleId}");
        Cache::forget("vehicle_dtc_{$vehicleId}");

        // Obtener nuevas lecturas
        $latestReadings = $this->getLatestSensorReadings($vehicleId);
        $dtcCodes = $this->getActiveDTCs($vehicleId);

        return response()->json([
            'message' => 'Cache refreshed successfully',
            'latest_readings' => $latestReadings,
            'dtc_codes' => $dtcCodes
        ]);
    }


    /**
     * Clasifica los sensores en categorías (primary, secondary, gps) y fusiona su metadata con el valor actual.
     */
    private function structureAndClassifySensors($vehicleSensors, $readings)
    {
        // Catálogo de PIDs fijos para el panel principal
        $pidsCatalog = [
            'rpm' => ['0x0C', '0xC'],
            'speed' => ['0x0D', 'D', 'vel_kmh'],
            'temperature' => ['0x05'],
            'battery' => ['0x42', 'BAT', 'volt'],
            'oilPressure' => ['0x0B', 'oil_press'],
            'throttlePosition' => ['0x11'],
            'fuelLevel' => ['0x2F'],
            'GEAR' => ['GEAR'],
        ];

        $structuredSensors = [
            'primary' => [],
            'secondary' => [],
            'gps' => [],
        ];

        // Obtener la lista plana de PIDs primarios
        $primaryPids = collect($pidsCatalog)->flatten()->toArray();

        foreach ($vehicleSensors as $vehicleSensor) {
            $sensorMetadata = $vehicleSensor->sensor;
            $pid = $sensorMetadata->pid;

            // Usar el valor pre-calculado
            $value = $readings[$pid] ?? null;

            // Si el valor no es nulo (no tiene sentido enviar sensores sin valor al front)
            if ($value === null) {
                // Usamos 0 como fallback inicial, pero es mejor que el front maneje 'N/A'
                $value = 0;
            }

            // Estructura de datos que el front necesita (plana y con valor)
            $sensorData = [
                'pid' => $pid,
                'name' => $sensorMetadata->name,
                'value' => (float) $value, // Aseguramos que sea float
                'unit' => $sensorMetadata->unit,
                'description' => $sensorMetadata->description,
                'min_value' => (float) ($sensorMetadata->min_value ?? 0),
                'max_value' => (float) ($sensorMetadata->max_value ?? 100),
                'vehicle_sensor_id' => $vehicleSensor->id,
            ];

            // Clasificación
            $category = null;
            foreach ($pidsCatalog as $key => $pids) {
                if (in_array($pid, $pids)) {
                    $category = $key;
                    break;
                }
            }

            if ($category) {
                $structuredSensors['primary'][$category] = $sensorData;
            } elseif (in_array($pid, ['lat', 'lng', 'alt_m', 'rumbo', 'vel_kmh'])) {
                // GPS y velocidad (si no fue mapeada como primaria)
                $structuredSensors['gps'][$pid] = $sensorData;
            } else {
                $structuredSensors['secondary'][] = $sensorData;
            }
        }

        // Agregar los sensores GPS al objeto readings si no tienen un valor
        if (!isset($structuredSensors['gps']['lat'])) {
            $structuredSensors['gps']['lat'] = ['pid' => 'lat', 'name' => 'Latitude', 'value' => 0, 'unit' => '°'];
            $structuredSensors['gps']['lng'] = ['pid' => 'lng', 'name' => 'Longitude', 'value' => 0, 'unit' => '°'];
        }

        return $structuredSensors;
    }
}
