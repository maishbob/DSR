<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tank_dips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tank_id')->constrained()->onDelete('cascade');
            $table->foreignId('shift_id')->constrained()->onDelete('cascade');
            $table->enum('dip_type', ['opening', 'closing'])->default('closing');
            $table->decimal('dip_volume', 10, 2); // litres
            $table->foreignId('entered_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique(['tank_id', 'shift_id', 'dip_type']);
            $table->index(['tank_id', 'shift_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tank_dips');
    }
};
