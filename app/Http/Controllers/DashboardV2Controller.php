<?php

namespace App\Http\Controllers;

use App\Models\DashboardLayout;
use App\Models\Vehicle;
use App\Models\VehicleSensor;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * DashboardV2Controller
 * 
 * Handles the fixed-layout Dashboard V2 (Slate Pro Edition).
 */
class DashboardV2Controller extends Controller
{
    /**
     * Display the Dashboard V2 for a vehicle.
     */
    public function show(int $vehicleId)
    {
        $user = auth()->user();
        $isSuperAdmin = $user->isSuperAdmin();

        // Get available vehicles based on user role
        if ($isSuperAdmin) {
            $availableVehicles = Vehicle::with(['clientDevice.client'])
                ->where('status', true)
                ->get()
                ->map(fn($vehicle) => [
                    'id' => $vehicle->id,
                    'make' => $vehicle->make,
                    'model' => $vehicle->model,
                    'nickname' => $vehicle->nickname,
                    'client' => $vehicle->clientDevice?->client ? [
                        'id' => $vehicle->clientDevice->client->id,
                        'company' => $vehicle->clientDevice->client->company,
                    ] : null,
                ]);
        } else {
            $availableVehicles = Vehicle::with(['clientDevice'])
                ->where('status', true)
                ->whereHas('clientDevice', fn($q) => $q->where('client_id', $user->client_id))
                ->get()
                ->map(fn($vehicle) => [
                    'id' => $vehicle->id,
                    'make' => $vehicle->make,
                    'model' => $vehicle->model,
                    'nickname' => $vehicle->nickname,
                    'client' => null,
                ]);
        }

        $vehicle = Vehicle::findOrFail($vehicleId);

        // Get the dashboard layout with v2 mapping
        $layout = DashboardLayout::where('vehicle_id', $vehicleId)
            ->where('is_active', true)
            ->first();

        // Extract v2 mapping from grid_config
        $mapping = $layout?->grid_config['v2_mapping'] ?? [];
        $shiftLightsConfig = $layout?->grid_config['shiftLights'] ?? [
            'enabled' => true,
            'maxRpm' => 9000,
            'shiftRpm' => 8500,
            'startRpm' => 4000,
        ];
        $cameraConfig = $layout?->grid_config['cameras'] ?? [
            'streamBaseUrl' => 'https://stream.neurona.xyz',
            'cameras' => [],
        ];

        return Inertia::render('DashboardV2', [
            'vehicleId' => $vehicleId,
            'vehicle' => [
                'id' => $vehicle->id,
                'make' => $vehicle->make,
                'model' => $vehicle->model,
                'nickname' => $vehicle->nickname,
            ],
            'mapping' => $mapping,
            'shiftLightsConfig' => $shiftLightsConfig,
            'cameraConfig' => $cameraConfig,
            'availableVehicles' => $availableVehicles->values()->toArray(),
            'isSuperAdmin' => $isSuperAdmin,
        ]);
    }

    /**
     * Show the configuration page for Dashboard V2.
     */
    public function edit(int $vehicleId)
    {
        $vehicle = Vehicle::findOrFail($vehicleId);

        // Get vehicle sensors
        $sensors = VehicleSensor::with('sensor')
            ->where('vehicle_id', $vehicleId)
            ->where('is_active', true)
            ->get()
            ->filter(fn($vs) => $vs->sensor !== null)
            ->map(fn($vs) => [
                'id' => $vs->id,
                'sensor_key' => $this->formatSensorKey($vs->sensor->name ?? $vs->sensor->pid),
                'label' => $vs->sensor->name ?? 'Sensor',
                'unit' => $vs->sensor->unit ?? '',
            ])
            ->values();

        // Get current mapping
        $layout = DashboardLayout::where('vehicle_id', $vehicleId)
            ->where('is_active', true)
            ->first();

        $currentMapping = $layout?->grid_config['v2_mapping'] ?? [];
        $shiftLightsConfig = $layout?->grid_config['shiftLights'] ?? [
            'enabled' => true,
            'maxRpm' => 9000,
            'shiftRpm' => 8500,
            'startRpm' => 4000,
        ];
        $cameraConfig = $layout?->grid_config['cameras'] ?? [
            'streamBaseUrl' => 'https://stream.neurona.xyz',
            'cameras' => [],
        ];

        return Inertia::render('DashboardV2Config', [
            'vehicleId' => $vehicleId,
            'vehicle' => [
                'id' => $vehicle->id,
                'make' => $vehicle->make,
                'model' => $vehicle->model,
                'nickname' => $vehicle->nickname,
            ],
            'sensors' => $sensors,
            'currentMapping' => $currentMapping,
            'shiftLightsConfig' => $shiftLightsConfig,
            'cameraConfig' => $cameraConfig,
        ]);
    }

    /**
     * Update the Dashboard V2 configuration.
     */
    public function update(Request $request, int $vehicleId)
    {
        $request->validate([
            'mapping' => 'required|array',
            'shiftLightsConfig' => 'required|array',
            'cameraConfig' => 'nullable|array',
        ]);

        $vehicle = Vehicle::findOrFail($vehicleId);

        // Get or create layout
        $layout = DashboardLayout::firstOrCreate(
            ['vehicle_id' => $vehicleId, 'is_active' => true],
            [
                'name' => 'Dashboard V2 - ' . ($vehicle->nickname ?? $vehicle->make),
                'theme' => 'racing-red',
                'grid_config' => [],
            ]
        );

        // Merge with existing grid_config
        $gridConfig = $layout->grid_config ?? [];
        $gridConfig['v2_mapping'] = $request->mapping;
        $gridConfig['shiftLights'] = $request->shiftLightsConfig;

        if ($request->has('cameraConfig')) {
            $gridConfig['cameras'] = $request->cameraConfig;
        }

        $layout->grid_config = $gridConfig;
        $layout->save();

        return response()->json(['success' => true]);
    }

    /**
     * Convert sensor name to telemetry key format (snake_case).
     */
    private function formatSensorKey(?string $name): string
    {
        if (!$name)
            return 'unknown';
        return preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(' ', '_', $name));
    }
}
