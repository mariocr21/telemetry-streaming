<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Widget Instances: Instancias de widgets configuradas dentro de un grupo.
     * Aquí es donde se elige QUÉ TIPO DE WIDGET usar para cada sensor.
     */
    public function up(): void
    {
        Schema::create('widget_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('widget_group_id')->constrained()->onDelete('cascade');
            $table->foreignId('widget_definition_id')->constrained()->onDelete('restrict');

            // Configuración específica de esta instancia (props del componente Vue)
            $table->json('props')->nullable();
            // Ej: {"min": 0, "max": 9000, "label": "RPM", "thresholds": [...]}

            // Posición dentro del grupo
            $table->integer('sort_order')->default(0);

            // Tamaño del widget
            $table->string('size_class')->default('md'); // sm, md, lg, xl, full

            // Override de estilos (para customización fina)
            $table->json('style_override')->nullable();
            // Ej: {"textColor": "text-cyan-400", "bgColor": "bg-slate-900"}

            // Estado
            $table->boolean('is_visible')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index(['widget_group_id', 'sort_order']);
            $table->index(['widget_group_id', 'is_visible']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('widget_instances');
    }
};
