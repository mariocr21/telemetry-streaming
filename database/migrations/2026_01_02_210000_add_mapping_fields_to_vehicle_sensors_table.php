<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vehicle_sensors', function (Blueprint $table) {
            // mapping_key: Identificador que viene del dispositivo (MQTT)
            // Ej: "engine_temp", "0C", "can_1F4_byte0"
            // Esto permite desacoplar el ID interno (sensor_id) del ID externo.
            $table->string('mapping_key')->nullable()->after('sensor_id');

            // source_type: Origen del dato para contexto
            // 'OBD2', 'CAN_CUSTOM', 'GPS', 'VIRTUAL'
            $table->string('source_type')->default('OBD2')->after('mapping_key');

            // Índice COMPUESTO único opcional (vehicle + mapping_key) 
            // para asegurar que no haya claves duplicadas por coche.
            // Pero mejor índex normal por ahora para evitar conflictos en update.
            $table->index(['vehicle_id', 'mapping_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_sensors', function (Blueprint $table) {
            $table->dropIndex(['vehicle_id', 'mapping_key']);
            $table->dropColumn(['mapping_key', 'source_type']);
        });
    }
};
