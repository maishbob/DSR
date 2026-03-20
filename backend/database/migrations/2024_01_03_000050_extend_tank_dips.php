<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Two tanks can be physically linked (manifolded together).
        // Each still gets its own dip reading — the shortage screen
        // displays them on the same row as Dip Stock / Dip Stock 2.
        Schema::table('tanks', function (Blueprint $table) {
            $table->foreignId('linked_tank_id')->nullable()->after('tank_capacity')
                ->constrained('tanks')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('tanks', function (Blueprint $table) {
            $table->dropForeign(['linked_tank_id']);
            $table->dropColumn('linked_tank_id');
        });
    }
};
