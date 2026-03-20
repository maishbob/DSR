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
            // Transaction category (Fuel/LPG/POS/Invoice = debit notes; Receipts = payment)
            $table->enum('trans_type', ['receipts', 'fuel', 'lpg', 'pos', 'invoice'])
                  ->default('receipts')->after('receipt_no');
            // Extend payment_method to include RTGS and Equity Card
            \DB::statement("ALTER TABLE payments MODIFY COLUMN payment_method
                ENUM('cash','mpesa','bank_transfer','cheque','rtgs','equity_card','other')
                NOT NULL DEFAULT 'cash'");
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('trans_type');
            \DB::statement("ALTER TABLE payments MODIFY COLUMN payment_method
                ENUM('cash','mpesa','bank_transfer','cheque','other')
                NOT NULL DEFAULT 'cash'");
        });
    }
};
