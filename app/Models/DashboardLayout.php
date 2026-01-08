<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DashboardLayout extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'vehicle_id',
        'name',
        'theme',
        'grid_config',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'grid_config' => 'json',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Default grid configuration
     */
    public static function defaultGridConfig(): array
    {
        return [
            'columns' => 12,
            'gap' => 4,
            'breakpoints' => [
                'lg' => ['columns' => 12],
                'md' => ['columns' => 6],
                'sm' => ['columns' => 1],
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // RELATIONSHIPS
    // ─────────────────────────────────────────────────────────────

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(WidgetGroup::class)->orderBy('sort_order');
    }

    // ─────────────────────────────────────────────────────────────
    // SCOPES
    // ─────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForVehicle($query, int $vehicleId)
    {
        return $query->where('vehicle_id', $vehicleId);
    }

    // ─────────────────────────────────────────────────────────────
    // METHODS
    // ─────────────────────────────────────────────────────────────

    /**
     * Get configuration JSON for the frontend
     */
    public function toConfigArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'theme' => $this->theme,
            'grid_config' => $this->grid_config ?? self::defaultGridConfig(),
        ];
    }
}
