<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VehicleSensor extends Model
{
    /** @use HasFactory<\Database\Factories\VehicleSensorFactory> */
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'vehicle_id',
        'sensor_id',
        'is_active',
        'frequency_seconds',
        'min_value',
        'max_value',
        'mapping_key',
        'source_type',
        'last_reading_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'frequency_seconds' => 'integer',
        'last_reading_at' => 'datetime'
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function sensor()
    {
        return $this->belongsTo(Sensor::class);
    }
}
