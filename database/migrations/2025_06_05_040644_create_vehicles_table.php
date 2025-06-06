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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('client_device_id')->constrained()->onDelete('cascade');

            // Datos detectados automáticamente por OBD2 (si están disponibles)
            $table->string('vin')->nullable(); // VIN del vehículo
            $table->string('protocol')->nullable(); // Protocolo OBD2 detectado
            $table->json('supported_pids')->nullable(); // PIDs soportados detectados

            // Datos configurados manualmente por el usuario
            $table->string('make')->nullable(); // Marca (usuario puede editarla)
            $table->string('model')->nullable(); // Modelo (usuario puede editarla)
            $table->integer('year')->nullable(); // Año (usuario puede editarlo)
            $table->string('license_plate')->nullable(); // Placa
            $table->string('color')->nullable(); // Color
            $table->string('nickname')->nullable(); // Apodo del vehículo

            // Estado del sistema
            $table->boolean('auto_detected')->default(false); // Si fue detectado automáticamente
            $table->boolean('is_configured')->default(false); // Si el usuario terminó la configuración
            $table->timestamp('first_reading_at')->nullable(); // Primera lectura OBD2
            $table->timestamp('last_reading_at')->nullable(); // Última lectura OBD2
            $table->softDeletes(); // Para manejar eliminaciones lógicas
            $table->timestamps();

            $table->unique(['client_device_id']); // Un dispositivo por vehículo
            $table->index(['client_id', 'is_configured']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
