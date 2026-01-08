<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientDeviceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceInventoryController;
use App\Http\Controllers\RegisterVehiculeController;
use App\Http\Controllers\ReplayController;
use App\Http\Controllers\TelemetryController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\LogMonitorController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Main Dashboard - Redirects to Dashboard Config (vehicle selector)
Route::get('dashboard', function () {
    return redirect()->route('dashboard.config.index');
})->middleware(['auth', 'verified'])->name('dashboard');

// DEPRECATED: Old dashboard routes - kept for reference, can be removed later
// Route::get('dashboard-pro', function () {
//     return Inertia::render('DashboardPro', [
//         'devices' => \App\Models\ClientDevice::with('activeVehicle')->get()
//     ]);
// })->middleware(['auth', 'verified'])->name('dashboard.pro');

// Route::get('telemetry-live', function () {
//     return Inertia::render('TelemetryDashboardPro', [
//         'devices' => \App\Models\ClientDevice::with('activeVehicle')->get()
//     ]);
// })->middleware(['auth', 'verified'])->name('telemetry.live');

// NEW: Dynamic Database-Driven Dashboard
Route::get('dashboard-dynamic/{vehicleId?}', function ($vehicleId = null) {
    $user = auth()->user();
    $isSuperAdmin = $user->isSuperAdmin();

    // Get available vehicles based on user role
    if ($isSuperAdmin) {
        $availableVehicles = \App\Models\Vehicle::with(['clientDevice.client'])
            ->where('status', true)
            ->whereHas('clientDevice')
            ->get()
            ->map(function ($vehicle) {
                return [
                    'id' => $vehicle->id,
                    'name' => $vehicle->nickname ?: trim(($vehicle->make ?? '') . ' ' . ($vehicle->model ?? '')) ?: $vehicle->vin,
                    'make' => $vehicle->make,
                    'model' => $vehicle->model,
                    'year' => $vehicle->year,
                    'nickname' => $vehicle->nickname,
                    'license_plate' => $vehicle->license_plate,
                    'vin' => $vehicle->vin,
                    'client' => $vehicle->clientDevice?->client ? [
                        'id' => $vehicle->clientDevice->client->id,
                        'full_name' => $vehicle->clientDevice->client->first_name . ' ' . $vehicle->clientDevice->client->last_name,
                        'company' => $vehicle->clientDevice->client->company,
                    ] : null,
                ];
            });
    } else {
        $availableVehicles = \App\Models\Vehicle::with(['clientDevice'])
            ->where('status', true)
            ->whereHas('clientDevice', function ($query) use ($user) {
                $query->where('client_id', $user->client_id);
            })
            ->get()
            ->map(function ($vehicle) {
                return [
                    'id' => $vehicle->id,
                    'name' => $vehicle->nickname ?: trim(($vehicle->make ?? '') . ' ' . ($vehicle->model ?? '')) ?: $vehicle->vin,
                    'make' => $vehicle->make,
                    'model' => $vehicle->model,
                    'year' => $vehicle->year,
                    'nickname' => $vehicle->nickname,
                    'license_plate' => $vehicle->license_plate,
                    'vin' => $vehicle->vin,
                    'client' => null,
                ];
            });
    }

    // If no vehicle ID provided, get the first available vehicle
    if (!$vehicleId) {
        $vehicleId = $availableVehicles->first()['id'] ?? 1;
    }

    // Optionally preload config for SSR (faster initial render)
    $preloadedConfig = null;
    try {
        $layout = \App\Models\DashboardLayout::with([
            'groups.widgets.definition',
            'groups.widgets.bindings',
        ])
            ->where('vehicle_id', $vehicleId)
            ->where('is_active', true)
            ->first();

        if ($layout) {
            $vehicle = \App\Models\Vehicle::find($vehicleId);
            $preloadedConfig = [
                'vehicle_id' => (int) $vehicleId,
                'layout' => $layout->toConfigArray(),
                'groups' => $layout->groups->map(fn($g) => $g->toConfigArray())->toArray(),
                'special_components' => [
                    'map' => [
                        'enabled' => $layout->grid_config['map']['enabled'] ?? true,
                        'config' => [
                            'defaultLayer' => $layout->grid_config['map']['defaultLayer'] ?? 'dark',
                        ]
                    ],
                    'shift_lights' => [
                        'enabled' => $layout->grid_config['shiftLights']['enabled'] ?? false,
                        'config' => [
                            'totalLights' => $layout->grid_config['shiftLights']['totalLights'] ?? 10,
                            'startRpm' => $layout->grid_config['shiftLights']['startRpm'] ?? 1000,
                            'shiftRpm' => $layout->grid_config['shiftLights']['shiftRpm'] ?? 6000,
                            'maxRpm' => $layout->grid_config['shiftLights']['maxRpm'] ?? 8000,
                            'rpmSensorKey' => $layout->grid_config['shiftLights']['rpmSensorKey'] ?? 'RPM',
                        ]
                    ],
                ],
                'meta' => [
                    'generated_at' => now()->toISOString(),
                    'cache_ttl' => 3600,
                    'version' => '1.0',
                ],
            ];
        }
    } catch (\Exception $e) {
        \Log::warning('Could not preload dashboard config: ' . $e->getMessage());
    }

    return Inertia::render('DashboardDynamic', [
        'vehicleId' => (int) $vehicleId,
        'preloadedConfig' => $preloadedConfig,
        'availableVehicles' => $availableVehicles->values()->toArray(),
        'isSuperAdmin' => $isSuperAdmin,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard.dynamic');

// Dashboard Configuration Pages
Route::prefix('dashboard-config')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [\App\Http\Controllers\DashboardConfigController::class, 'index'])
        ->name('dashboard.config.index');
    Route::get('/{vehicleId}/edit', [\App\Http\Controllers\DashboardConfigController::class, 'edit'])
        ->name('dashboard.config.edit');
});

// Dashboard V2 (Fixed Layout - Slate Pro)
Route::prefix('dashboard-v2')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/{vehicleId}', [\App\Http\Controllers\DashboardV2Controller::class, 'show'])
        ->name('dashboard.v2.show');
    Route::get('/{vehicleId}/config', [\App\Http\Controllers\DashboardV2Controller::class, 'edit'])
        ->name('dashboard.v2.config');
});

Route::get('/log-monitor', [LogMonitorController::class, 'index'])
    ->name('log.monitor');
Route::resource('device-inventory', DeviceInventoryController::class)->middleware(['auth', 'verified']);

// Admin Sensors Management (Super Admin Only)
Route::resource('admin/sensors', \App\Http\Controllers\SensorController::class)
    ->middleware(['auth', 'verified'])
    ->names('admin.sensors');

// Admin Vehicles Management (Super Admin Only)
Route::prefix('admin/vehicles')->middleware(['auth', 'verified'])->name('admin.vehicles.')->group(function () {
    Route::get('/', [\App\Http\Controllers\VehicleAdminController::class, 'index'])->name('index');
    Route::get('/available-devices', [\App\Http\Controllers\VehicleAdminController::class, 'getAvailableDevices'])->name('available-devices');
    Route::post('/', [\App\Http\Controllers\VehicleAdminController::class, 'store'])->name('store');
    Route::get('/{vehicle}', [\App\Http\Controllers\VehicleAdminController::class, 'show'])->name('show');
    Route::post('/{vehicle}/assign-device', [\App\Http\Controllers\VehicleAdminController::class, 'assignDevice'])->name('assign-device');
    Route::post('/{vehicle}/toggle-status', [\App\Http\Controllers\VehicleAdminController::class, 'toggleStatus'])->name('toggle-status');
    Route::delete('/{vehicle}', [\App\Http\Controllers\VehicleAdminController::class, 'destroy'])->name('destroy');
});

// Admin Clients Management (Super Admin Only)
Route::prefix('admin/clients')->middleware(['auth', 'verified'])->name('admin.clients.')->group(function () {
    Route::get('/', [\App\Http\Controllers\ClientAdminController::class, 'index'])->name('index');
    Route::post('/', [\App\Http\Controllers\ClientAdminController::class, 'store'])->name('store');
    Route::get('/{client}', [\App\Http\Controllers\ClientAdminController::class, 'show'])->name('show');
    Route::put('/{client}', [\App\Http\Controllers\ClientAdminController::class, 'update'])->name('update');
    Route::delete('/{client}', [\App\Http\Controllers\ClientAdminController::class, 'destroy'])->name('destroy');
});

Route::resource('clients', ClientController::class)->middleware(['auth', 'verified']);
Route::prefix('clients/{client}')->middleware(['auth', 'verified'])->name('clients.')->group(function () {
    Route::resource('devices', ClientDeviceController::class)->parameters([
        'devices' => 'device'
    ]);

    // Rutas adicionales para activar/desactivar dispositivos
    Route::post('devices/{device}/activate', [ClientDeviceController::class, 'activate'])
        ->name('devices.activate');
    Route::post('devices/{device}/deactivate', [ClientDeviceController::class, 'deactivate'])
        ->name('devices.deactivate');

    Route::get('/vehicle/{vehicleId}/connection-status', [DashboardController::class, 'getVehicleConnectionStatus'])
        ->name('vehicle.connection.status');

    Route::post('/vehicle/{vehicleId}/refresh-cache', [DashboardController::class, 'refreshVehicleCache'])
        ->name('vehicle.cache.refresh');

    // Ruta para obtener últimos datos de telemetría (ya existente en RegisterVehiculeController)
    Route::get('/telemetry/latest/{vehicleId}', [RegisterVehiculeController::class, 'getLatestTelemetry'])
        ->name('telemetry.latest');

    // Rutas anidadas para vehículos dentro de dispositivos
    Route::prefix('devices/{device}')->name('devices.')->group(function () {

        // Rutas CRUD para vehículos
        Route::resource('vehicles', VehicleController::class)->parameters([
            'vehicles' => 'vehicle'
        ]);

        // Rutas adicionales para vehículos
        Route::prefix('vehicles/{vehicle}')->name('vehicles.')->group(function () {
            Route::get(
                '/export-sensor-data',
                [VehicleController::class, 'exportSensorData']
            )->name('export-sensor-data');
            // Configuración de sensores
            Route::post('toggle-sensor', [VehicleController::class, 'toggleSensor'])
                ->name('toggle-sensor');

            Route::post('update-sensor-config', [VehicleController::class, 'updateSensorConfig'])
                ->name('update-sensor-config');

            // Datos para gráficos (AJAX)
            Route::get('sensor-data', [VehicleController::class, 'getSensorData'])
                ->name('sensor-data');

            // Exportar datos
            Route::get('export-data', [VehicleController::class, 'exportData'])
                ->name('export-data');

            // Activar/desactivar vehículo
            Route::post('activate', [VehicleController::class, 'activate'])
                ->name('activate');

            Route::post('deactivate', [VehicleController::class, 'deactivate'])
                ->name('deactivate');

            // Forzar sincronización
            Route::post('sync', [VehicleController::class, 'syncSensors'])
                ->name('sync');

            // Agregar sensores desde el catálogo global
            Route::post('add-sensors', [VehicleController::class, 'addSensors'])
                ->name('add-sensors');
        });
    });
});

Route::get('/vehicle/{clientDevice}', [DashboardController::class, 'getDeviceVehicleActive'])
    ->name('api.vehicle')
    ->middleware('auth');
Route::get('/vehicle/{vehicle}/telemetry', [RegisterVehiculeController::class, 'getLatestTelemetry'])
    ->middleware('auth');

Route::prefix('telemetry')->group(function () {
    // Obtener últimos datos de telemetría
    Route::get('/latest/{vehicleId}', [RegisterVehiculeController::class, 'getLatestTelemetry'])
        ->name('telemetry.latest');

    // Historial de telemetría
    Route::get('/history/{vehicleId}', [TelemetryController::class, 'getHistory'])
        ->name('telemetry.history');

    // Estadísticas de telemetría
    Route::get('/stats/{vehicleId}', [TelemetryController::class, 'getStats'])
        ->name('telemetry.stats');
});

Route::prefix('replays')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [ReplayController::class, 'index'])->name('replays.index');
});

// Test endpoints (solo en desarrollo)
if (app()->environment('local')) {
    Route::prefix('test')->group(function () {
        // Simular datos de telemetría
        Route::post('/simulate/{vehicleId}', [TelemetryController::class, 'simulateData'])
            ->name('test.simulate');

        // Test WebSocket
        Route::post('/websocket/{vehicleId}', [TelemetryController::class, 'testWebSocket'])
            ->name('test.websocket');
    });
}
Route::middleware(['auth', 'verified'])
    ->prefix('api/vehicles')
    ->group(function () {
        Route::get('/sessions/{vehicleId}', [ReplayController::class, 'getAvailableSessions'])
            ->name('vehicles.sessions');
        Route::get('/session-data/{vehicleId}', [ReplayController::class, 'getSessionData'])
            ->name('vehicles.session-data');
        Route::get('/{vehicle}/replay', [ReplayController::class, 'getReplayData'])
            ->name('vehicles.replay');
    });
require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
