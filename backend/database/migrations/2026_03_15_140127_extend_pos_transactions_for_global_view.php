<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::table('pos_transactions')->update([
                'station_id' => DB::raw('(SELECT station_id FROM shifts WHERE shifts.id = pos_transactions.shift_id)'),
                'trans_date' => DB::raw('(SELECT shift_date FROM shifts WHERE shifts.id = pos_transactions.shift_id)'),
            ]);
        } else {
            DB::statement('
                UPDATE pos_transactions pt
                JOIN shifts s ON s.id = pt.shift_id
                SET pt.station_id = s.station_id,
                    pt.trans_date = s.shift_date
            ');
        }
    }

    public function down(): void
    {
        Schema::table('pos_transactions', function (Blueprint $table) {
            $table->dropForeign(['station_id']);
            $table->dropColumn(['station_id', 'trans_date']);
        });
    }
};
