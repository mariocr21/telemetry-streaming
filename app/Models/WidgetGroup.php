<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WidgetGroup extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'dashboard_layout_id',
        'name',
        'slug',
        'icon',
        'grid_column_start',
        'grid_column_span',
        'grid_row_start',
        'grid_row_span',
        'sort_order',
        'style_config',
        'is_visible',
        'is_collapsible',
        'is_collapsed',
    ];

    protected $casts = [
        'style_config' => 'json',
        'is_visible' => 'boolean',
        'is_collapsible' => 'boolean',
        'is_collapsed' => 'boolean',
    ];

    // ─────────────────────────────────────────────────────────────
    // RELATIONSHIPS
    // ─────────────────────────────────────────────────────────────

    public function dashboardLayout(): BelongsTo
    {
        return $this->belongsTo(DashboardLayout::class);
    }

    public function widgets(): HasMany
    {
        return $this->hasMany(WidgetInstance::class)->orderBy('sort_order');
    }

    // ─────────────────────────────────────────────────────────────
    // SCOPES
    // ─────────────────────────────────────────────────────────────

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    // ─────────────────────────────────────────────────────────────
    // ACCESSORS
    // ─────────────────────────────────────────────────────────────

    /**
     * Get CSS grid position
     */
    public function getGridStyleAttribute(): array
    {
        return [
            'colStart' => $this->grid_column_start,
            'colSpan' => $this->grid_column_span,
            'rowStart' => $this->grid_row_start,
            'rowSpan' => $this->grid_row_span,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // METHODS
    // ─────────────────────────────────────────────────────────────

    /**
     * Get group configuration for frontend
     */
    public function toConfigArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'icon' => $this->icon,
            'grid' => $this->grid_style,
            'style' => $this->style_config ?? [
                'bgColor' => 'bg-dash-card',
                'borderColor' => 'border-slate-700',
            ],
            'is_collapsible' => $this->is_collapsible,
            'is_collapsed' => $this->is_collapsed,
            'widgets' => $this->widgets->map(fn($w) => $w->toConfigArray())->toArray(),
        ];
    }
}
