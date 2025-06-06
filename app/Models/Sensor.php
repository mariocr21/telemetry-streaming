<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sensor extends Model
{
    /** @use HasFactory<\Database\Factories\SensorFactory> */
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'pid',
        'name',
        'description',
        'category',
        'unit',
        'data_type',
        'min_value',
        'max_value',
        'requires_calculation',
        'calculation_formula',
        'data_bytes',
        'is_standard',
        'notes'
    ];

    public function vehicleSensors()
    {
        return $this->hasMany(VehicleSensor::class);
    }

}
