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
        Schema::create('registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_sensor_id')
                ->constrained('vehicle_sensors')
                ->onDelete('cascade');
            $table->decimal('value', 10, 2); // Valor del sensor
            $table->timestamp('recorded_at')->useCurrent(); // Fecha y hora de la lectura
            $table->softDeletes(); // Para manejar eliminaciones lógicas
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registers');
    }
};
