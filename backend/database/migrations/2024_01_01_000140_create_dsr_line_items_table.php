<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dsr_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_sales_record_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('tank_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('opening_meter', 12, 3)->default(0);
            $table->decimal('closing_meter', 12, 3)->default(0);
            $table->decimal('litres_sold', 12, 3)->default(0);
            $table->decimal('price_per_litre', 10, 4)->default(0);
            $table->decimal('revenue', 14, 2)->default(0);
            $table->decimal('opening_stock', 12, 3)->default(0);
            $table->decimal('deliveries', 12, 3)->default(0);
            $table->decimal('expected_stock', 12, 3)->default(0);
            $table->decimal('actual_stock', 12, 3)->default(0);
            $table->decimal('variance', 12, 3)->default(0);
            $table->decimal('credit_sales_litres', 12, 3)->default(0);
            $table->decimal('credit_sales_value', 14, 2)->default(0);
            $table->timestamps();

            $table->index('daily_sales_record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dsr_line_items');
    }
};
