<?php

namespace App\Http\Controllers;

use App\Models\DashboardLayout;
use App\Models\Vehicle;
use App\Models\VehicleSensor;
use App\Models\WidgetDefinition;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * DashboardConfigController
 * 
 * Handles the admin pages for configuring dashboard layouts.
 */
class DashboardConfigController extends Controller
{
    /**
     * Convert sensor name to telemetry key format (snake_case).
     * Examples: "Engine RPM" -> "Engine_RPM", "Coolant Temp" -> "Coolant_Temp"
     */
    private function formatSensorKey(?string $name): string
    {
        if (!$name)
            return 'unknown';
        // Replace spaces with underscores and remove special characters
        return preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(' ', '_', $name));
    }

    /**
     * Display listing of vehicles with their dashboard configurations.
     */
    public function index()
    {
        // Get all vehicles with their sensors and active layouts
        $vehicles = Vehicle::with([
            'vehicleSensors.sensor',
            'dashboardLayouts' => function ($query) {
                $query->where('is_active', true)
                    ->withCount([
                        'groups',
                        'groups as widgets_count' => function ($q) {
                            $q->selectRaw('COALESCE(SUM((SELECT COUNT(*) FROM widget_instances WHERE widget_instances.widget_group_id = widget_groups.id)), 0)');
                        }
                    ]);
            },
        ])->get();

        // Map vehicles to include active layout info
        $vehiclesData = $vehicles->map(function ($vehicle) {
            $activeLayout = $vehicle->dashboardLayouts->first();

            return [
                'id' => $vehicle->id,
                'make' => $vehicle->make,
                'model' => $vehicle->model,
                'year' => $vehicle->year,
                'nickname' => $vehicle->nickname,
                'status' => $vehicle->status,
                'sensors' => $vehicle->vehicleSensors
                    ->filter(fn($vs) => $vs->sensor !== null)
                    ->map(fn($vs) => [
                        'id' => $vs->id,
                        'sensor_key' => $this->formatSensorKey($vs->sensor->name ?? $vs->sensor->pid),
                        'label' => $vs->sensor->name ?? 'Sensor',
                        'unit' => $vs->sensor->unit ?? '',
                    ])
                    ->values(),
                'active_layout' => $activeLayout ? [
                    'id' => $activeLayout->id,
                    'name' => $activeLayout->name,
                    'theme' => $activeLayout->theme,
                    'is_active' => $activeLayout->is_active,
                    'groups_count' => $activeLayout->groups->count(),
                    'widgets_count' => $activeLayout->groups->sum(fn($g) => $g->widgets->count()),
                    'updated_at' => $activeLayout->updated_at?->toISOString(),
                ] : null,
            ];
        });

        return Inertia::render('DashboardConfig/Index', [
            'vehicles' => $vehiclesData,
        ]);
    }

    /**
     * Show the dashboard editor for a specific vehicle.
     */
    public function edit(int $vehicleId)
    {
        $vehicle = Vehicle::findOrFail($vehicleId);

        // Get vehicle sensors with their sensor definitions
        $sensors = VehicleSensor::with('sensor')
            ->where('vehicle_id', $vehicleId)
            ->where('is_active', true) // Only active sensors
            ->get()
            ->filter(fn($vs) => $vs->sensor !== null) // Ensure sensor exists
            ->map(fn($vs) => [
                'id' => $vs->id,
                // Use sensor name for display and PID for technical key
                'sensor_key' => $this->formatSensorKey($vs->sensor->name ?? $vs->sensor->pid),
                'label' => $vs->sensor->name ?? 'Sensor',
                'unit' => $vs->sensor->unit ?? '',
                'category' => $vs->sensor->category ?? 'general',
                'pid' => $vs->sensor->pid ?? null,
            ])
            ->values(); // Re-index array after filter

        // Get widget definitions
        $widgetDefinitions = WidgetDefinition::where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        // Get active layout with groups and widgets
        $layout = DashboardLayout::with([
            'groups' => function ($query) {
                $query->orderBy('sort_order');
            },
            'groups.widgets' => function ($query) {
                $query->orderBy('sort_order');
            },
            'groups.widgets.definition',
            'groups.widgets.bindings',
        ])
            ->where('vehicle_id', $vehicleId)
            ->where('is_active', true)
            ->first();

        // Transform layout data
        $layoutData = null;
        $groupsData = [];

        if ($layout) {
            $layoutData = [
                'id' => $layout->id,
                'name' => $layout->name,
                'theme' => $layout->theme,
                'grid_config' => $layout->grid_config,
                'is_active' => $layout->is_active,
            ];

            $groupsData = $layout->groups->map(fn($group) => [
                'id' => $group->id,
                'name' => $group->name,
                'slug' => $group->slug,
                'icon' => $group->icon,
                'grid_column_start' => $group->grid_column_start,
                'grid_column_span' => $group->grid_column_span,
                'style_config' => $group->style_config,
                'sort_order' => $group->sort_order,
                'widgets' => $group->widgets->map(fn($widget) => [
                    'id' => $widget->id,
                    'widget_definition_id' => $widget->widget_definition_id,
                    'definition' => [
                        'id' => $widget->definition->id,
                        'type' => $widget->definition->type,
                        'name' => $widget->definition->name,
                        'icon' => $widget->definition->icon,
                        'component_name' => $widget->definition->component_name,
                        'supports_thresholds' => $widget->definition->supports_thresholds,
                        'supports_multiple_slots' => $widget->definition->supports_multiple_slots,
                        'props_schema' => $widget->definition->props_schema,
                    ],
                    'size_class' => $widget->size_class,
                    'props' => $widget->props,
                    'sort_order' => $widget->sort_order,
                    'bindings' => $widget->bindings->map(fn($b) => [
                        'id' => $b->id,
                        'vehicle_sensor_id' => $b->vehicle_sensor_id,
                        'telemetry_key' => $b->telemetry_key,
                        'target_prop' => $b->target_prop,
                        'slot' => $b->slot,
                        'label' => $b->resolved_label,
                        'unit' => $b->resolved_unit,
                    ]),
                ]),
            ]);
        }

        return Inertia::render('DashboardConfig/Edit', [
            'vehicle' => [
                'id' => $vehicle->id,
                'make' => $vehicle->make,
                'model' => $vehicle->model,
                'year' => $vehicle->year,
                'nickname' => $vehicle->nickname,
            ],
            'layout' => $layoutData,
            'groups' => $groupsData,
            'sensors' => $sensors,
            'widgetDefinitions' => $widgetDefinitions,
        ]);
    }
}
