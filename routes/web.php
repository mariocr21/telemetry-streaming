<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientDeviceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceInventoryController;
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

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
