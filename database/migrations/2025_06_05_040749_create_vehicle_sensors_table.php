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
        Schema::create('vehicle_sensors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
            $table->foreignId('sensor_id')->constrained('sensors')->onDelete('cascade');
            $table->boolean('is_active')->default(true); // Si está enviando datos
            $table->integer('frequency_seconds')->default(5); // Frecuencia de envío

            // Información del sensor
            $table->decimal('min_value', 10, 2)->nullable();
            $table->decimal('max_value', 10, 2)->nullable();

            $table->timestamp('last_reading_at')->nullable(); // Última lectura recibida
            $table->softDeletes(); // Para manejar eliminaciones lógicas
            $table->timestamps();

            $table->unique(['vehicle_id', 'sensor_id']);
            $table->index(['vehicle_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_sensors');
    }
};
