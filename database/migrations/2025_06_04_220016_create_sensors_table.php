<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sensors', function (Blueprint $table) {
            $table->id();
            // Información básica del PID
            $table->string('pid', 10)->unique(); // 0x00, 0x01, 0x04, etc.
            $table->string('name'); // Engine Load, RPM, etc.
            $table->text('description')->nullable(); // Descripción detallada
            $table->string('category'); // engine, fuel, diagnostics, vehicle, etc.
            $table->string('unit'); // %, °C, RPM, km/h, bit_encoded, etc.
            $table->string('data_type')->default('numeric'); // numeric, boolean, bit_encoded

            // Rango de valores (para validación)
            $table->decimal('min_value', 10, 2)->nullable();
            $table->decimal('max_value', 10, 2)->nullable();

            // Configuración para cálculos OBD2
            $table->boolean('requires_calculation')->default(false);
            $table->text('calculation_formula')->nullable(); // Fórmula de conversión del valor raw
            $table->integer('data_bytes')->default(1); // 1, 2, 4 bytes de datos

            // Metadatos
            $table->boolean('is_standard')->default(true); // Si es PID estándar o específico del fabricante
            $table->text('notes')->nullable(); // Notas adicionales

            $table->timestamps();
            $table->softDeletes(); // Para manejar eliminaciones lógicas

            // Índices
            $table->index('category');
            $table->index('is_standard');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sensors');
    }
};
