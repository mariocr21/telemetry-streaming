<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WidgetInstance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'widget_group_id',
        'widget_definition_id',
        'props',
        'sort_order',
        'size_class',
        'style_override',
        'is_visible',
    ];

    protected $casts = [
        'props' => 'json',
        'style_override' => 'json',
        'is_visible' => 'boolean',
    ];

    // ─────────────────────────────────────────────────────────────
    // RELATIONSHIPS
    // ─────────────────────────────────────────────────────────────

    public function group(): BelongsTo
    {
        return $this->belongsTo(WidgetGroup::class, 'widget_group_id');
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(WidgetDefinition::class, 'widget_definition_id');
    }

    public function bindings(): HasMany
    {
        return $this->hasMany(SensorWidgetBinding::class);
    }

    // ─────────────────────────────────────────────────────────────
    // SCOPES
    // ─────────────────────────────────────────────────────────────

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    // ─────────────────────────────────────────────────────────────
    // METHODS
    // ─────────────────────────────────────────────────────────────

    /**
     * Get widget configuration for frontend
     */
    public function toConfigArray(): array
    {
        $definition = $this->definition;

        // Get bindings - make sure it's always an array
        $bindings = $this->bindings ?? collect();
        $bindingsArray = $bindings->isNotEmpty()
            ? $bindings->map(fn($b) => $b->toConfigArray())->toArray()
            : [];

        return [
            'id' => $this->id,
            'type' => $definition->type,
            'component' => $definition->component_name,
            'size' => $this->size_class,
            // Use merged props - defaults from schema + instance overrides
            'props' => $this->merged_props,
            'style_override' => $this->style_override,
            'bindings' => $bindingsArray,
        ];
    }

    /**
     * Merge default props from definition with instance props.
     * Handles cases where props might contain schema objects instead of values.
     */
    public function getMergedPropsAttribute(): array
    {
        $defaultProps = [];
        $schema = $this->definition->props_schema ?? [];

        // Handle case where schema might be a string (double-encoded JSON)
        if (is_string($schema)) {
            $schema = json_decode($schema, true) ?? [];
        }

        // Extract default values from schema
        if (is_array($schema)) {
            foreach ($schema as $key => $config) {
                if (is_array($config) && isset($config['default'])) {
                    $defaultProps[$key] = $config['default'];
                }
            }
        }

        // Get instance props, ensuring it's an array
        $instanceProps = $this->props;
        if (is_string($instanceProps)) {
            $instanceProps = json_decode($instanceProps, true) ?? [];
        }
        if (!is_array($instanceProps)) {
            $instanceProps = [];
        }

        // CRITICAL FIX: Check if instanceProps contains schema objects (bad data)
        // If so, extract the 'default' values from them instead of using them directly
        $cleanedInstanceProps = [];
        foreach ($instanceProps as $key => $value) {
            // If the value is a schema-like object (has 'type' and 'default' keys)
            if (is_array($value) && isset($value['type']) && array_key_exists('default', $value)) {
                // Extract just the default value
                $cleanedInstanceProps[$key] = $value['default'];
            } else {
                // It's a normal value, use as-is
                $cleanedInstanceProps[$key] = $value;
            }
        }

        return array_merge($defaultProps, $cleanedInstanceProps);
    }
}
