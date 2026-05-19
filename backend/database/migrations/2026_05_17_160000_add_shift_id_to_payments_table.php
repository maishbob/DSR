<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('shift_id')
                ->nullable()
                ->after('station_id')
                ->constrained('shifts')
                ->nullOnDelete();

            $table->index(['shift_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['shift_id', 'payment_date']);
            $table->dropForeign(['shift_id']);
            $table->dropColumn('shift_id');
        });
    }
};
