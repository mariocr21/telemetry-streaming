<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ClientDevice;
use App\Models\DeviceInventory;
use App\Models\Vehicle;
use App\Models\Sensor;
use App\Models\VehicleSensor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class VehicleRegistrationController extends Controller
{
    /**
     * Registrar o actualizar vehículo automáticamente desde datos OBD2
     */
    public function registerVehicle(Request $request)
    {
        try {
            // Validar la estructura de datos recibida
            $validator = Validator::make($request->all(), [
                'id' => 'required|string',      // Serial del dispositivo
                'idc' => 'required|string',     // VIN del vehículo
                's' => 'required|array',        // Array de PIDs soportados
                's.*' => 'string' // Validar formato PID
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos inválidos',
                    'errors' => $validator->errors()
                ], 400);
            }

            $deviceSerial = $request->input('id');
            $vin = $request->input('idc');
            $supportedPids = $request->input('s');

            Log::info("Procesando registro de vehículo", [
                'device_serial' => $deviceSerial,
                'vin' => $vin,
                'pids_count' => count($supportedPids)
            ]);

            // Buscar el dispositivo por serial number
            $deviceInventory = DeviceInventory::where('serial_number', $deviceSerial)->first();

            if (!$deviceInventory) {
                Log::warning("Dispositivo no encontrado", ['serial' => $deviceSerial]);
                return response()->json([
                    'success' => false,
                    'message' => 'Dispositivo no encontrado'
                ], 404);
            }

            // Buscar el dispositivo del cliente asociado
            $clientDevice = ClientDevice::where('device_inventory_id', $deviceInventory->id)->first();

            if (!$clientDevice) {
                Log::warning("Dispositivo no asignado a cliente", ['device_id' => $deviceInventory->id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Dispositivo no está asignado a ningún cliente'
                ], 404);
            }

            // Usar transacción para asegurar consistencia
            DB::beginTransaction();

            try {
                // Buscar o crear el vehículo
                $vehicle = Vehicle::where('client_device_id', $clientDevice->id)
                    ->where('vin', $vin)
                    ->first();

                $isNewVehicle = false;

                if (!$vehicle) {
                    // Crear nuevo vehículo
                    $vehicle = Vehicle::create([
                        'client_id' => $clientDevice->client_id,
                        'client_device_id' => $clientDevice->id,
                        'vin' => $vin,
                        'supported_pids' => $supportedPids,
                        'auto_detected' => true,
                        'is_configured' => false,
                        'first_reading_at' => now(),
                        'last_reading_at' => now()
                    ]);

                    $isNewVehicle = true;
                    // Desactivar otros vehículos del mismo dispositivo
                    Vehicle::where('client_device_id', $clientDevice->id)
                        ->where('id', '!=', $vehicle->id)
                        ->update(['status' => false]);

                    Log::info("Nuevo vehículo creado", ['vehicle_id' => $vehicle->id, 'vin' => $vin]);
                } else {
                    // Actualizar vehículo existente
                    $vehicle->update([
                        'supported_pids' => $supportedPids,
                        'status' => true,
                        'last_reading_at' => now()
                    ]);

                    // desactivar los otros vehiculos del mismo dispositivo
                    Vehicle::where('client_device_id', $clientDevice->id)
                        ->where('id', '!=', $vehicle->id)
                        ->update(['status' => false]);

                    Log::info("Vehículo actualizado", ['vehicle_id' => $vehicle->id, 'vin' => $vin]);
                }

                // Procesar sensores soportados
                $sensorsResult = $this->processSupportedSensors($vehicle, $supportedPids);

                // Activar el dispositivo si está pendiente
                if ($clientDevice->status === 'pending') {
                    $clientDevice->update([
                        'status' => 'active',
                        'activated_at' => now(),
                        'last_ping' => now()
                    ]);
                    Log::info("Dispositivo activado automáticamente", ['device_id' => $clientDevice->id]);
                } else {
                    // Actualizar último ping
                    $clientDevice->update(['last_ping' => now()]);
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => $isNewVehicle ? 'Vehículo registrado exitosamente' : 'Vehículo actualizado exitosamente',
                    'data' => [
                        'vehicle_id' => $vehicle->id,
                        'vin' => $vin,
                        'is_new_vehicle' => $isNewVehicle,
                        'device_activated' => $clientDevice->status === 'active',
                        'sensors' => [
                            'total_supported' => count($supportedPids),
                            'mapped_sensors' => $sensorsResult['mapped'],
                            'new_sensors' => $sensorsResult['new'],
                            'unmapped_pids' => $sensorsResult['unmapped']
                        ]
                    ]
                ], 200);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error("Error en registro de vehículo", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
                'error' => config('app.debug') ? $e->getMessage() : 'Error procesando solicitud'
            ], 500);
        }
    }

    /**
     * Procesar sensores soportados y vincularlos al vehículo
     */ 
    private function processSupportedSensors(Vehicle $vehicle, array $supportedPids)
    {
        $result = [
            'mapped' => 0,
            'new' => 0,
            'unmapped' => []
        ];

        // Obtener sensores existentes que coincidan con los PIDs
        $existingSensors = Sensor::whereIn('pid', $supportedPids)->get()->keyBy('pid');

        // Obtener TODOS los sensores vinculados al vehículo (activos e inactivos)
        $existingVehicleSensors = VehicleSensor::where('vehicle_id', $vehicle->id)
            ->with('sensor')
            ->get()
            ->keyBy('sensor.pid');

        // PASO 1: Desactivar sensores que YA NO están en el array de PIDs soportados
        VehicleSensor::where('vehicle_id', $vehicle->id)
            ->whereHas('sensor', function ($query) use ($supportedPids) {
                $query->whereNotIn('pid', $supportedPids);
            })
            ->update(['is_active' => false]);

        // PASO 2: Procesar PIDs soportados
        foreach ($supportedPids as $pid) {
            if (isset($existingSensors[$pid])) {
                $sensor = $existingSensors[$pid];

                if (isset($existingVehicleSensors[$pid])) {
                    // El sensor ya existe - ASEGURAR QUE ESTÉ ACTIVO y actualizar valores
                    $existingVehicleSensors[$pid]->update([
                        'is_active' => true,
                        'frequency_seconds' => 5, // Actualizar frecuencia si es necesario
                        'min_value' => $sensor->min_value,
                        'max_value' => $sensor->max_value
                    ]);

                    Log::info("Sensor actualizado/reactivado", [
                        'vehicle_id' => $vehicle->id,
                        'sensor_pid' => $pid,
                        'sensor_name' => $sensor->name
                    ]);
                } else {
                    // Sensor nuevo - CREARLO
                    VehicleSensor::create([
                        'vehicle_id' => $vehicle->id,
                        'sensor_id' => $sensor->id,
                        'is_active' => true,
                        'frequency_seconds' => 5,
                        'min_value' => $sensor->min_value,
                        'max_value' => $sensor->max_value
                    ]);

                    $result['new']++;
                    Log::info("Nuevo sensor vinculado al vehículo", [
                        'vehicle_id' => $vehicle->id,
                        'sensor_pid' => $pid,
                        'sensor_name' => $sensor->name
                    ]);
                }

                $result['mapped']++;
            } else {
                // PID no mapeado en nuestra base de datos
                $result['unmapped'][] = $pid;
                Log::warning("PID no reconocido", ['pid' => $pid, 'vehicle_id' => $vehicle->id]);
            }
        }

        return $result;
    }
    /**
     * Desactivar sensores que ya no están en la lista de PIDs soportados
     */
    private function deactivateUnsupportedSensors(Vehicle $vehicle, array $supportedPids)
    {
        $vehicleSensors = VehicleSensor::where('vehicle_id', $vehicle->id)
            ->where('is_active', true)
            ->with('sensor')
            ->get();

        foreach ($vehicleSensors as $vehicleSensor) {
            if (!in_array($vehicleSensor->sensor->pid, $supportedPids)) {
                $vehicleSensor->update(['is_active' => false]);
                Log::info("Sensor desactivado (no soportado)", [
                    'vehicle_id' => $vehicle->id,
                    'sensor_pid' => $vehicleSensor->sensor->pid,
                    'sensor_name' => $vehicleSensor->sensor->name
                ]);
            }
        }
    }

    /**
     * Obtener información del vehículo por serial del dispositivo
     */
    public function getVehicleInfo(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'device_serial' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Serial del dispositivo requerido',
                    'errors' => $validator->errors()
                ], 400);
            }

            $deviceSerial = $request->input('device_serial');

            // Buscar dispositivo y vehículo asociado
            $deviceInventory = DeviceInventory::where('serial_number', $deviceSerial)->first();

            if (!$deviceInventory) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dispositivo no encontrado'
                ], 404);
            }

            $clientDevice = ClientDevice::where('device_inventory_id', $deviceInventory->id)->first();

            if (!$clientDevice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dispositivo no asignado'
                ], 404);
            }

            $vehicle = Vehicle::where('client_device_id', $clientDevice->id)->first();

            if (!$vehicle) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vehículo no registrado'
                ], 404);
            }

            // Obtener sensores activos
            $activeSensors = VehicleSensor::where('vehicle_id', $vehicle->id)
                ->where('is_active', true)
                ->with('sensor')
                ->get()
                ->map(function ($vs) {
                    return [
                        'pid' => $vs->sensor->pid,
                        'name' => $vs->sensor->name,
                        'unit' => $vs->sensor->unit,
                        'frequency' => $vs->frequency_seconds,
                        'min_value' => $vs->min_value,
                        'max_value' => $vs->max_value
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'vehicle' => [
                        'id' => $vehicle->id,
                        'vin' => $vehicle->vin,
                        'make' => $vehicle->make,
                        'model' => $vehicle->model,
                        'year' => $vehicle->year,
                        'nickname' => $vehicle->nickname,
                        'is_configured' => $vehicle->is_configured,
                        'auto_detected' => $vehicle->auto_detected
                    ],
                    'device' => [
                        'id' => $clientDevice->id,
                        'name' => $clientDevice->device_name,
                        'status' => $clientDevice->status,
                        'last_ping' => $clientDevice->last_ping
                    ],
                    'sensors' => $activeSensors
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error obteniendo información del vehículo", [
                'error' => $e->getMessage(),
                'device_serial' => $request->input('device_serial')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }
}
