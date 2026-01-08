<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SensorWidgetBinding extends Model
{
    use HasFactory;

    protected $fillable = [
        'widget_instance_id',
        'vehicle_sensor_id',
        'telemetry_key',
        'target_prop',
        'slot',
        'transform',
        'display_label',
        'display_unit',
        'thresholds',
    ];

    protected $casts = [
        'transform' => 'array',
        'thresholds' => 'array',
    ];

    // ─────────────────────────────────────────────────────────────
    // RELATIONSHIPS
    // ─────────────────────────────────────────────────────────────

    public function widgetInstance(): BelongsTo
    {
        return $this->belongsTo(WidgetInstance::class);
    }

    public function vehicleSensor(): BelongsTo
    {
        return $this->belongsTo(VehicleSensor::class);
    }

    /**
     * Get the sensor through vehicle_sensor
     */
    public function sensor()
    {
        return $this->vehicleSensor?->sensor();
    }

    // ─────────────────────────────────────────────────────────────
    // ACCESSORS
    // ─────────────────────────────────────────────────────────────

    /**
     * Get the display label (override or from sensor)
     */
    public function getResolvedLabelAttribute(): string
    {
        if ($this->display_label) {
            return $this->display_label;
        }

        return $this->vehicleSensor?->sensor?->name ?? $this->telemetry_key;
    }

    /**
     * Get the display unit (override or from sensor)
     */
    public function getResolvedUnitAttribute(): string
    {
        if ($this->display_unit) {
            return $this->display_unit;
        }

        return $this->vehicleSensor?->sensor?->unit ?? '';
    }

    // ─────────────────────────────────────────────────────────────
    // METHODS
    // ─────────────────────────────────────────────────────────────

    /**
     * Get binding configuration for frontend
     */
    public function toConfigArray(): array
    {
        return [
            'telemetry_key' => $this->telemetry_key,
            'target_prop' => $this->target_prop,
            'slot' => $this->slot,
            'transform' => $this->transform,
            'label' => $this->resolved_label,
            'unit' => $this->resolved_unit,
            'thresholds' => $this->thresholds,
        ];
    }

    /**
     * Apply transformation to a raw value
     */
    public function transformValue($rawValue)
    {
        if ($rawValue === null || !$this->transform) {
            return $rawValue;
        }

        $value = $rawValue;
        $transform = $this->transform;

        // Apply multiplier
        if (isset($transform['multiply'])) {
            $value = $value * $transform['multiply'];
        }

        // Apply offset
        if (isset($transform['offset'])) {
            $value = $value + $transform['offset'];
        }

        // Apply clamping
        if (isset($transform['clamp'])) {
            $min = $transform['clamp']['min'] ?? PHP_FLOAT_MIN;
            $max = $transform['clamp']['max'] ?? PHP_FLOAT_MAX;
            $value = max($min, min($max, $value));
        }

        // Apply rounding
        if (isset($transform['round'])) {
            $value = round($value, $transform['round']);
        }

        return $value;
    }
}
