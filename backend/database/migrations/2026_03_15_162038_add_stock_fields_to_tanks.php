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
        Schema::table('tanks', function (Blueprint $table) {
            $table->boolean('is_complex')->default(false)->after('tank_capacity');
            $table->decimal('last_closing_stock', 12, 2)->nullable()->after('is_complex');
            $table->decimal('last_dip_stock', 12, 2)->nullable()->after('last_closing_stock');
            $table->decimal('last_dip_2', 12, 2)->nullable()->after('last_dip_stock');
        });
    }

    public function down(): void
    {
        Schema::table('tanks', function (Blueprint $table) {
            $table->dropColumn(['is_complex', 'last_closing_stock', 'last_dip_stock', 'last_dip_2']);
        });
    }
};
