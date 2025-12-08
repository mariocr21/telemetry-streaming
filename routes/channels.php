<?php

use App\Models\ClientDevice;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('telemetry', function ($user) {
    // Permitir acceso si el usuario está autenticado
    return $user !== null;
});

// Canal privado para vehículo específico
Broadcast::channel('vehicle.{vehicleId}', function ($user, $vehicleId) {
    // Verificar que $vehicleId no sea null o undefined
    if (!$user || $vehicleId === 'undefined' || $vehicleId === null) {
        return false;
    }
    
    // Convertir $vehicleId a número entero
    $vehicleId = intval($vehicleId);
    
    $vehicle = Vehicle::find($vehicleId);
    
    if (!$vehicle) {
        return false;
    }
    
    // Verificar permisos de acceso
    return $vehicle->client_id === $user->client_id || $user->isSuperAdmin();
});

// Canal privado para dispositivo específico
Broadcast::channel('device.{deviceId}', function ($user, $deviceId) {
    if (!$user) {
        return false;
    }
    
    $device = ClientDevice::find($deviceId);
    
    if (!$device) {
        return false;
    }
    
    // Verificar si el dispositivo pertenece al cliente del usuario
    return $device->client_id === $user->client_id || $user->isSuperAdmin();
});

// Canal para alertas en tiempo real
Broadcast::channel('alerts.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});