<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardLayoutResource;
use App\Models\DashboardLayout;
use App\Models\Vehicle;
use App\Models\WidgetDefinition;
use App\Models\WidgetGroup;
use App\Models\WidgetInstance;
use App\Models\SensorWidgetBinding;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class DashboardLayoutController extends Controller
{
    /**
     * Cache TTL in seconds (1 hour)
     */
    private const CACHE_TTL = 3600;

    /**
     * Get the dashboard configuration for a specific vehicle.
     * 
     * GET /api/vehicles/{vehicleId}/dashboard
     * 
     * Returns the complete JSON configuration needed to render
     * a dynamic dashboard in the frontend.
     */
    public function show(int $vehicleId): JsonResponse
    {
        $vehicle = Vehicle::with(['vehicleSensors.sensor'])->findOrFail($vehicleId);

        // For development, skip cache to see changes immediately
        // In production, use: Cache::remember("dashboard_config_{$vehicleId}", self::CACHE_TTL, ...)

        // Get the active layout for this vehicle
        $layout = DashboardLayout::with([
            'groups' => function ($query) {
                $query->where('is_visible', true)
                    ->orderBy('sort_order');
            },
            'groups.widgets' => function ($query) {
                $query->where('is_visible', true)
                    ->orderBy('sort_order');
            },
            'groups.widgets.definition',
            'groups.widgets.bindings.vehicleSensor.sensor', // Include sensor for label/unit
        ])
            ->where('vehicle_id', $vehicleId)
            ->where('is_active', true)
            ->first();

        // If no layout exists, return a default empty structure
        if (!$layout) {
            $config = $this->getEmptyLayoutConfig($vehicleId);
        } else {
            $config = $this->buildDashboardConfig($layout, $vehicle);
        }

        return response()->json([
            'success' => true,
            'data' => $config,
        ]);
    }

    /**
     * Get all available widget definitions (for the admin configurator).
     * 
     * GET /api/dashboard/widgets
     */
    public function getWidgetDefinitions(): JsonResponse
    {
        $widgets = WidgetDefinition::active()
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->map(fn($w) => $w->toSelectorArray());

        return response()->json([
            'success' => true,
            'data' => $widgets,
        ]);
    }

    /**
     * Create or update a dashboard layout for a vehicle.
     * 
     * PUT /api/vehicles/{vehicleId}/dashboard
     */
    public function update(Request $request, int $vehicleId): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($vehicleId);

        $validated = $request->validate([
            'layout' => 'sometimes|array',
            'layout.name' => 'sometimes|string|max:255',
            'layout.theme' => 'sometimes|string|max:50',
            'layout.grid_config' => 'sometimes|array',
            'groups' => 'sometimes|array',
            'groups.*.name' => 'required|string|max:255',
            'groups.*.icon' => 'nullable|string|max:50',
            'groups.*.grid_column_start' => 'sometimes|integer|min:1',
            'groups.*.grid_column_span' => 'sometimes|integer|min:1|max:12',
            'groups.*.widgets' => 'sometimes|array',
            'groups.*.widgets.*.widget_definition_id' => 'required|exists:widget_definitions,id',
            'groups.*.widgets.*.props' => 'sometimes|array',
            'groups.*.widgets.*.bindings' => 'sometimes|array',
        ]);

        // Extract layout data
        $layoutData = $validated['layout'] ?? [];

        try {
            DB::beginTransaction();

            // Get or create the layout
            $layout = DashboardLayout::firstOrCreate(
                ['vehicle_id' => $vehicleId, 'is_active' => true],
                [
                    'name' => $layoutData['name'] ?? 'Default Layout',
                    'theme' => $layoutData['theme'] ?? 'cyberpunk-dark',
                    'grid_config' => $layoutData['grid_config'] ?? DashboardLayout::defaultGridConfig(),
                ]
            );

            // Update layout properties if provided
            if (isset($layoutData['name']))
                $layout->name = $layoutData['name'];
            if (isset($layoutData['theme']))
                $layout->theme = $layoutData['theme'];
            if (isset($layoutData['grid_config']))
                $layout->grid_config = $layoutData['grid_config'];
            $layout->save();

            // Process groups if provided
            if (isset($validated['groups'])) {
                $this->syncGroups($layout, $validated['groups']);
            }

            DB::commit();

            // Clear cache
            Cache::forget("dashboard_config_{$vehicleId}");

            // Return the updated configuration
            return $this->show($vehicleId);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error updating dashboard layout',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Create a default dashboard layout for a vehicle based on its sensors.
     * 
     * POST /api/vehicles/{vehicleId}/dashboard/generate
     */
    public function generate(int $vehicleId): JsonResponse
    {
        $vehicle = Vehicle::with(['vehicleSensors.sensor'])->findOrFail($vehicleId);

        try {
            DB::beginTransaction();

            // Delete existing layouts for this vehicle
            DashboardLayout::where('vehicle_id', $vehicleId)->delete();

            // Create new default layout
            $layout = DashboardLayout::create([
                'vehicle_id' => $vehicleId,
                'name' => 'Auto-Generated Layout',
                'theme' => 'cyberpunk-dark',
                'grid_config' => DashboardLayout::defaultGridConfig(),
                'is_active' => true,
            ]);

            // Generate groups based on sensor categories
            $this->generateDefaultGroups($layout, $vehicle);

            DB::commit();

            // Clear cache
            Cache::forget("dashboard_config_{$vehicleId}");

            return response()->json([
                'success' => true,
                'message' => 'Dashboard layout generated successfully',
                'data' => $this->buildDashboardConfig($layout->fresh([
                    'groups.widgets.definition',
                    'groups.widgets.bindings',
                ]), $vehicle),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error generating dashboard layout',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Delete a dashboard layout.
     * 
     * DELETE /api/vehicles/{vehicleId}/dashboard
     */
    public function destroy(int $vehicleId): JsonResponse
    {
        $deleted = DashboardLayout::where('vehicle_id', $vehicleId)
            ->where('is_active', true)
            ->delete();

        Cache::forget("dashboard_config_{$vehicleId}");

        return response()->json([
            'success' => true,
            'message' => $deleted ? 'Dashboard layout deleted' : 'No active layout found',
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // PRIVATE METHODS
    // ─────────────────────────────────────────────────────────────

    /**
     * Build the complete dashboard configuration array
     */
    private function buildDashboardConfig(DashboardLayout $layout, Vehicle $vehicle): array
    {
        return [
            'vehicle_id' => $vehicle->id,
            'layout' => $layout->toConfigArray(),
            'groups' => $layout->groups->map(fn($g) => $g->toConfigArray())->toArray(),
            'special_components' => $this->getSpecialComponents($layout),
            'meta' => [
                'generated_at' => now()->toISOString(),
                'cache_ttl' => self::CACHE_TTL,
                'version' => '1.0',
            ],
        ];
    }

    /**
     * Get empty layout config for vehicles without a configured dashboard
     */
    private function getEmptyLayoutConfig(int $vehicleId): array
    {
        return [
            'vehicle_id' => $vehicleId,
            'layout' => [
                'id' => null,
                'name' => 'No Layout Configured',
                'theme' => 'cyberpunk-dark',
                'grid_config' => DashboardLayout::defaultGridConfig(),
            ],
            'groups' => [],
            'special_components' => [
                'map' => ['enabled' => true],
                'shift_lights' => ['enabled' => false],
            ],
            'meta' => [
                'generated_at' => now()->toISOString(),
                'cache_ttl' => 0,
                'version' => '1.0',
                'is_empty' => true,
            ],
        ];
    }

    /**
     * Get special components configuration (map, shift lights, etc.)
     */
    private function getSpecialComponents(DashboardLayout $layout): array
    {
        // Get shift lights config from grid_config, with defaults
        $gridConfig = $layout->grid_config ?? [];
        $mapConfig = $gridConfig['map'] ?? [];

        return [
            'map' => [
                'enabled' => $mapConfig['enabled'] ?? true,
                'config' => [
                    'defaultLayer' => $mapConfig['defaultLayer'] ?? 'dark',
                ],
                'grid' => [
                    'colStart' => 1,
                    'colSpan' => 5,
                    'rowStart' => 1,
                    'rowSpan' => 'full',
                ],
                'bindings' => [
                    'latitude' => 'GPS_Latitude',
                    'longitude' => 'GPS_Longitude',
                    'heading' => 'GPS_Heading',
                    'speed' => 'GPS_Speed',
                ],
            ],
            'shift_lights' => [
                'enabled' => $shiftLightsConfig['enabled'] ?? true,
                'position' => 'top',
                'bindings' => [
                    'rpm' => $shiftLightsConfig['rpmSensorKey'] ?? 'RPM',
                ],
                'config' => [
                    'totalLights' => $shiftLightsConfig['totalLights'] ?? 10,
                    'startRpm' => $shiftLightsConfig['startRpm'] ?? 4000,
                    'maxRpm' => $shiftLightsConfig['maxRpm'] ?? 8000,
                    'shiftRpm' => $shiftLightsConfig['shiftRpm'] ?? 7000,
                ],
            ],
        ];
    }

    /**
     * Sync groups from request data
     */
    private function syncGroups(DashboardLayout $layout, array $groupsData): void
    {
        // Delete existing groups (cascade will delete widgets and bindings)
        $layout->groups()->delete();

        foreach ($groupsData as $index => $groupData) {
            $group = WidgetGroup::create([
                'dashboard_layout_id' => $layout->id,
                'name' => $groupData['name'],
                'slug' => Str::slug($groupData['name']),
                'icon' => $groupData['icon'] ?? null,
                'grid_column_start' => $groupData['grid_column_start'] ?? 1,
                'grid_column_span' => $groupData['grid_column_span'] ?? 6,
                'grid_row_start' => $groupData['grid_row_start'] ?? null,
                'grid_row_span' => $groupData['grid_row_span'] ?? 1,
                'sort_order' => $index,
                'style_config' => $groupData['style_config'] ?? null,
                'is_visible' => $groupData['is_visible'] ?? true,
            ]);

            // Create widgets for this group
            if (isset($groupData['widgets'])) {
                $this->syncWidgets($group, $groupData['widgets']);
            }
        }
    }

    /**
     * Sync widgets for a group
     */
    private function syncWidgets(WidgetGroup $group, array $widgetsData): void
    {
        foreach ($widgetsData as $index => $widgetData) {
            $widget = WidgetInstance::create([
                'widget_group_id' => $group->id,
                'widget_definition_id' => $widgetData['widget_definition_id'],
                'props' => $widgetData['props'] ?? [],
                'sort_order' => $index,
                'size_class' => $widgetData['size_class'] ?? 'md',
                'style_override' => $widgetData['style_override'] ?? null,
                'is_visible' => $widgetData['is_visible'] ?? true,
            ]);

            // Create bindings for this widget
            if (isset($widgetData['bindings'])) {
                foreach ($widgetData['bindings'] as $bindingData) {
                    SensorWidgetBinding::create([
                        'widget_instance_id' => $widget->id,
                        'vehicle_sensor_id' => $bindingData['vehicle_sensor_id'],
                        'telemetry_key' => $bindingData['telemetry_key'],
                        'target_prop' => $bindingData['target_prop'] ?? 'value',
                        'slot' => $bindingData['slot'] ?? null,
                        'transform' => $bindingData['transform'] ?? null,
                        'display_label' => $bindingData['display_label'] ?? null,
                        'display_unit' => $bindingData['display_unit'] ?? null,
                        'thresholds' => $bindingData['thresholds'] ?? null,
                    ]);
                }
            }
        }
    }

    /**
     * Generate default groups based on vehicle's sensor categories
     */
    private function generateDefaultGroups(DashboardLayout $layout, Vehicle $vehicle): void
    {
        $vehicleSensors = $vehicle->vehicleSensors()->with('sensor')->get();

        // Group sensors by category
        $sensorsByCategory = $vehicleSensors->groupBy(fn($vs) => $vs->sensor->category ?? 'other');

        // Widget type mapping by category
        $categoryWidgetMap = [
            'engine' => 'radial_gauge',
            'fuel' => 'linear_bar',
            'temperature' => 'linear_bar',
            'electrical' => 'digital_value',
            'tires' => 'tire_grid',
            'transmission' => 'digital_value',
        ];

        $sortOrder = 0;

        foreach ($sensorsByCategory as $category => $sensors) {
            if ($sensors->isEmpty())
                continue;

            // Create group for this category
            $group = WidgetGroup::create([
                'dashboard_layout_id' => $layout->id,
                'name' => ucfirst($category),
                'slug' => Str::slug($category),
                'icon' => $this->getCategoryIcon($category),
                'grid_column_start' => 1,
                'grid_column_span' => $sortOrder === 0 ? 12 : 6,
                'sort_order' => $sortOrder++,
                'is_visible' => true,
            ]);

            // Get the appropriate widget type for this category
            $widgetType = $categoryWidgetMap[$category] ?? 'digital_value';
            $widgetDefinition = WidgetDefinition::where('type', $widgetType)->first();

            if (!$widgetDefinition) {
                $widgetDefinition = WidgetDefinition::first(); // Fallback
            }

            // Create widgets for each sensor
            foreach ($sensors as $index => $vehicleSensor) {
                $sensor = $vehicleSensor->sensor;

                $widget = WidgetInstance::create([
                    'widget_group_id' => $group->id,
                    'widget_definition_id' => $widgetDefinition->id,
                    'props' => [
                        'min' => $sensor->min_value ?? 0,
                        'max' => $sensor->max_value ?? 100,
                        'label' => $sensor->name,
                        'unit' => $sensor->unit ?? '',
                    ],
                    'sort_order' => $index,
                    'size_class' => 'md',
                    'is_visible' => true,
                ]);

                // Create binding
                SensorWidgetBinding::create([
                    'widget_instance_id' => $widget->id,
                    'vehicle_sensor_id' => $vehicleSensor->id,
                    'telemetry_key' => Str::snake($sensor->name),
                    'target_prop' => 'value',
                ]);
            }
        }
    }

    /**
     * Get icon for a sensor category
     */
    private function getCategoryIcon(string $category): string
    {
        return match ($category) {
            'engine' => 'gauge',
            'fuel' => 'droplets',
            'temperature' => 'thermometer',
            'electrical' => 'zap',
            'tires' => 'circle',
            'transmission' => 'settings-2',
            'diagnostics' => 'alert-triangle',
            default => 'activity',
        };
    }
}
