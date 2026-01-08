<?php

namespace Database\Seeders;

use App\Models\WidgetDefinition;
use Illuminate\Database\Seeder;

/**
 * VideoStreamWidgetSeeder
 * 
 * Adds the Video Stream widget definition to the catalog.
 * Run with: php artisan db:seed --class=VideoStreamWidgetSeeder
 */
class VideoStreamWidgetSeeder extends Seeder
{
    public function run(): void
    {
        WidgetDefinition::updateOrCreate(
            ['type' => 'video_stream'],
            [
                'name' => 'Live Camera',
                'component_name' => 'VideoStreamWidget',
                'description' => 'Stream de video en tiempo real desde MediaMTX vía WebRTC. Soporta múltiples cámaras y modo pantalla completa.',
                'icon' => 'video',
                'category' => 'special',
                'min_width' => 3,
                'min_height' => 3,
                'supports_thresholds' => false,
                'supports_multiple_slots' => false,
                'supports_animation' => false,
                'props_schema' => [
                    'streamBaseUrl' => [
                        'type' => 'string',
                        'default' => 'https://stream.neurona.xyz',
                        'label' => 'URL Base del Stream',
                    ],
                    'channelId' => [
                        'type' => 'string',
                        'default' => 'movil1',
                        'label' => 'ID del Canal',
                    ],
                    'label' => [
                        'type' => 'string',
                        'default' => 'Cámara 1',
                        'label' => 'Etiqueta',
                    ],
                    'autoplay' => [
                        'type' => 'boolean',
                        'default' => true,
                        'label' => 'Reproducción Automática',
                    ],
                ],
            ]
        );

        $this->command->info('✅ Video Stream Widget added to catalog!');
    }
}
