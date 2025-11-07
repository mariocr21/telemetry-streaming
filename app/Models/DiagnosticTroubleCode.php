<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiagnosticTroubleCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_id',
        'code',
        'description',
        'detected_at',
        'redetected_at',
        'resolved_at',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'detected_at' => 'datetime',
        'redetected_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}