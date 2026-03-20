<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('shift_id')->constrained()->onDelete('cascade');
            $table->decimal('quantity', 10, 3); // litres
            $table->decimal('price_applied', 10, 4);
            $table->decimal('total_value', 12, 2);
            $table->string('vehicle_plate')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['credit_customer_id']);
            $table->index(['shift_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_sales');
    }
};
