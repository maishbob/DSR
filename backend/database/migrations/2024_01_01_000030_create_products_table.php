<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->onDelete('cascade');
            $table->string('product_name'); // Petrol, Diesel, Kerosene
            $table->string('unit')->default('litres');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('station_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
