<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Register extends Model
{
    /** @use HasFactory<\Database\Factories\RegisterFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'vehicle_sensor_id',
        'value',
        'recorded_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'value' => 'decimal:2',
    ];

    protected $dates = [
        'recorded_at',
        'created_at',
        'updated_at',
        'deleted_at'
    ];
    
    public function sensor()
    {
        return $this->belongsTo(VehicleSensor::class, 'vehicle_sensor_id');
    }

    // Método helper para obtener el vehículo a través del sensor
    public function vehicle()
    {
        return $this->hasOneThrough(
            Vehicle::class,
            VehicleSensor::class,
            'id', // Foreign key en vehicle_sensors
            'id', // Foreign key en vehicles
            'vehicle_sensor_id', // Local key en registers
            'vehicle_id' // Local key en vehicle_sensors
        );
    }

    // Método helper para obtener el sensor original
    public function originalSensor()
    {
        return $this->hasOneThrough(
            Sensor::class,
            VehicleSensor::class,
            'id', // Foreign key en vehicle_sensors
            'id', // Foreign key en sensors
            'vehicle_sensor_id', // Local key en registers
            'sensor_id' // Local key en vehicle_sensors
        );
    }
}