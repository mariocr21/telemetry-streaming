<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * This migration populates the mapping_key field in vehicle_sensors
     * with the PID from the associated sensor when mapping_key is NULL.
     * 
     * The mapping_key is what the firmware sends to identify which sensor the data belongs to.
     * By default, it should match the sensor's PID.
     */
    public function up(): void
    {
        // Update vehicle_sensors that have NULL mapping_key
        // Set mapping_key = sensor.pid
        DB::statement('
            UPDATE vehicle_sensors 
            SET mapping_key = (
                SELECT sensors.pid 
                FROM sensors 
                WHERE sensors.id = vehicle_sensors.sensor_id
            )
            WHERE vehicle_sensors.mapping_key IS NULL
        ');

        // Also set default source_type based on sensor.is_standard
        DB::statement('
            UPDATE vehicle_sensors 
            SET source_type = (
                SELECT CASE 
                    WHEN sensors.is_standard = 1 THEN \'OBD2\'
                    ELSE \'CAN_CUSTOM\'
                END
                FROM sensors 
                WHERE sensors.id = vehicle_sensors.sensor_id
            )
            WHERE vehicle_sensors.source_type IS NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse - the data was empty before
    }
};
