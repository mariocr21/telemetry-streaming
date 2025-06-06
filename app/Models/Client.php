<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    /** @use HasFactory<\Database\Factories\ClientFactory> */
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'zip_code',
        'country',
        'company',
        'job_title',
    ];

    /**
     * Get the users associated with the client.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }
    /**
     * Get the full name of the client.
     */
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }
    public function devices()
    {
        return $this->hasMany(ClientDevice::class, 'client_id');
    }
}
