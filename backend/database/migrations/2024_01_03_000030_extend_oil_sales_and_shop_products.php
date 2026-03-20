<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Track opening stock per shift so closing can be derived
        Schema::table('oil_sales', function (Blueprint $table) {
            $table->decimal('opening_stock', 10, 3)->default(0)->after('shop_product_id');
            // closing_stock = opening_stock - quantity (calculated, not stored)
        });
    }

    public function down(): void
    {
        Schema::table('oil_sales', function (Blueprint $table) {
            $table->dropColumn('opening_stock');
        });
    }
};
