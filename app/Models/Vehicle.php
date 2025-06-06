<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    /** @use HasFactory<\Database\Factories\VehicleFactory> */
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'client_id',
        'client_device_id',
        'vin',
        'protocol',
        'supported_pids',
        'make',
        'model',
        'year',
        'license_plate',
        'color',
        'nickname',
        'auto_detected',
        'is_configured',
        'first_reading_at',
        'last_reading_at'
    ];

    protected $casts = [
        'supported_pids' => 'json',
        'auto_detected' => 'boolean',
        'is_configured' => 'boolean',
        'first_reading_at' => 'datetime',
        'last_reading_at' => 'datetime'
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function clientDevice()
    {
        return $this->belongsTo(ClientDevice::class, 'client_device_id');
    }

    public function sensors()
    {
        return $this->belongsToMany(Sensor::class, 'vehicle_sensors')
            ->withPivot('is_active', 'frequency_seconds', 'last_reading_at')
            ->withTimestamps();
    }

    public function vehicleSensors()
    {
        return $this->hasMany(VehicleSensor::class);
    }
}
