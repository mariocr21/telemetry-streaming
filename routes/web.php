<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientDeviceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceInventoryController;
use App\Http\Controllers\RegisterVehiculeController;
use App\Http\Controllers\TelemetryController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

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
require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
