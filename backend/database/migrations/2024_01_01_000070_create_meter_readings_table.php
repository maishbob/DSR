<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meter_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('shift_id')->constrained()->onDelete('cascade');
            $table->decimal('opening_meter', 12, 3);
            $table->decimal('closing_meter', 12, 3)->nullable();
            // calculated: closing_meter - opening_meter
            $table->decimal('litres_sold', 12, 3)->nullable()->storedAs(null);
            $table->foreignId('entered_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique(['shift_id', 'product_id']);
            $table->index(['station_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meter_readings');
    }
};
