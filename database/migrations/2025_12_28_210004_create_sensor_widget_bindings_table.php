<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Sensor Widget Bindings: TABLA CRÍTICA
     * Vincula los sensores del vehículo con las instancias de widgets.
     * Esta es la tabla que permite que el dato CAN_ID_0x1F mueva el gauge correcto.
     */
    public function up(): void
    {
        Schema::create('sensor_widget_bindings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('widget_instance_id')->constrained()->onDelete('cascade');
            $table->foreignId('vehicle_sensor_id')->constrained()->onDelete('cascade');

            // Clave del dato en el JSON de telemetría
            $table->string('telemetry_key');  // "RPM", "CAN_ID_0x1F", "Coolant_Temp"

            // Prop del widget a la que se vincula el valor
            $table->string('target_prop')->default('value');  // Generalmente "value"

            // Para widgets con múltiples slots (ej: TireGrid con 4 ruedas, TextGrid con 4 temps)
            $table->string('slot')->nullable();  // "fl", "fr", "rl", "rr" o "coolant", "oil", etc.

            // Transformación opcional del valor
            $table->json('transform')->nullable();
            // Ej: {"multiply": 0.1, "offset": -40, "round": 2, "clamp": {"min": 0, "max": 100}}

            // Override de display (para mostrar nombre/unidad diferente al del sensor)
            $table->string('display_label')->nullable();  // Override del nombre del sensor
            $table->string('display_unit')->nullable();   // Override de unidad

            // Umbrales específicos para este binding (override de los del widget)
            $table->json('thresholds')->nullable();
            // Ej: {"warning": 220, "critical": 260}

            $table->timestamps();

            // Restricciones
            $table->unique(['widget_instance_id', 'vehicle_sensor_id', 'slot'], 'unique_binding');

            // Índices para lookup rápido
            $table->index('telemetry_key');
            $table->index('widget_instance_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sensor_widget_bindings');
    }
};
