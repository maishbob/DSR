<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tank_dips', function (Blueprint $table) {
            // Litres dispensed for pump testing (not sold, deducted from stock)
            $table->decimal('pump_test_volume', 10, 3)->default(0)->after('dip_volume');
        });
    }

    public function down(): void
    {
        Schema::table('tank_dips', function (Blueprint $table) {
            $table->dropColumn('pump_test_volume');
        });
    }
};
