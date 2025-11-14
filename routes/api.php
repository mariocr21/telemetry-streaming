<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\VehicleRegistrationController;
use App\Http\Controllers\RegisterVehiculeController;
use App\Http\Controllers\ReplayController;
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
// Rutas para obtener datos de vehículos
