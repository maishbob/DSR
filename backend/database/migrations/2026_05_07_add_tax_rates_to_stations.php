<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            $table->decimal('vat_rate', 5, 4)->default(0.16)->after('timezone');
            $table->decimal('wht_rate', 5, 4)->default(0.0172)->after('vat_rate');
        });
    }

    public function down(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            $table->dropColumn(['vat_rate', 'wht_rate']);
        });
    }
};
