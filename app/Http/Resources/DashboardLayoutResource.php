<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardLayoutResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle_id' => $this->vehicle_id,
            'name' => $this->name,
            'theme' => $this->theme,
            'grid_config' => $this->grid_config ?? $this->defaultGridConfig(),
            'is_active' => $this->is_active,
            'is_default' => $this->is_default,
            'groups' => $this->whenLoaded('groups', function () {
                return $this->groups->map(fn($group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'slug' => $group->slug,
                    'icon' => $group->icon,
                    'grid' => [
                        'colStart' => $group->grid_column_start,
                        'colSpan' => $group->grid_column_span,
                        'rowStart' => $group->grid_row_start,
                        'rowSpan' => $group->grid_row_span,
                    ],
                    'style' => $group->style_config,
                    'is_visible' => $group->is_visible,
                    'is_collapsible' => $group->is_collapsible,
                    'widgets' => $group->widgets->map(fn($widget) => [
                        'id' => $widget->id,
                        'type' => $widget->definition->type,
                        'component' => $widget->definition->component_name,
                        'size' => $widget->size_class,
                        'props' => $widget->props,
                        'style_override' => $widget->style_override,
                        'bindings' => $widget->bindings->map(fn($binding) => [
                            'telemetry_key' => $binding->telemetry_key,
                            'target_prop' => $binding->target_prop,
                            'slot' => $binding->slot,
                            'transform' => $binding->transform,
                            'label' => $binding->resolved_label,
                            'unit' => $binding->resolved_unit,
                            'thresholds' => $binding->thresholds,
                        ]),
                    ]),
                ]);
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Get the default grid configuration.
     */
    private function defaultGridConfig(): array
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
}
