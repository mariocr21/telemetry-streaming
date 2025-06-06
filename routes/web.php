<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientDeviceController;
use App\Http\Controllers\DeviceInventoryController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::resource('device-inventory', DeviceInventoryController::class);

Route::resource('clients', ClientController::class);
Route::prefix('clients/{client}')->name('clients.')->group(function () {
    Route::resource('devices', ClientDeviceController::class)->parameters([
        'devices' => 'device'
    ]);
    
    // Rutas adicionales para activar/desactivar dispositivos
    Route::post('devices/{device}/activate', [ClientDeviceController::class, 'activate'])
        ->name('devices.activate');
    Route::post('devices/{device}/deactivate', [ClientDeviceController::class, 'deactivate'])
        ->name('devices.deactivate');
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
