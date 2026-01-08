<?php

namespace Database\Seeders;

use App\Models\WidgetDefinition;
use Illuminate\Database\Seeder;

/**
 * Seeds new specialized widget definitions for the Neurona dashboard.
 * Run with: php artisan db:seed --class=NewWidgetDefinitionsSeeder
 */
class NewWidgetDefinitionsSeeder extends Seeder
{
    public function run(): void
    {
        $widgets = [
            // Temperature Card - Individual temperature display
            [
                'type' => 'temperature_card',
                'name' => 'Temperature Card',
                'component_name' => 'TemperatureCardWidget',
                'description' => 'Colored card showing individual temperature value with zone-based colors (cold, optimal, warm, hot, critical)',
                'icon' => 'thermometer',
                'category' => 'temperature',
                'props_schema' => [
                    'label' => ['type' => 'string', 'required' => true, 'label' => 'Label', 'default' => 'TEMP'],
                    'unit' => ['type' => 'string', 'default' => '°F', 'label' => 'Unit'],
                    'min' => ['type' => 'number', 'default' => 0, 'label' => 'Min Value'],
                    'max' => ['type' => 'number', 'default' => 300, 'label' => 'Max Value'],
                    'coldThreshold' => ['type' => 'number', 'default' => 120, 'label' => 'Cold Threshold'],
                    'optimalThreshold' => ['type' => 'number', 'default' => 200, 'label' => 'Optimal Threshold'],
                    'warmThreshold' => ['type' => 'number', 'default' => 220, 'label' => 'Warm Threshold'],
                    'hotThreshold' => ['type' => 'number', 'default' => 250, 'label' => 'Hot Threshold'],
                ],
                'min_width' => 80,
                'min_height' => 80,
                'supports_thresholds' => true,
                'supports_multiple_slots' => false,
                'supports_animation' => true,
                'is_active' => true,
            ],

            // Fuel Gauge - Circular fuel level
            [
                'type' => 'fuel_gauge',
                'name' => 'Fuel Gauge',
                'component_name' => 'FuelGaugeWidget',
                'description' => 'Circular gauge showing fuel level percentage with icon and color-coded status',
                'icon' => 'fuel',
                'category' => 'fuel',
                'props_schema' => [
                    'label' => ['type' => 'string', 'default' => 'FUEL', 'label' => 'Label'],
                    'unit' => ['type' => 'select', 'options' => ['%', 'gal', 'L'], 'default' => '%', 'label' => 'Unit'],
                    'max' => ['type' => 'number', 'default' => 100, 'label' => 'Max Capacity'],
                    'lowThreshold' => ['type' => 'number', 'default' => 25, 'label' => 'Low Warning (%)'],
                    'criticalThreshold' => ['type' => 'number', 'default' => 10, 'label' => 'Critical Warning (%)'],
                ],
                'min_width' => 100,
                'min_height' => 120,
                'supports_thresholds' => true,
                'supports_multiple_slots' => false,
                'supports_animation' => true,
                'is_active' => true,
            ],

            // GPS Info - Coordinates/satellites display
            [
                'type' => 'gps_info',
                'name' => 'GPS Info',
                'component_name' => 'GPSInfoWidget',
                'description' => 'Display GPS data like latitude, longitude, satellites, heading',
                'icon' => 'map-pin',
                'category' => 'gps',
                'props_schema' => [
                    'label' => ['type' => 'string', 'required' => true, 'label' => 'Label', 'default' => 'GPS'],
                    'unit' => ['type' => 'string', 'default' => '', 'label' => 'Unit'],
                    'precision' => ['type' => 'number', 'default' => 6, 'label' => 'Decimal Precision'],
                    'type' => ['type' => 'select', 'options' => ['latitude', 'longitude', 'satellites', 'speed', 'heading', 'altitude', 'default'], 'default' => 'default', 'label' => 'Data Type'],
                ],
                'min_width' => 80,
                'min_height' => 60,
                'supports_thresholds' => false,
                'supports_multiple_slots' => false,
                'supports_animation' => true,
                'is_active' => true,
            ],

            // Pressure Bar - Horizontal bar for pressures
            [
                'type' => 'pressure_bar',
                'name' => 'Pressure Bar',
                'component_name' => 'PressureBarWidget',
                'description' => 'Horizontal bar showing pressure value with label, used for Oil PSI, Fuel PSI, etc.',
                'icon' => 'gauge',
                'category' => 'pressure',
                'props_schema' => [
                    'label' => ['type' => 'string', 'required' => true, 'label' => 'Label', 'default' => 'PRESSURE'],
                    'unit' => ['type' => 'string', 'default' => 'PSI', 'label' => 'Unit'],
                    'min' => ['type' => 'number', 'default' => 0, 'label' => 'Min Value'],
                    'max' => ['type' => 'number', 'default' => 100, 'label' => 'Max Value'],
                    'lowThreshold' => ['type' => 'number', 'default' => 20, 'label' => 'Low Threshold'],
                    'highThreshold' => ['type' => 'number', 'default' => 80, 'label' => 'High Threshold'],
                    'criticalThreshold' => ['type' => 'number', 'default' => 95, 'label' => 'Critical Threshold'],
                    'showBar' => ['type' => 'boolean', 'default' => true, 'label' => 'Show Bar'],
                    'color' => ['type' => 'string', 'default' => '', 'label' => 'Custom Color (hex)'],
                ],
                'min_width' => 120,
                'min_height' => 50,
                'supports_thresholds' => true,
                'supports_multiple_slots' => false,
                'supports_animation' => true,
                'is_active' => true,
            ],
        ];

        foreach ($widgets as $widget) {
            WidgetDefinition::updateOrCreate(
                ['type' => $widget['type']],
                $widget
            );
        }

        $this->command->info('✅ New widget definitions seeded successfully!');
    }
}
