<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientDevice extends Model
{
    /** @use HasFactory<\Database\Factories\ClientDeviceFactory> */
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'device_inventory_id',
        'client_id',
        'device_name',
        'mac_address',
        'status',
        'activated_at',
        'last_ping',
        'device_config'
    ];

    protected $casts = [
        'device_config' => 'json',
        'activated_at' => 'datetime',
        'last_ping' => 'datetime'
    ];

    public function DeviceInventory()
    {
        return $this->belongsTo(DeviceInventory::class, 'device_inventory_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

public function vehicles()
    {
        return $this->hasMany(Vehicle::class, 'client_device_id');
    }
}
