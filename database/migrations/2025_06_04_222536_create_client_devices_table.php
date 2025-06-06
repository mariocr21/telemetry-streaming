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
        Schema::create('client_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_inventory_id')->constrained()->onDelete('cascade');
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->string('device_name')->nullable(); // Nombre personalizado del cliente
            $table->string('mac_address')->nullable(); // MAC address del dispositivo
            $table->string('status')->default('pending_setup'); // pending_setup, active, inactive, maintenance
            $table->timestamp('activated_at')->useCurrent(); // Cuándo se activó
            $table->timestamp('last_ping')->nullable(); // Último ping recibido
            $table->json('device_config')->nullable(); // Configuración del dispositivo
            $table->softDeletes();
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_devices');
    }
};
