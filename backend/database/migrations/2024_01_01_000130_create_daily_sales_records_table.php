<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_sales_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->unique()->constrained()->onDelete('cascade');
            $table->foreignId('station_id')->constrained()->onDelete('cascade');
            $table->date('shift_date');
            $table->enum('shift_type', ['day', 'night']);
            $table->decimal('total_litres_sold', 12, 3)->default(0);
            $table->decimal('total_revenue', 14, 2)->default(0);
            $table->decimal('total_credit_sales', 14, 2)->default(0);
            $table->decimal('total_cash_sales', 14, 2)->default(0);
            $table->decimal('total_deliveries', 12, 3)->default(0);
            $table->decimal('expected_stock', 12, 3)->default(0);
            $table->decimal('actual_stock', 12, 3)->default(0);
            $table->decimal('variance', 12, 3)->default(0);
            $table->json('product_breakdown')->nullable(); // per-product summary
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->boolean('locked')->default(false);
            $table->timestamps();

            $table->index(['station_id', 'shift_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_sales_records');
    }
};
