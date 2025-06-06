<?php

namespace App\Http\Controllers;

use App\Events\NewRegisterEvent;
use App\Models\ClientDevice;
use App\Models\Register;
use App\Models\VehicleSensor;
use Illuminate\Http\Request;

class RegisterVehiculeController extends Controller
{
    /**
     * Store the vehicle registration data.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
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

            foreach ($data['s'] as $sensorHex => $sensorData) {
                $vehicleSensor = VehicleSensor::wherehas('vehicle', function ($query) use ($data) {
                    $query->where('vin', $data['idc']);
                })
                    ->whereHas('sensor', function ($query) use ($sensorHex) {
                        $query->where('pid', $sensorHex);
                    })
                    ->first();

                if ($vehicleSensor) {
                    Register::create([
                        'client_device_id' => $clientDevice->id,
                        'vehicle_id' => $clientVehicle->id,
                        'vehicle_sensor_id' => $vehicleSensor->id,
                        'value' => $sensorData['v'],
                        'recorded_at' => $sensorData['dt'],
                    ]);

                    broadcast(new NewRegisterEvent($clientDevice->id, [
                        'device_id' => $clientDevice->id,
                        'vehicle_id' => $clientVehicle->id,
                        'sensor_hex' => $sensorHex,
                        'value' => $sensorData['v'],
                        'recorded_at' => $sensorData['dt'],
                    ]));
                }
            }

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
