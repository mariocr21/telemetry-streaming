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
        Schema::create('diagnostic_trouble_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
            $table->string('code', 10);
            $table->string('description')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('redetected_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Índices para búsquedas rápidas
            $table->index(['vehicle_id', 'is_active']);
            $table->index('code');

            // Combinación única para evitar duplicados
            $table->unique(['vehicle_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diagnostic_trouble_codes');
    }
};
