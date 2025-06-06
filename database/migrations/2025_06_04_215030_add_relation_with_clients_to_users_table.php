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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('client_id')
                ->nullable()
                ->default(null)
                ->constrained('clients')
                ->nullOnDelete()
                ->after('id'); // Adjust the position of the new column if necessary
            $table->enum('role', ['SA', 'CA', 'CU']) // SA = Super Admin, CA = Client Admin, CU = Client User
                ->default('CU')
                ->after('client_id'); // Adjust the position of the new column if necessary
            $table->boolean('is_active')->default(true)
                ->after('role'); // Adjust the position of the new column if necessary
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // check if the foreign key exists before dropping it
            if (Schema::hasColumn('users', 'client_id')) {
                $table->dropForeign(['client_id']);
            }
            $table->dropColumn(['client_id', 'role', 'is_active']);
            $table->dropSoftDeletes();
        });
    }
};
