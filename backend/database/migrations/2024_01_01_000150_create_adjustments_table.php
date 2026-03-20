<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_sales_record_id')->constrained()->onDelete('cascade');
            $table->foreignId('station_id')->constrained()->onDelete('cascade');
            $table->string('adjustment_type'); // meter_reading, tank_dip, delivery, credit_sale
            $table->text('reason');
            $table->decimal('original_value', 14, 4)->nullable();
            $table->decimal('corrected_value', 14, 4)->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['daily_sales_record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adjustments');
    }
};
