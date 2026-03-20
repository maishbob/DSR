<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pump_nozzles', function (Blueprint $table) {
            // Short reference code like "UX3", "DX1"
            $table->string('nozzle_ref', 15)->nullable()->after('nozzle_name');
            // Physical pump number (Main Pump) and position (Nozzle No)
            $table->unsignedTinyInteger('main_pump')->nullable()->after('sort_order');
            $table->unsignedTinyInteger('nozzle_no')->nullable()->after('main_pump');
        });
    }

    public function down(): void
    {
        Schema::table('pump_nozzles', function (Blueprint $table) {
            $table->dropColumn(['nozzle_ref', 'main_pump', 'nozzle_no']);
        });
    }
};
