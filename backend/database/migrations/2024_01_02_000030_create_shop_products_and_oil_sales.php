<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Non-fuel products sold at the station shop (oils, lubricants, gas, etc.)
        Schema::create('shop_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->onDelete('cascade');
            $table->string('product_name', 100); // e.g. "HX7 HELIX 10W-40 4L"
            $table->string('unit', 20)->default('unit'); // unit, litre, kg, etc.
            $table->decimal('current_price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('station_id');
        });

        // Oil/shop product sales recorded per shift
        Schema::create('oil_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained()->onDelete('cascade');
            $table->foreignId('shop_product_id')->constrained()->onDelete('cascade');
            $table->decimal('quantity', 10, 3);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_value', 12, 2);
            $table->foreignId('entered_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index('shift_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oil_sales');
        Schema::dropIfExists('shop_products');
    }
};
