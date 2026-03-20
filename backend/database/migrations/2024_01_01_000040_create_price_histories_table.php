<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->decimal('price_per_litre', 10, 4);
            $table->date('effective_from');
            $table->date('effective_to')->nullable(); // null = currently active
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['product_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_histories');
    }
};
