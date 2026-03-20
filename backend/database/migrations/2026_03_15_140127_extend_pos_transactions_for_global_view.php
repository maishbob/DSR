<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pos_transactions', function (Blueprint $table) {
            $table->date('trans_date')->nullable()->after('shift_id');
            $table->foreignId('station_id')->nullable()->after('shift_id')
                  ->constrained()->onDelete('cascade');
        });

        // Back-fill from shifts
        \DB::statement('
            UPDATE pos_transactions pt
            JOIN shifts s ON s.id = pt.shift_id
            SET pt.station_id = s.station_id,
                pt.trans_date = s.shift_date
        ');
    }

    public function down(): void
    {
        Schema::table('pos_transactions', function (Blueprint $table) {
            $table->dropForeign(['station_id']);
            $table->dropColumn(['station_id', 'trans_date']);
        });
    }
};
