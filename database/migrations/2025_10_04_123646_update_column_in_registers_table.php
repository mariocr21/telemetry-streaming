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
        Schema::table('registers', function (Blueprint $table) {
            // actualizar el tipo de dato en la columna 'value' a decimal(15, 10) para aceptar datos de latitud y longitud
            $table->decimal('value', 15, 10)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registers', function (Blueprint $table) {
            //
            $table->decimal('value', 10, 2)->change();
        });
    }
};
