<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pump_nozzles', function (Blueprint $table) {
            // Which tank this nozzle draws from
            $table->foreignId('tank_id')->nullable()->after('product_id')
                ->constrained()->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('pump_nozzles', function (Blueprint $table) {
            $table->dropForeign(['tank_id']);
            $table->dropColumn('tank_id');
        });
    }
};
