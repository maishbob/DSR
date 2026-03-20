<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('tank_id')->constrained()->onDelete('cascade');
            $table->foreignId('shift_id')->nullable()->constrained()->onDelete('set null');
            $table->date('delivery_date');
            $table->string('supplier_name');
            $table->string('waybill_number')->nullable();
            $table->decimal('delivery_quantity', 10, 2); // litres invoiced
            $table->decimal('tank_dip_before', 10, 2)->nullable(); // dip before delivery
            $table->decimal('tank_dip_after', 10, 2)->nullable();  // dip after delivery
            // calculated: (tank_dip_after - tank_dip_before) - delivery_quantity
            $table->decimal('delivery_variance', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['station_id', 'delivery_date']);
            $table->index(['product_id', 'delivery_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
