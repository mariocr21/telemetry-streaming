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
        Schema::create('device_inventories', function (Blueprint $table) {
            $table->id();
            $table->string('serial_number')->unique(); // OBD-2025-000156
            $table->string('device_uuid')->unique(); // Para comunicación API
            $table->string('model');
            $table->string('hardware_version')->nullable();
            $table->string('firmware_version')->nullable();
            $table->string('status')->default('available');
            $table->timestamp('manufactured_date')->nullable();
            $table->timestamp('sold_date')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes(); // Para manejar eliminaciones lógicas
            $table->timestamps();

            $table->index('status');
            $table->index('serial_number'); // Para activación rápida
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_inventories');
    }
};
