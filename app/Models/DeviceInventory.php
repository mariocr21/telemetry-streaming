<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeviceInventory extends Model
{
    /** @use HasFactory<\Database\Factories\DeviceInventoryFactory> */
    use HasFactory, SoftDeletes;
    
    protected $fillable = [
        'serial_number',
        'device_uuid',
        'model',
        'hardware_version',
        'firmware_version',
        'status',
        'manufactured_date',
        'sold_date',
        'notes'
    ];

    protected $casts = [
        'manufactured_date' => 'datetime',
        'sold_date' => 'datetime',
    ];

    // Relación con dispositivos de clientes
    public function clientDevices()
    {
        return $this->hasMany(ClientDevice::class, 'device_inventory_id');
    }

    // Scope para dispositivos disponibles
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }
}