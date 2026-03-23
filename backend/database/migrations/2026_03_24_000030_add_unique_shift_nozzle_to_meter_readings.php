<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prevents duplicate meter readings for the same nozzle within a shift.
 * The application already uses firstOrCreate(), but this constraint enforces
 * idempotency at the database level — the last line of defence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meter_readings', function (Blueprint $table) {
            $table->unique(['shift_id', 'nozzle_id'], 'unique_shift_nozzle');
        });
    }

    public function down(): void
    {
        Schema::table('meter_readings', function (Blueprint $table) {
            $table->dropUnique('unique_shift_nozzle');
        });
    }
};
