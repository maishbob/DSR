<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_sales_records', function (Blueprint $table) {
            // Sequential DSR serial number per station
            $table->unsignedBigInteger('serial_number')->nullable()->after('id');

            // How many days this DSR covers (usually 1)
            $table->unsignedTinyInteger('dsr_covers_days')->default(1)->after('shift_type');

            // Operator who prepared, manager who verified
            $table->string('prepared_by', 100)->nullable();
            $table->string('verified_by', 100)->nullable();

            // Cash register Z-readings (from oils/shop till)
            $table->decimal('z_amount_a', 12, 2)->default(0);
            $table->decimal('z_amount_b', 12, 2)->default(0);
            $table->decimal('z_amount_d', 12, 2)->default(0);

            // Payment channel totals
            $table->decimal('cash_collected', 14, 2)->default(0);
            $table->decimal('mpesa_collected', 14, 2)->default(0);
            $table->decimal('total_card_sales', 14, 2)->default(0);
            $table->decimal('total_pos_sales', 14, 2)->default(0);
            $table->decimal('total_oil_sales', 14, 2)->default(0);
            $table->decimal('total_expenses', 14, 2)->default(0);

            // Summary totals
            $table->decimal('total_fuel_sales', 14, 2)->default(0); // revenue from pumps
            $table->decimal('gross_sales', 14, 2)->default(0);      // all channels collected
            $table->decimal('net_sales_balance', 14, 2)->default(0); // gross - fuel - oils

            $table->index(['station_id', 'serial_number']);
        });
    }

    public function down(): void
    {
        Schema::table('daily_sales_records', function (Blueprint $table) {
            $table->dropColumn([
                'serial_number', 'dsr_covers_days', 'prepared_by', 'verified_by',
                'z_amount_a', 'z_amount_b', 'z_amount_d',
                'cash_collected', 'mpesa_collected', 'total_card_sales',
                'total_pos_sales', 'total_oil_sales', 'total_expenses',
                'total_fuel_sales', 'gross_sales', 'net_sales_balance',
            ]);
        });
    }
};
