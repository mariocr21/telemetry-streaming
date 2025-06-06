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
            // Agregar columna de estado del vehículo
            $table->boolean('status')->default(true)->after('last_reading_at');
            // Agregar índice para la columna de estado
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // Eliminar la columna de estado del vehículo
            if (Schema::hasColumn('vehicles', 'status')) {
                $table->dropIndex(['status']); // Eliminar índice
                $table->dropColumn('status'); // Eliminar columna
            }
        });
    }
};
