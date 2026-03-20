<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dsr_line_items', function (Blueprint $table) {
            // Shortage and excess shown separately (variance split)
            $table->decimal('shortage', 10, 3)->default(0)->after('variance');
            $table->decimal('excess', 10, 3)->default(0)->after('shortage');
            // Running cumulative variance % across DSRs
            $table->decimal('cumulative_variance_pct', 8, 2)->default(0)->after('excess');
        });
    }

    public function down(): void
    {
        Schema::table('dsr_line_items', function (Blueprint $table) {
            $table->dropColumn(['shortage', 'excess', 'cumulative_variance_pct', 'price_per_litre']);
        });
    }
};
