<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meter_readings', function (Blueprint $table) {
            // Revenue odometer (cumulative KES counter on the pump)
            $table->decimal('opening_shs', 15, 2)->nullable()->after('closing_electrical');
            $table->decimal('closing_shs', 15, 2)->nullable()->after('opening_shs');
            // Calculated: closing_shs - opening_shs (cross-check vs electronic litres × price)
            $table->decimal('shs_sold', 14, 2)->nullable()->after('closing_shs');
        });
    }

    public function down(): void
    {
        Schema::table('meter_readings', function (Blueprint $table) {
            $table->dropColumn(['opening_shs', 'closing_shs', 'shs_sold']);
        });
    }
};
