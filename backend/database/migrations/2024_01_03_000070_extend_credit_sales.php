<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_customers', function (Blueprint $table) {
            // Station-assigned account number e.g. P051161877Z
            $table->string('account_number', 30)->nullable()->after('customer_name');
        });

        Schema::table('credit_sales', function (Blueprint $table) {
            // Auto-generated debit note number e.g. 336940-116187
            $table->string('debit_note', 30)->nullable()->after('id');
            // Fuel or Oil
            $table->enum('type', ['fuel', 'oil', 'other'])->default('fuel')->after('debit_note');
            // VAT (16%) and Withholding Tax amounts
            $table->decimal('vat_amount', 12, 2)->default(0)->after('total_value');
            $table->decimal('wht_amount', 12, 2)->default(0)->after('vat_amount');
        });
    }

    public function down(): void
    {
        Schema::table('credit_sales', function (Blueprint $table) {
            $table->dropColumn(['debit_note', 'type', 'vat_amount', 'wht_amount']);
        });

        Schema::table('credit_customers', function (Blueprint $table) {
            $table->dropColumn('account_number');
        });
    }
};
