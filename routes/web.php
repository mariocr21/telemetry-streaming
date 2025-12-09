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

Route::get('dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');
Route::get('/log-monitor', [LogMonitorController::class, 'index'])
    ->name('log.monitor');
Route::resource('device-inventory', DeviceInventoryController::class)->middleware(['auth', 'verified']);

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
