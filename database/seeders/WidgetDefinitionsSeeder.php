<?php

namespace Database\Seeders;

use App\Models\WidgetDefinition;
use Illuminate\Database\Seeder;

class WidgetDefinitionsSeeder extends Seeder
{
    /**
     * Seed the widget definitions catalog - PREMIUM EDITION.
     */
    public function run(): void
    {
        $widgets = [
            // ─────────────────────────────────────────────────────────────
            // CORE RACING WIDGETS
            // ─────────────────────────────────────────────────────────────
            [
                'type' => 'radial_gauge',
                'name' => 'Pro Racing Gauge',
                'component_name' => 'RadialGaugeD3',
                'description' => 'Tacómetro de alta precisión estilo "StartStream". Soporta zonas de color dinámicas y alta velocidad de refresco.',
                'icon' => 'gauge',
                'category' => 'visualization',
                'min_width' => 2,
                'min_height' => 2,
                'supports_thresholds' => true,
                'supports_multiple_slots' => false,
                'supports_animation' => true,
                'props_schema' => [
                    'label' => ['type' => 'string', 'default' => 'RPM', 'label' => 'Etiqueta'],
                    'min' => ['type' => 'number', 'default' => 0, 'label' => 'Mínimo'],
                    'max' => ['type' => 'number', 'default' => 8000, 'label' => 'Máximo'],
                    'unit' => ['type' => 'string', 'default' => 'RPM', 'label' => 'Unidad'],
                    'startAngle' => ['type' => 'number', 'default' => -140, 'label' => 'Ángulo Inicio'],
                    'endAngle' => ['type' => 'number', 'default' => 140, 'label' => 'Ángulo Fin'],
                ],
            ],
            [
                'type' => 'linear_bar',
                'name' => 'Precision Bar',
                'component_name' => 'LinearBarD3', // Or PressureBarWidget if preferred
                'description' => 'Barra horizontal de precisión para presiones y niveles. Diseño compacto.',
                'icon' => 'minus',
                'category' => 'visualization',
                'min_width' => 3,
                'min_height' => 1,
                'supports_thresholds' => true,
                'supports_multiple_slots' => false,
                'props_schema' => [
                    'label' => ['type' => 'string', 'default' => 'OIL PRESS', 'label' => 'Etiqueta'],
                    'min' => ['type' => 'number', 'default' => 0],
                    'max' => ['type' => 'number', 'default' => 100],
                    'unit' => ['type' => 'string', 'default' => 'PSI'],
                ],
            ],

            // ─────────────────────────────────────────────────────────────
            // DATA BOXES (THE BENTO BLOCKS)
            // ─────────────────────────────────────────────────────────────
            [
                'type' => 'text_grid',
                'name' => 'Smart Data Box',
                'component_name' => 'TextGridWidget',
                'description' => 'Caja de datos inteligente. Úsalo para 1 valor (Gigante) o arrastra múltiples sensores para crear una grilla automática.',
                'icon' => 'layout-grid',
                'category' => 'text',
                'min_width' => 2,
                'min_height' => 1,
                'supports_thresholds' => true,
                'supports_multiple_slots' => true, // KEY: Allows dragging multiple sensors
                'supports_animation' => false,
                'props_schema' => [
                    'label' => ['type' => 'string', 'default' => 'DATA', 'label' => 'Etiqueta Principal'],
                ],
            ],
            [
                'type' => 'gear_scale',
                'name' => 'Gear Scale (Linear)',
                'component_name' => 'GearScaleWidget',
                'description' => 'Visualización lineal de la transmisión. Muestra marcha actual y contexto de marchas disponibles.',
                'icon' => 'list-end',
                'category' => 'transmission',
                'min_width' => 3,
                'min_height' => 1,
                'supports_thresholds' => false,
                'supports_multiple_slots' => false,
                'props_schema' => [
                    'label' => ['type' => 'string', 'default' => 'GEAR'],
                    'maxGear' => ['type' => 'number', 'default' => 6],
                ],
            ],

            // ─────────────────────────────────────────────────────────────
            // SPECIALTY
            // ─────────────────────────────────────────────────────────────
            [
                'type' => 'tire_grid',
                'name' => 'Tire Monitor System',
                'component_name' => 'TireGridWidget',
                'description' => 'Monitoreo completo de llantas (Presión + Temperatura) en layout 2x2.',
                'icon' => 'circle',
                'category' => 'special',
                'min_width' => 4,
                'min_height' => 2,
                'supports_thresholds' => true,
                'supports_multiple_slots' => true,
                'props_schema' => [
                    'pressureUnit' => ['type' => 'string', 'default' => 'PSI'],
                    'tempUnit' => ['type' => 'string', 'default' => '°F'],
                ],
            ],
            [
                'type' => 'fuel_gauge',
                'name' => 'Fuel Level (Circle)',
                'component_name' => 'FuelGaugeWidget',
                'description' => 'Indicador circular de nivel de combustible con alerta de reserva.',
                'icon' => 'droplet',
                'category' => 'special',
                'min_width' => 2,
                'min_height' => 2,
                'supports_thresholds' => true,
                'props_schema' => [
                    'label' => ['type' => 'string', 'default' => 'FUEL'],
                ],
            ],
            [
                'type' => 'gps_info',
                'name' => 'GPS Data Block',
                'component_name' => 'GPSInfoWidget',
                'description' => 'Bloque de información GPS (Lat/Long/Satélites).',
                'icon' => 'map-pin',
                'category' => 'special',
                'min_width' => 2,
                'min_height' => 1,
                'supports_multiple_slots' => true,
                'props_schema' => [],
            ],
            [
                'type' => 'map_widget',
                'name' => 'GPS Live Map',
                'component_name' => 'MapWidget',
                'description' => 'Mapa de ruta en tiempo real con track de posición.',
                'icon' => 'map',
                'category' => 'visualization',
                'min_width' => 4,
                'min_height' => 4,
                'supports_thresholds' => false,
                'supports_multiple_slots' => false,
                'props_schema' => [
                    'zoom' => ['type' => 'number', 'default' => 15],
                    'showTrack' => ['type' => 'boolean', 'default' => true],
                ],
            ],
        ];

        foreach ($widgets as $widget) {
            WidgetDefinition::updateOrCreate(
                ['type' => $widget['type']],
                array_merge($widget, [
                    'props_schema' => $widget['props_schema'], // Store as array, cast handles serialization
                ])
            );
        }

        $this->command->info('✅ Premium Widget Catalog Updated!');
    }
}
