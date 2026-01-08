<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Widget Definitions: Catálogo maestro de tipos de widgets disponibles.
     * Esta tabla actúa como un "menú de opciones" para elegir qué widget usar.
     */
    public function up(): void
    {
        Schema::create('widget_definitions', function (Blueprint $table) {
            $table->id();

            // Identificación del tipo de widget
            $table->string('type')->unique();           // "radial_gauge", "linear_bar", "text_grid"
            $table->string('name');                     // "Tacómetro D3", "Barra Lineal"
            $table->string('component_name');           // Nombre del componente Vue: "RadialGaugeD3"
            $table->text('description')->nullable();    // Descripción para el admin

            // Icono para mostrar en el selector (Lucide icon name)
            $table->string('icon')->nullable();         // "gauge", "bar-chart", "grid"

            // Schema JSON de props disponibles (para validación y UI de configuración)
            $table->json('props_schema')->nullable();
            // Ej: {"min": {"type": "number", "default": 0}, "max": {"type": "number", "required": true}}

            // Categoría para agrupar en la UI de configuración
            $table->string('category')->default('visualization'); // visualization, text, special

            // Tamaño mínimo recomendado (en unidades del grid)
            $table->integer('min_width')->default(1);   // Columnas mínimas
            $table->integer('min_height')->default(1);  // Rows mínimas

            // Capacidades del widget
            $table->boolean('supports_thresholds')->default(false);
            $table->boolean('supports_multiple_slots')->default(false);
            $table->boolean('supports_animation')->default(true);

            // Estado
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Índices
            $table->index('category');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('widget_definitions');
    }
};
