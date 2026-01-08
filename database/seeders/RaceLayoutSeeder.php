<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Vehicle;
use App\Models\DashboardLayout;
use App\Models\WidgetGroup;
use App\Models\WidgetInstance;
use App\Models\WidgetDefinition;
use App\Models\SensorWidgetBinding;
use App\Models\VehicleSensor;

class RaceLayoutSeeder extends Seeder
{
    /**
     * Creates the "Race Ready" Layout.
     * Hero Map (Left) + Primary Quadrant (Right) + Detailed Rows (Bottom)
     */
    public function run(): void
    {
        $vehicle = Vehicle::find(1);
        if (!$vehicle)
            return;

        // 1. Create Layout
        $layout = DashboardLayout::create([
            'vehicle_id' => $vehicle->id,
            'name' => 'Race Ready Professional',
            'theme' => 'cyberpunk-dark',
            'grid_config' => ['columns' => 12, 'gap' => 4],
            'is_active' => true,
        ]);

        // Deactivate others
        DashboardLayout::where('vehicle_id', $vehicle->id)
            ->where('id', '!=', $layout->id)
            ->update(['is_active' => false]);

        $this->command->info("Building Race Layout ID: {$layout->id}");

        // 2. Define Groups

        // Group: PRIMARY DISPLAY (The Sidebar Quadrant)
        // This will sit to the right of the Map.
        $gPrimary = WidgetGroup::create([
            'dashboard_layout_id' => $layout->id,
            'name' => 'PRIMARY DISPLAY',
            'slug' => 'primary-display',
            'grid_column_start' => 9, // Explicitly right
            'grid_column_span' => 4,
            'sort_order' => 0,
        ]);

        // Group: SYSTEM HEALTH (Bottom Full Width)
        $gSystem = WidgetGroup::create([
            'dashboard_layout_id' => $layout->id,
            'name' => 'SYSTEM HEALTH',
            'slug' => 'system-health',
            'grid_column_start' => 1,
            'grid_column_span' => 12, // Full width below
            'sort_order' => 1,
        ]);

        // 3. Add Widgets to Primary Quadrant (2x2)
        // 1. RPM (Top Left)
        $this->createWidget($gPrimary, 'radial_gauge', 0, ['label' => 'RPM', 'max' => 8000, 'endAngle' => 140, 'startAngle' => -140], 'RPM');

        // 2. SPEED (Top Right)
        $this->createWidget($gPrimary, 'radial_gauge', 1, ['label' => 'SPEED', 'max' => 120, 'unit' => 'MPH'], 'Vehicle_Speed');

        // 3. GEAR SCALE PREMIUN (Bottom Left)
        // Restoring the pro gear widget
        $this->createWidget($gPrimary, 'gear_scale', 2, ['label' => 'GEAR', 'maxGear' => 6], 'Current_Gear');

        // 4. COOLANT (Bottom Right)
        $this->createWidget($gPrimary, 'text_grid', 3, ['label' => 'COOLANT', 'items' => [['label' => 'TEMP', 'unit' => '°F', 'slot' => 'val']]], 'Coolant_Temp');


        // 4. Add Widgets to System Bottom
        $this->createWidget($gSystem, 'pressure_bar', 0, ['label' => 'OIL PRESS', 'max' => 100], 'Oil_Pressure');
        $this->createWidget($gSystem, 'pressure_bar', 1, ['label' => 'FUEL PRESS', 'max' => 80], 'Fuel_Pressure');
        $this->createWidget($gSystem, 'text_grid', 2, ['label' => 'BATTERY', 'unit' => 'V'], 'Battery_Voltage');
        $this->createWidget($gSystem, 'text_grid', 3, ['label' => 'INTAKE', 'unit' => '°F'], 'Intake_Air_Temp');

        $this->command->info('Race Layout Created!');
    }

    private function createWidget($group, $type, $order, $props, $sensorKey)
    {
        $def = WidgetDefinition::where('type', $type)->first();
        if (!$def) {
            // Fallback logic not strictly needed if DB seeded correctly
            return;
        }

        $widget = WidgetInstance::create([
            'widget_group_id' => $group->id,
            'widget_definition_id' => $def->id,
            'props' => $props,
            'sort_order' => $order,
            'size_class' => 'full',
            'is_visible' => true,
        ]);

        if ($sensorKey) {
            $sensor = VehicleSensor::whereHas('sensor', function ($q) use ($sensorKey) {
                $q->where('sensor_key', $sensorKey);
            })->first();

            if ($sensor) {
                SensorWidgetBinding::create([
                    'widget_instance_id' => $widget->id,
                    'vehicle_sensor_id' => $sensor->id,
                    'telemetry_key' => $sensorKey,
                    'target_prop' => 'value',
                ]);
            }
        }
    }
}
