<?php
// Modificar tu RegisterVehiculeController.php

namespace App\Http\Controllers;

use App\Events\VehicleTelemetryEvent;
use App\Models\ClientDevice;
use App\Models\Register;
use App\Models\VehicleSensor;
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
                's' => 'required|array',
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

            foreach ($data['s'] as $sensorHex => $sensorData) {
                $vehicleSensor = VehicleSensor::wherehas('vehicles', function ($query) use ($data) {
                    $query->where('vin', $data['idc']);
                })
                    ->whereHas('sensor', function ($query) use ($sensorHex) {
                        $query->where('pid', $sensorHex);
                    })
                    ->with('sensor')
                    ->first();

                if ($vehicleSensor) {
                    // Guardar en base de datos
                    Register::create([
                        'client_device_id' => $clientDevice->id,
                        'vehicle_id' => $clientVehicle->id,
                        'vehicle_sensor_id' => $vehicleSensor->id,
                        'value' => $sensorData['v'],
                        'recorded_at' => $sensorData['dt'],
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
                        'timestamp' => $sensorData['dt']
                    ];

                    $processedReadings[$sensorHex] = $processedValue;
                }
            }

            // Broadcast evento de telemetría en tiempo real
            broadcast(new VehicleTelemetryEvent(
                $clientVehicle->id,
                $clientDevice->id,
                $telemetryData
            ));

            // Cache para API rápida
            Cache::put(
                "vehicle_telemetry_{$clientVehicle->id}",
                $processedReadings,
                300 // 5 minutos
            );

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
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

    // Endpoint para obtener últimos datos en caché
    public function getLatestTelemetry($vehicleId)
    {
        $telemetry = Cache::get("vehicle_telemetry_{$vehicleId}", []);

        return response()->json([
            'vehicle_id' => $vehicleId,
            'timestamp' => now()->toISOString(),
            'data' => $telemetry
        ]);
    }
}
