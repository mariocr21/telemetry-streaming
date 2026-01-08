<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\ClientDevice;
use App\Models\Vehicle;
use App\Models\Sensor;
use App\Models\VehicleSensor;
use App\Models\DashboardLayout;
use App\Models\WidgetGroup;
use App\Models\WidgetInstance;
use App\Models\WidgetDefinition;
use App\Models\SensorWidgetBinding;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoDashboardSeeder extends Seeder
{
    /**
     * Create a demo vehicle with sensors and a complete dashboard configuration.
     * 
     * This seeder creates:
     * - 1 Client
     * - 1 Device
     * - 1 Vehicle (Ford Raptor Demo)
     * - 12 Sensors (RPM, Speed, Temps, Tires, etc.)
     * - 1 Dashboard Layout with 5 groups
     * - Multiple widgets with proper sensor bindings
     */
    public function run(): void
    {
        $this->command->info('🚀 Creating demo dashboard data...');

        // ─────────────────────────────────────────────────────────────
        // 1. CREATE CLIENT AND DEVICE
        // ─────────────────────────────────────────────────────────────
        $client = Client::firstOrCreate(
            ['email' => 'demo@neuronaoffroad.com'],
            [
                'first_name' => 'Demo',
                'last_name' => 'Racing Team',
                'phone' => '555-0100',
                'company' => 'Neurona Off Road Demo',
            ]
        );
        $this->command->info("✅ Client: {$client->first_name} {$client->last_name}");

        // Create device inventory first
        $inventory = \App\Models\DeviceInventory::firstOrCreate(
            ['serial_number' => 'DEMO-ESP32-SN001'],
            [
                'device_uuid' => 'demo-uuid-' . uniqid(),
                'model' => 'ESP32-WROOM',
                'hardware_version' => '1.0',
                'firmware_version' => '2.0.0',
                'status' => 'sold',
            ]
        );

        $device = ClientDevice::firstOrCreate(
            ['client_id' => $client->id, 'device_inventory_id' => $inventory->id],
            [
                'device_name' => 'Demo ESP32 Dashboard',
                'status' => 'active',
            ]
        );
        $this->command->info("✅ Device ID: {$device->id}");

        // ─────────────────────────────────────────────────────────────
        // 2. CREATE VEHICLE
        // ─────────────────────────────────────────────────────────────
        $vehicle = Vehicle::firstOrCreate(
            ['vin' => 'DEMO1234567890VIN'],
            [
                'client_id' => $client->id,
                'client_device_id' => $device->id,
                'make' => 'Ford',
                'model' => 'F-150 Raptor',
                'year' => 2024,
                'nickname' => 'Baja Beast',
                'license_plate' => 'DEMO-001',
                'color' => 'Oxford White',
                'is_configured' => true,
                'status' => true,
            ]
        );
        $this->command->info("✅ Vehicle: {$vehicle->nickname} ({$vehicle->make} {$vehicle->model})");

        // ─────────────────────────────────────────────────────────────
        // 3. CREATE SENSORS
        // ─────────────────────────────────────────────────────────────
        $sensorsData = [
            ['pid' => '0x0C', 'name' => 'RPM', 'category' => 'engine', 'unit' => 'RPM', 'min_value' => 0, 'max_value' => 9000],
            ['pid' => '0x0D', 'name' => 'Vehicle Speed', 'category' => 'engine', 'unit' => 'MPH', 'min_value' => 0, 'max_value' => 200],
            ['pid' => '0x11', 'name' => 'Throttle Position', 'category' => 'engine', 'unit' => '%', 'min_value' => 0, 'max_value' => 100],
            ['pid' => '0x05', 'name' => 'Coolant Temp', 'category' => 'temperature', 'unit' => '°F', 'min_value' => 0, 'max_value' => 300],
            ['pid' => '0x5C', 'name' => 'Oil Temperature', 'category' => 'temperature', 'unit' => '°F', 'min_value' => 0, 'max_value' => 300],
            ['pid' => 'CAN_TRANS_TEMP', 'name' => 'Transmission Temp', 'category' => 'temperature', 'unit' => '°F', 'min_value' => 0, 'max_value' => 300],
            ['pid' => '0x0F', 'name' => 'Intake Air Temp', 'category' => 'temperature', 'unit' => '°F', 'min_value' => 0, 'max_value' => 200],
            ['pid' => 'CAN_FUEL_PRESS', 'name' => 'Fuel Pressure', 'category' => 'fuel', 'unit' => 'PSI', 'min_value' => 0, 'max_value' => 80],
            ['pid' => 'CAN_VOLTAGE', 'name' => 'Battery Voltage', 'category' => 'electrical', 'unit' => 'V', 'min_value' => 10, 'max_value' => 16],
            ['pid' => 'CAN_CURRENT', 'name' => 'Alternator Current', 'category' => 'electrical', 'unit' => 'A', 'min_value' => 0, 'max_value' => 100],
            ['pid' => 'CAN_GEAR', 'name' => 'Current Gear', 'category' => 'transmission', 'unit' => '', 'min_value' => 0, 'max_value' => 10],
        ];

        $vehicleSensors = [];
        foreach ($sensorsData as $sensorData) {
            $sensor = Sensor::firstOrCreate(
                ['pid' => $sensorData['pid']],
                $sensorData
            );

            $vs = VehicleSensor::firstOrCreate(
                ['vehicle_id' => $vehicle->id, 'sensor_id' => $sensor->id],
                ['is_active' => true, 'frequency_seconds' => 1]
            );

            $vehicleSensors[$sensor->name] = $vs;
        }
        $this->command->info("✅ Created " . count($vehicleSensors) . " sensors");

        // ─────────────────────────────────────────────────────────────
        // 4. CREATE DASHBOARD LAYOUT
        // ─────────────────────────────────────────────────────────────

        // Delete existing layout for this vehicle
        DashboardLayout::where('vehicle_id', $vehicle->id)->delete();

        $layout = DashboardLayout::create([
            'vehicle_id' => $vehicle->id,
            'name' => 'Baja Race Dashboard',
            'theme' => 'cyberpunk-dark',
            'grid_config' => [
                'columns' => 12,
                'gap' => 4,
                'breakpoints' => [
                    'lg' => ['columns' => 12],
                    'md' => ['columns' => 6],
                    'sm' => ['columns' => 1],
                ],
            ],
            'is_active' => true,
        ]);
        $this->command->info("✅ Dashboard Layout: {$layout->name}");

        // Get widget definitions
        $radialGauge = WidgetDefinition::where('type', 'radial_gauge')->first();
        $linearBar = WidgetDefinition::where('type', 'linear_bar')->first();
        $digitalValue = WidgetDefinition::where('type', 'digital_value')->first();
        $textGrid = WidgetDefinition::where('type', 'text_grid')->first();

        // ─────────────────────────────────────────────────────────────
        // 5. CREATE GROUPS AND WIDGETS
        // ─────────────────────────────────────────────────────────────

        // GROUP 1: Engine Performance (RPM, Speed, TPS)
        $engineGroup = WidgetGroup::create([
            'dashboard_layout_id' => $layout->id,
            'name' => 'Engine Performance',
            'slug' => 'engine-performance',
            'icon' => 'gauge',
            'grid_column_start' => 1,
            'grid_column_span' => 12,
            'sort_order' => 0,
            'style_config' => ['bgColor' => 'bg-dash-card', 'borderColor' => 'border-slate-700'],
        ]);

        // RPM Widget
        $rpmWidget = WidgetInstance::create([
            'widget_group_id' => $engineGroup->id,
            'widget_definition_id' => $radialGauge->id,
            'props' => [
                'min' => 0,
                'max' => 9000,
                'label' => 'RPM',
                'unit' => '',
                'thresholds' => [
                    ['value' => 60, 'color' => '#00ff9d'],
                    ['value' => 85, 'color' => '#ff8a00'],
                    ['value' => 100, 'color' => '#ff003c'],
                ],
                'arcWidth' => 12,
                'animated' => true,
            ],
            'size_class' => 'lg',
            'sort_order' => 0,
        ]);
        SensorWidgetBinding::create([
            'widget_instance_id' => $rpmWidget->id,
            'vehicle_sensor_id' => $vehicleSensors['RPM']->id,
            'telemetry_key' => 'RPM',
            'target_prop' => 'value',
        ]);

        // Speed Widget
        $speedWidget = WidgetInstance::create([
            'widget_group_id' => $engineGroup->id,
            'widget_definition_id' => $radialGauge->id,
            'props' => [
                'min' => 0,
                'max' => 200,
                'label' => 'SPEED',
                'unit' => 'MPH',
                'thresholds' => [
                    ['value' => 50, 'color' => '#00ff9d'],
                    ['value' => 80, 'color' => '#ff8a00'],
                    ['value' => 100, 'color' => '#ff003c'],
                ],
                'arcWidth' => 12,
                'animated' => true,
            ],
            'size_class' => 'lg',
            'sort_order' => 1,
        ]);
        SensorWidgetBinding::create([
            'widget_instance_id' => $speedWidget->id,
            'vehicle_sensor_id' => $vehicleSensors['Vehicle Speed']->id,
            'telemetry_key' => 'Vehicle_Speed',
            'target_prop' => 'value',
        ]);

        // TPS Widget (as linear bar)
        $tpsWidget = WidgetInstance::create([
            'widget_group_id' => $engineGroup->id,
            'widget_definition_id' => $linearBar->id,
            'props' => [
                'min' => 0,
                'max' => 100,
                'label' => 'TPS',
                'unit' => '%',
                'colorScheme' => 'success',
            ],
            'size_class' => 'md',
            'sort_order' => 2,
        ]);
        SensorWidgetBinding::create([
            'widget_instance_id' => $tpsWidget->id,
            'vehicle_sensor_id' => $vehicleSensors['Throttle Position']->id,
            'telemetry_key' => 'Throttle_Position',
            'target_prop' => 'value',
        ]);

        // GROUP 2: Gear Indicator
        $gearGroup = WidgetGroup::create([
            'dashboard_layout_id' => $layout->id,
            'name' => 'Gear',
            'slug' => 'gear',
            'icon' => 'settings-2',
            'grid_column_start' => 1,
            'grid_column_span' => 4,
            'sort_order' => 1,
            'style_config' => ['bgColor' => 'bg-dash-success', 'borderColor' => 'border-green-600', 'variant' => 'highlight'],
        ]);

        $gearWidget = WidgetInstance::create([
            'widget_group_id' => $gearGroup->id,
            'widget_definition_id' => $digitalValue->id,
            'props' => [
                'label' => 'Gear',
                'fontSize' => '6xl',
                'fontWeight' => 'black',
                'textColor' => 'white',
                'fallbackValue' => 'N',
            ],
            'size_class' => 'xl',
            'sort_order' => 0,
        ]);
        SensorWidgetBinding::create([
            'widget_instance_id' => $gearWidget->id,
            'vehicle_sensor_id' => $vehicleSensors['Current Gear']->id,
            'telemetry_key' => 'Current_Gear',
            'target_prop' => 'value',
        ]);

        // GROUP 3: Oil & Fuel
        $oilFuelGroup = WidgetGroup::create([
            'dashboard_layout_id' => $layout->id,
            'name' => 'Oil & Fuel',
            'slug' => 'oil-fuel',
            'icon' => 'droplets',
            'grid_column_start' => 5,
            'grid_column_span' => 8,
            'sort_order' => 2,
        ]);

        // Oil Temp Bar
        $oilTempWidget = WidgetInstance::create([
            'widget_group_id' => $oilFuelGroup->id,
            'widget_definition_id' => $linearBar->id,
            'props' => [
                'min' => 0,
                'max' => 300,
                'label' => 'Oil Temp',
                'unit' => '°F',
                'colorScheme' => 'temperature',
                'thresholds' => ['warning' => 220, 'critical' => 260],
            ],
            'size_class' => 'full',
            'sort_order' => 0,
        ]);
        SensorWidgetBinding::create([
            'widget_instance_id' => $oilTempWidget->id,
            'vehicle_sensor_id' => $vehicleSensors['Oil Temperature']->id,
            'telemetry_key' => 'Oil_Temperature',
            'target_prop' => 'value',
        ]);

        // Fuel Pressure Bar
        $fuelPressWidget = WidgetInstance::create([
            'widget_group_id' => $oilFuelGroup->id,
            'widget_definition_id' => $linearBar->id,
            'props' => [
                'min' => 0,
                'max' => 80,
                'label' => 'Fuel Press',
                'unit' => 'PSI',
                'colorScheme' => 'pressure',
            ],
            'size_class' => 'full',
            'sort_order' => 1,
        ]);
        SensorWidgetBinding::create([
            'widget_instance_id' => $fuelPressWidget->id,
            'vehicle_sensor_id' => $vehicleSensors['Fuel Pressure']->id,
            'telemetry_key' => 'Fuel_Pressure',
            'target_prop' => 'value',
        ]);

        // GROUP 4: Temperatures (Text Grid)
        $tempsGroup = WidgetGroup::create([
            'dashboard_layout_id' => $layout->id,
            'name' => 'Temperatures',
            'slug' => 'temperatures',
            'icon' => 'thermometer',
            'grid_column_start' => 1,
            'grid_column_span' => 6,
            'sort_order' => 3,
        ]);

        $tempsWidget = WidgetInstance::create([
            'widget_group_id' => $tempsGroup->id,
            'widget_definition_id' => $textGrid->id,
            'props' => [
                'columns' => 4,
                'gap' => 2,
                'items' => [
                    ['label' => 'Coolant', 'slot' => 'coolant', 'unit' => '°'],
                    ['label' => 'Oil', 'slot' => 'oil', 'unit' => '°'],
                    ['label' => 'Trans', 'slot' => 'trans', 'unit' => '°'],
                    ['label' => 'Intake', 'slot' => 'intake', 'unit' => '°'],
                ],
            ],
            'size_class' => 'full',
            'sort_order' => 0,
        ]);

        // Bind all 4 temperature sensors to slots
        SensorWidgetBinding::create([
            'widget_instance_id' => $tempsWidget->id,
            'vehicle_sensor_id' => $vehicleSensors['Coolant Temp']->id,
            'telemetry_key' => 'Coolant_Temp',
            'target_prop' => 'value',
            'slot' => 'coolant',
        ]);
        SensorWidgetBinding::create([
            'widget_instance_id' => $tempsWidget->id,
            'vehicle_sensor_id' => $vehicleSensors['Oil Temperature']->id,
            'telemetry_key' => 'Oil_Temperature',
            'target_prop' => 'value',
            'slot' => 'oil',
        ]);
        SensorWidgetBinding::create([
            'widget_instance_id' => $tempsWidget->id,
            'vehicle_sensor_id' => $vehicleSensors['Transmission Temp']->id,
            'telemetry_key' => 'Transmission_Temp',
            'target_prop' => 'value',
            'slot' => 'trans',
        ]);
        SensorWidgetBinding::create([
            'widget_instance_id' => $tempsWidget->id,
            'vehicle_sensor_id' => $vehicleSensors['Intake Air Temp']->id,
            'telemetry_key' => 'Intake_Air_Temp',
            'target_prop' => 'value',
            'slot' => 'intake',
        ]);

        // GROUP 5: Electrical
        $electricalGroup = WidgetGroup::create([
            'dashboard_layout_id' => $layout->id,
            'name' => 'Electrical',
            'slug' => 'electrical',
            'icon' => 'zap',
            'grid_column_start' => 7,
            'grid_column_span' => 6,
            'sort_order' => 4,
        ]);

        $electricalWidget = WidgetInstance::create([
            'widget_group_id' => $electricalGroup->id,
            'widget_definition_id' => $textGrid->id,
            'props' => [
                'columns' => 2,
                'gap' => 4,
                'items' => [
                    ['label' => 'Battery', 'slot' => 'voltage', 'unit' => 'V', 'color' => 'yellow-400'],
                    ['label' => 'Current', 'slot' => 'current', 'unit' => 'A', 'color' => 'yellow-400'],
                ],
            ],
            'size_class' => 'full',
            'sort_order' => 0,
        ]);

        SensorWidgetBinding::create([
            'widget_instance_id' => $electricalWidget->id,
            'vehicle_sensor_id' => $vehicleSensors['Battery Voltage']->id,
            'telemetry_key' => 'Battery_Voltage',
            'target_prop' => 'value',
            'slot' => 'voltage',
            'transform' => ['round' => 1],
        ]);
        SensorWidgetBinding::create([
            'widget_instance_id' => $electricalWidget->id,
            'vehicle_sensor_id' => $vehicleSensors['Alternator Current']->id,
            'telemetry_key' => 'Alternator_Current',
            'target_prop' => 'value',
            'slot' => 'current',
            'transform' => ['round' => 1],
        ]);

        // ─────────────────────────────────────────────────────────────
        // SUMMARY
        // ─────────────────────────────────────────────────────────────
        $this->command->newLine();
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('✅ DEMO DASHBOARD CREATED SUCCESSFULLY');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info("Vehicle ID: {$vehicle->id}");
        $this->command->info("Layout ID: {$layout->id}");
        $this->command->info("Groups: 5 (Engine, Gear, Oil/Fuel, Temps, Electrical)");
        $this->command->info("Widgets: " . WidgetInstance::whereHas('group', fn($q) => $q->where('dashboard_layout_id', $layout->id))->count());
        $this->command->info("Bindings: " . SensorWidgetBinding::whereHas('widgetInstance.group', fn($q) => $q->where('dashboard_layout_id', $layout->id))->count());
        $this->command->newLine();
        $this->command->info("🌐 Test endpoint: GET http://localhost:8000/api/vehicles/{$vehicle->id}/dashboard");
    }
}
