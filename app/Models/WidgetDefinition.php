<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WidgetDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'component_name',
        'description',
        'icon',
        'props_schema',
        'category',
        'min_width',
        'min_height',
        'supports_thresholds',
        'supports_multiple_slots',
        'supports_animation',
        'is_active',
    ];

    protected $casts = [
        'props_schema' => 'json',
        'supports_thresholds' => 'boolean',
        'supports_multiple_slots' => 'boolean',
        'supports_animation' => 'boolean',
        'is_active' => 'boolean',
    ];

    // ─────────────────────────────────────────────────────────────
    // RELATIONSHIPS
    // ─────────────────────────────────────────────────────────────

    public function instances(): HasMany
    {
        return $this->hasMany(WidgetInstance::class);
    }

    // ─────────────────────────────────────────────────────────────
    // SCOPES
    // ─────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    // ─────────────────────────────────────────────────────────────
    // METHODS
    // ─────────────────────────────────────────────────────────────

    /**
     * Get definition for selector dropdown
     */
    public function toSelectorArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'icon' => $this->icon,
            'category' => $this->category,
            'description' => $this->description,
            'supports_thresholds' => $this->supports_thresholds,
            'supports_multiple_slots' => $this->supports_multiple_slots,
        ];
    }
}
