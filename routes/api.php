<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\VehicleRegistrationController;
use App\Http\Controllers\RegisterVehiculeController;
use App\Http\Controllers\ReplayController;
use App\Http\Controllers\Api\DashboardLayoutController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Registro/actualización automática de vehículo
Route::post('/register/vehicle', [VehicleRegistrationController::class, 'registerVehicle'])
    ->name('api.obd2.vehicle.register');

// Rutas para recibir datos OBD2
Route::prefix('/registers')->group(function () {
    Route::post('', [RegisterVehiculeController::class, 'store']);
});

// ═══════════════════════════════════════════════════════════════════
// DASHBOARD LAYOUT API (Dynamic Dashboard Configuration)
// ═══════════════════════════════════════════════════════════════════

// Widget definitions catalog (for admin configurator)
Route::get('/dashboard/widgets', [DashboardLayoutController::class, 'getWidgetDefinitions'])
    ->name('api.dashboard.widgets');

// Vehicle-specific dashboard configuration
Route::prefix('/vehicles/{vehicleId}/dashboard')->group(function () {
    // Get dashboard configuration for a vehicle
    Route::get('', [DashboardLayoutController::class, 'show'])
        ->name('api.vehicles.dashboard.show');

    // Update dashboard configuration
    Route::put('', [DashboardLayoutController::class, 'update'])
        ->name('api.vehicles.dashboard.update');

    // Delete dashboard configuration
    Route::delete('', [DashboardLayoutController::class, 'destroy'])
        ->name('api.vehicles.dashboard.destroy');

    // Auto-generate dashboard based on vehicle sensors
    Route::post('/generate', [DashboardLayoutController::class, 'generate'])
        ->name('api.vehicles.dashboard.generate');
});

// ═══════════════════════════════════════════════════════════════════
// DASHBOARD V2 API (Fixed Layout Sensor Mapping)
// ═══════════════════════════════════════════════════════════════════
Route::put('/dashboard-v2/{vehicleId}/config', [\App\Http\Controllers\DashboardV2Controller::class, 'update'])
    ->name('api.dashboard.v2.update');
