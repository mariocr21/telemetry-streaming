<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Widget Groups: Los "grupos" o "cards" del dashboard.
     * Ejemplos: "Engine Performance", "Tires", "Electrical", "Temperatures"
     */
    public function up(): void
    {
        Schema::create('widget_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dashboard_layout_id')->constrained()->onDelete('cascade');

            // Identificación del grupo
            $table->string('name');         // "Engine Performance"
            $table->string('slug');         // "engine-performance"
            $table->string('icon')->nullable(); // Lucide icon name: "gauge", "thermometer", "zap"

            // Posición en el grid CSS
            $table->integer('grid_column_start')->default(1);   // CSS grid-column-start
            $table->integer('grid_column_span')->default(6);    // CSS grid-column span
            $table->integer('grid_row_start')->nullable();      // CSS grid-row-start (null = auto)
            $table->integer('grid_row_span')->default(1);       // CSS grid-row span

            // Orden de renderizado
            $table->integer('sort_order')->default(0);

            // Configuración visual del grupo
            $table->json('style_config')->nullable();
            // Ej: {"bgColor": "bg-dash-card", "borderColor": "border-slate-700", "variant": "default"}

            // Estado
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_collapsible')->default(false);
            $table->boolean('is_collapsed')->default(false);

            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index(['dashboard_layout_id', 'sort_order']);
            $table->index(['dashboard_layout_id', 'is_visible']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('widget_groups');
    }
};
