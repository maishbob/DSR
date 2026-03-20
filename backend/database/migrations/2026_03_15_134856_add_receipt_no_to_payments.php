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
        Schema::table('payments', function (Blueprint $table) {
            $table->string('receipt_no', 30)->nullable()->after('payment_date');  // Receipt / Invoice No
            // rename payment_method enum to include rtgs/equity; add station_id for global index
            $table->foreignId('station_id')->nullable()->after('credit_customer_id')
                  ->constrained()->onDelete('cascade');
        });

        // Back-fill station_id from the credit_customer
        \DB::statement('
            UPDATE payments p
            JOIN credit_customers cc ON cc.id = p.credit_customer_id
            SET p.station_id = cc.station_id
        ');
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['station_id']);
            $table->dropColumn(['receipt_no', 'station_id']);
        });
    }
};
