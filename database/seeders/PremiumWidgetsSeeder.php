<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PremiumWidgetsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Register GearScaleWidget
        \App\Models\WidgetDefinition::updateOrCreate(
            ['type' => 'gear_scale'],
            [
                'name' => 'Gear Scale (Linear)',
                'icon' => 'list-end', // Lucide icon name (approximation)
                'component_name' => 'GearScaleWidget',
                'category' => 'transmission',
                'description' => 'Muestra marcha actual y escala con contexto',
                'supports_thresholds' => false,
                'supports_multiple_slots' => false,
                'props_schema' => [
                    'label' => ['type' => 'string', 'default' => 'GEAR'],
                    'maxGear' => ['type' => 'number', 'default' => 6],
                    'showReverse' => ['type' => 'boolean', 'default' => true],
                ],
            ]
        );

        // 2. Update RadialGauge defaults to match new segmented style
        \App\Models\WidgetDefinition::updateOrCreate(
            ['type' => 'radial_gauge'],
            [
                'name' => 'Pro Radial Gauge',
                'description' => 'Tacómetro estilo Neurona con segmentos de zona',
                'component_name' => 'RadialGaugeD3',
                'category' => 'gauges',
                'props_schema' => [
                    'label' => ['type' => 'string', 'default' => 'RPM'],
                    'min' => ['type' => 'number', 'default' => 0],
                    'max' => ['type' => 'number', 'default' => 10000],
                    'unit' => ['type' => 'string', 'default' => 'RPM'],
                    'startAngle' => ['type' => 'number', 'default' => -140],
                    'endAngle' => ['type' => 'number', 'default' => 140],
                ]
            ]
        );

        $this->command->info('Premium widgets registered successfully.');
    }
}
