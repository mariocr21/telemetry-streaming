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
        Schema::table('vehicles', function (Blueprint $table) {
            // Eliminar el constraint unique que limitaba un dispositivo a un vehículo
            $table->dropUnique(['client_device_id']);
            
            // Agregar índices para optimizar consultas con múltiples vehículos
            $table->index(['client_device_id', 'status']);
            $table->index(['client_id', 'client_device_id']);
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // Eliminar los índices agregados
            $table->dropIndex(['client_device_id', 'status']);
            $table->dropIndex(['client_id', 'client_device_id']);
            
            // Intentar restaurar el constraint unique (solo funcionará si no hay duplicados)
            try {
                $table->unique(['client_device_id']);
            } catch (\Exception $e) {
                // Si hay datos duplicados, no se puede restaurar el unique constraint
                // Se debe limpiar manualmente antes del rollback
            }
        });
    }

};
