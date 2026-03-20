<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tanks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('tank_name');
            $table->decimal('tank_capacity', 10, 2); // litres
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['station_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tanks');
    }
};
