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

class StarstreamLayoutSeeder extends Seeder
{
    /**
     * Creates a 'Starstream Clone' layout for Vehicle 1.
     * Meticulously recreating the reference image structure.
     */
    public function run(): void
    {
        $vehicle = Vehicle::find(1);
        if (!$vehicle)
            return;

        // 1. Create Layout
        $layout = DashboardLayout::create([
            'vehicle_id' => $vehicle->id,
            'name' => 'StarStream Pro Clone',
            'theme' => 'cyberpunk-dark',
            'grid_config' => ['columns' => 12, 'gap' => 4], // 12 column grid
            'is_active' => true,
        ]);

        // Deactivate others
        DashboardLayout::where('vehicle_id', $vehicle->id)
            ->where('id', '!=', $layout->id)
            ->update(['is_active' => false]);

        $this->command->info("Building StarStream Layout ID: {$layout->id}");

        // 2. Define Groups (The 'Bento Boxes')

        // Group 1: ENGINE PERFORMANCE (Top Left, 3 gauges wide)
        $gEnPer = WidgetGroup::create([
            'dashboard_layout_id' => $layout->id,
            'name' => 'ENGINE PERFORMANCE',
            'slug' => 'engine-perf',
            'grid_column_start' => 1,
            'grid_column_span' => 6, // Half width
            'sort_order' => 0,
        ]);

        // Group 2: TEMPERATURES (Top Right)
        $gTemps = WidgetGroup::create([
            'dashboard_layout_id' => $layout->id,
            'name' => 'TEMPERATURES (°F)',
            'slug' => 'temperatures',
            'grid_column_start' => 7,
            'grid_column_span' => 6, // Half width
            'sort_order' => 1,
        ]);

        // Group 3: GEAR + FUEL (Middle Left, small box)
        $gGear = WidgetGroup::create([
            'dashboard_layout_id' => $layout->id,
            'name' => 'TRANSMISSION',
            'slug' => 'gear',
            'grid_column_start' => 1,
            'grid_column_span' => 3,
            'sort_order' => 2,
        ]);

        // Group 4: PRESSURES (Middle Center)
        $gPress = WidgetGroup::create([
            'dashboard_layout_id' => $layout->id,
            'name' => 'PRESSURES (PSI)',
            'slug' => 'pressures',
            'grid_column_start' => 4,
            'grid_column_span' => 4,
            'sort_order' => 3,
        ]);

        // Group 5: ELECTRICAL (Middle Right)
        $gElec = WidgetGroup::create([
            'dashboard_layout_id' => $layout->id,
            'name' => 'ELECTRICAL',
            'slug' => 'electrical',
            'grid_column_start' => 8,
            'grid_column_span' => 5, // Fills rest
            'sort_order' => 4,
        ]);

        // Group 6: TIRES (Bottom Left)
        $gTires = WidgetGroup::create([
            'dashboard_layout_id' => $layout->id,
            'name' => 'TIRES',
            'slug' => 'tires',
            'grid_column_start' => 1,
            'grid_column_span' => 4,
            'sort_order' => 5,
        ]);

        // Group 7: FUEL (Bottom Center)
        $gFuel = WidgetGroup::create([
            'dashboard_layout_id' => $layout->id,
            'name' => 'FUEL (GALLONS)',
            'slug' => 'fuel-level',
            'grid_column_start' => 5,
            'grid_column_span' => 4,
            'sort_order' => 6,
        ]);

        // Group 8: GPS (Bottom Right)
        $gGPS = WidgetGroup::create([
            'dashboard_layout_id' => $layout->id,
            'name' => 'GPS',
            'slug' => 'gps',
            'grid_column_start' => 9,
            'grid_column_span' => 4,
            'sort_order' => 7,
        ]);


        // 3. Add Widgets to Groups

        // --- Engine Perf (3 Radial Gauges) ---
        $this->createWidget($gEnPer, 'radial_gauge', 0, ['label' => 'RPM', 'max' => 8000, 'endAngle' => 140, 'startAngle' => -140], 'RPM');
        $this->createWidget($gEnPer, 'radial_gauge', 1, ['label' => 'SPEED', 'max' => 120, 'unit' => 'MPH', 'endAngle' => 140, 'startAngle' => -140], 'Vehicle_Speed');
        $this->createWidget($gEnPer, 'radial_gauge', 2, ['label' => 'THROTTLE', 'max' => 100, 'unit' => '%', 'endAngle' => 140, 'startAngle' => -140], 'Throttle_Position');

        // --- Temps (4 simple boxes) ---
        // Using TextGridWidget as single boxes for layout control
        $this->createWidget($gTemps, 'text_grid', 0, ['label' => 'COOLANT', 'unit' => '°F'], 'Coolant_Temp');
        $this->createWidget($gTemps, 'text_grid', 1, ['label' => 'OIL', 'unit' => '°F'], 'Oil_Temperature');
        $this->createWidget($gTemps, 'text_grid', 2, ['label' => 'TRANS', 'unit' => '°F'], 'Transmission_Temp');
        $this->createWidget($gTemps, 'text_grid', 3, ['label' => 'INTAKE', 'unit' => '°F'], 'Intake_Air_Temp');

        // --- Gear (Gear Scale + Fuel Circle) ---
        $this->createWidget($gGear, 'gear_scale', 0, ['label' => 'GEAR', 'maxGear' => 6], 'Current_Gear');
        $this->createWidget($gGear, 'fuel_gauge', 1, ['label' => 'FUEL'], 'Fuel_Level'); // Assuming we have this, or simulate

        // --- Pressures (Linear Bars) ---
        $this->createWidget($gPress, 'pressure_bar', 0, ['label' => 'OIL', 'max' => 100, 'unit' => 'PSI'], 'Oil_Pressure');
        $this->createWidget($gPress, 'pressure_bar', 1, ['label' => 'FUEL', 'max' => 80, 'unit' => 'PSI'], 'Fuel_Pressure');

        // --- Electrical (2 boxes) ---
        $this->createWidget($gElec, 'text_grid', 0, ['label' => 'BATTERY', 'unit' => 'V'], 'Battery_Voltage');
        $this->createWidget($gElec, 'text_grid', 1, ['label' => 'CURRENT', 'unit' => 'A'], 'Alternator_Current');

        // --- Tires (Tire Grid) ---
        // Tire grid is a single widget that handles layout internally
        $this->createWidget($gTires, 'tire_grid', 0, [], null); // Slots handled in creating layouts usually, but binding logic handles mapping

        // --- GPS (Boxes) ---
        $this->createWidget($gGPS, 'text_grid', 0, ['label' => 'LATITUDE'], 'GPS_Latitude');
        $this->createWidget($gGPS, 'text_grid', 1, ['label' => 'LONGITUDE'], 'GPS_Longitude');
        $this->createWidget($gGPS, 'text_grid', 2, ['label' => 'SATELLITES'], 'GPS_Satellites');

        $this->command->info('Layout Created Successfully!');
    }

    private function createWidget($group, $type, $order, $props, $sensorKey)
    {
        $def = WidgetDefinition::where('type', $type)->first();
        if (!$def)
            return;

        $widget = WidgetInstance::create([
            'widget_group_id' => $group->id,
            'widget_definition_id' => $def->id,
            'props' => $props,
            'sort_order' => $order,
            'size_class' => 'full', // Always fill
            'is_visible' => true,
        ]);

        if ($sensorKey) {
            // Find sensor
            $sensor = VehicleSensor::whereHas('sensor', function ($q) use ($sensorKey) {
                $q->where('sensor_key', $sensorKey);
            })->first();

            if ($sensor) {
                SensorWidgetBinding::create([
                    'widget_instance_id' => $widget->id,
                    'vehicle_sensor_id' => $sensor->id,
                    'telemetry_key' => $sensorKey,
                    'target_prop' => 'value', // Default
                ]);
            }
        }
    }
}
