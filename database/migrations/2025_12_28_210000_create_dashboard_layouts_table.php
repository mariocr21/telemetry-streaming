<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Dashboard Layouts: Define qué layout tiene cada vehículo.
     * Cada vehículo puede tener múltiples layouts pero solo uno activo.
     */
    public function up(): void
    {
        Schema::create('dashboard_layouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');

            // Identificación del layout
            $table->string('name')->default('Default Layout');
            $table->string('theme')->default('cyberpunk-dark'); // Para futuras variantes de tema

            // Configuración global del grid CSS
            $table->json('grid_config')->nullable();
            // Ejemplo: {"columns": 12, "gap": 4, "breakpoints": {"lg": 12, "md": 6, "sm": 1}}

            // Estado
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);

            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index(['vehicle_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_layouts');
    }
};
