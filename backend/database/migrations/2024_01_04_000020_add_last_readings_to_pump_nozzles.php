<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pump_nozzles', function (Blueprint $table) {
            $table->decimal('last_mech', 12, 1)->nullable()->after('nozzle_no');
            $table->decimal('last_elec', 12, 3)->nullable()->after('last_mech');
            $table->decimal('last_shs',  15, 2)->nullable()->after('last_elec');
        });
    }

    public function down(): void
    {
        Schema::table('pump_nozzles', function (Blueprint $table) {
            $table->dropColumn(['last_mech', 'last_elec', 'last_shs']);
        });
    }
};
