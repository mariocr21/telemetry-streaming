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
    
    public function device()
    {
        return $this->belongsTo(ClientDevice::class, 'client_device_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function sensor()
    {
        return $this->belongsTo(VehicleSensor::class, 'vehicle_sensor_id');
    }
}
