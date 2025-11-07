<?php
// RegisterVehiculeController.php modificado

namespace App\Http\Controllers;

use App\Events\VehicleTelemetryEvent;
use App\Models\ClientDevice;
use App\Models\Register;
use App\Models\VehicleSensor;
use App\Models\DiagnosticTroubleCode; // Asumimos que crearemos este modelo para los DTC
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RegisterVehiculeController extends Controller
{
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'id' => 'required|exists:device_inventories,serial_number',
                'idc' => 'required|exists:vehicles,vin',
                'dt' => 'required|string', // Nuevo campo de timestamp global
                's' => 'required|array',
                'DTC' => 'nullable|array', // Campo opcional para códigos de error
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'errors' => $e->errors(),
            ], 422);
        }

        try {
            $clientDevice = ClientDevice::with('vehicles')->WhereHas('DeviceInventory', function ($query) use ($data) {
                $query->where('serial_number', $data['id']);
            })->first();

            if (!$clientDevice) {
                return response()->json(['status' => 'error', 'message' => 'Device not found'], 404);
            }

            $clientVehicle = $clientDevice->vehicles()->where('vin', $data['idc'])->first();

            if (!$clientVehicle) {
                return response()->json(['status' => 'error', 'message' => 'Vehicle not found'], 404);
            }

            $telemetryData = [];
            $processedReadings = [];
            $timestamp = $data['dt']; // Usar el timestamp global

            // Registrar los datos recibidos en el log
            Log::debug('Received telemetry data', [
                'device_id' => $data['id'],
                'vehicle_vin' => $data['idc'],
                'timestamp' => $timestamp,
                'sensors' => $data['s'],
                'dtc_codes' => $data['DTC'] ?? [],
            ]);

            foreach ($data['s'] as $sensorHex => $sensorData) {
                $vehicleSensor = VehicleSensor::wherehas('vehicle', function ($query) use ($data) {
                    $query->where('vin', $data['idc']);
                })
                    ->whereHas('sensor', function ($query) use ($sensorHex) {
                        $query->where('pid', $sensorHex);
                    })
                    ->with('sensor')
                    ->first();

                if ($vehicleSensor && isset($sensorData['v'])) {
                    // Guardar en base de datos - ahora usando timestamp global
                    Register::create([
                        'client_device_id' => $clientDevice->id,
                        'vehicle_id' => $clientVehicle->id,
                        'vehicle_sensor_id' => $vehicleSensor->id,
                        'value' => $sensorData['v'],
                        'recorded_at' => $timestamp, // Usar timestamp global
                    ]);

                    // Preparar datos para broadcast
                    $processedValue = $this->processSensorValue(
                        $sensorData['v'],
                        $vehicleSensor->sensor
                    );

                    $telemetryData[$sensorHex] = [
                        'pid' => $sensorHex,
                        'raw_value' => $sensorData['v'],
                        'processed_value' => $processedValue,
                        'unit' => $vehicleSensor->sensor->unit,
                        'name' => $vehicleSensor->sensor->name,
                        'timestamp' => $timestamp // Usar timestamp global
                    ];

                    $processedReadings[$sensorHex] = $processedValue;
                }
            }

            // Procesamiento de códigos DTC si existen
            $dtcCodes = [];
            if (!empty($data['DTC'])) {
                foreach ($data['DTC'] as $code) {
                    // Guardar los DTC en la base de datos (asumiendo que tenemos un modelo para esto)
                    $dtc = DiagnosticTroubleCode::firstOrCreate([
                        'vehicle_id' => $clientVehicle->id,
                        'code' => $code,
                    ], [
                        'detected_at' => $timestamp,
                        'is_active' => true,
                    ]);
                    
                    // Si el DTC ya existía pero estaba marcado como inactivo, actualizarlo
                    if (!$dtc->wasRecentlyCreated && !$dtc->is_active) {
                        $dtc->update([
                            'is_active' => true,
                            'redetected_at' => $timestamp,
                        ]);
                    }
                    
                    $dtcCodes[] = [
                        'code' => $code,
                        'description' => $this->getDTCDescription($code), // Método para obtener descripción
                        'severity' => $this->getDTCSeverity($code), // Método para obtener severidad
                    ];
                }
                
                // Guardar los DTC actuales en caché para acceso rápido
                Cache::put(
                    "vehicle_dtc_{$clientVehicle->id}",
                    $dtcCodes,
                    300 // 5 minutos
                );
            }

            // Broadcast evento de telemetría en tiempo real, incluyendo DTC
            broadcast(new VehicleTelemetryEvent(
                $clientVehicle->id,
                $clientDevice->id,
                $telemetryData,
                $dtcCodes
            ));

            // Cache para API rápida
            Cache::put(
                "vehicle_telemetry_{$clientVehicle->id}",
                $processedReadings,
                300 // 5 minutos
            );

            //Actualizar el estatus de los sensores
            // Obtener los IDs de los VehicleSensor que se usaron
            $vehicleSensorIds = VehicleSensor::whereHas('vehicle', function ($query) use ($data) {
                $query->where('vin', $data['idc']);
                })
                ->whereHas('sensor', function ($query) use ($telemetryData) {
                $query->whereIn('pid', array_keys($telemetryData));
                })
                ->pluck('id');

            // Actualizar todos los sensores en una sola consulta
            VehicleSensor::whereIn('id', $vehicleSensorIds)
                ->update([
                'is_active' => true,
                'last_reading_at' => now(),
                ]);

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('Error processing telemetry: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function processSensorValue($rawValue, $sensor)
    {
        if (!$sensor->requires_calculation || !$sensor->calculation_formula) {
            return $rawValue;
        }

        try {
            $formula = $sensor->calculation_formula;
            $A = $rawValue;
            $B = 0; // Para datos de 2 bytes, implementar después

            // Reemplazar variables en la fórmula
            $calculatedFormula = str_replace(['A', 'B'], [$A, $B], $formula);

            // Evaluar de manera segura
            $result = eval("return $calculatedFormula;");

            return round($result, 2);
        } catch (Exception $e) {
            Log::error('Error calculating sensor value: ' . $e->getMessage());
            return $rawValue;
        }
    }

    // Método para obtener descripción de código DTC
    private function getDTCDescription($code)
    {
        // Implementar lógica para obtener descripción del código DTC
        // Puede ser desde una base de datos o un servicio externo
        // Por ahora retornamos una descripción genérica
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
    
    // Método para determinar la severidad del código DTC
    private function getDTCSeverity($code)
    {
        // Implementar lógica para determinar la severidad
        // Por ahora simplemente basado en el tipo de código
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

    // Endpoint para obtener últimos datos en caché
    public function getLatestTelemetry($vehicleId)
    {
        $telemetry = Cache::get("vehicle_telemetry_{$vehicleId}", []);
        $dtcCodes = Cache::get("vehicle_dtc_{$vehicleId}", []);

        return response()->json([
            'vehicle_id' => $vehicleId,
            'timestamp' => now()->toISOString(),
            'data' => $telemetry,
            'dtc_codes' => $dtcCodes
        ]);
    }
    
    // Endpoint para obtener los códigos DTC activos
    public function getActiveDTC($vehicleId)
    {
        $dtcCodes = Cache::get("vehicle_dtc_{$vehicleId}", []);
        
        if (empty($dtcCodes)) {
            // Si no hay en caché, intentar obtener de la base de datos
            $dtcCodes = DiagnosticTroubleCode::where('vehicle_id', $vehicleId)
                ->where('is_active', true)
                ->get()
                ->map(function($dtc) {
                    return [
                        'code' => $dtc->code,
                        'description' => $this->getDTCDescription($dtc->code),
                        'severity' => $this->getDTCSeverity($dtc->code),
                        'detected_at' => $dtc->detected_at,
                    ];
                })
                ->toArray();
        }
        
        return response()->json([
            'vehicle_id' => $vehicleId,
            'timestamp' => now()->toISOString(),
            'dtc_codes' => $dtcCodes
        ]);
    }
}