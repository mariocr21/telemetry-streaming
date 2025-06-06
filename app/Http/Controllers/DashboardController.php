<?php

namespace App\Http\Controllers;

use App\Models\ClientDevice;
use Illuminate\Http\Request;
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
        if ($vehicle) {
            return response()->json([
                'vehicle' => $vehicle->load('vehicleSensors.sensor'),
            ]);
        } else {
            return response()->json([
                'message' => 'No active vehicle found for this device.',
            ], 404);
        }
    }
}
