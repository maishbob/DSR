<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pump_nozzles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('nozzle_name', 50); // e.g. "DX1 DIESEL", "UX3 UNLEADED"
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['station_id', 'product_id']);
            $table->unique(['station_id', 'nozzle_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pump_nozzles');
    }
};
