<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('meter_readings');

        Schema::create('meter_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained()->onDelete('cascade');
            $table->foreignId('nozzle_id')->constrained('pump_nozzles')->onDelete('cascade');
            // Mechanical counter (integer odometer on the pump head)
            $table->decimal('opening_mechanical', 12, 1);
            $table->decimal('closing_mechanical', 12, 1)->nullable();
            // Electrical counter (electronic display — used for revenue calculation)
            $table->decimal('opening_electrical', 12, 3);
            $table->decimal('closing_electrical', 12, 3)->nullable();
            // Calculated from electrical: closing - opening
            $table->decimal('litres_sold', 12, 3)->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique(['shift_id', 'nozzle_id']);
            $table->index('nozzle_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meter_readings');

        Schema::create('meter_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('shift_id')->constrained()->onDelete('cascade');
            $table->decimal('opening_meter', 12, 3);
            $table->decimal('closing_meter', 12, 3)->nullable();
            $table->decimal('litres_sold', 12, 3)->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique(['shift_id', 'product_id']);
        });
    }
};
