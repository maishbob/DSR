<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_customers', function (Blueprint $table) {
            $table->string('contact', 100)->nullable()->after('customer_name');
            $table->string('address', 255)->nullable()->after('contact');
            $table->string('city', 100)->nullable()->after('address');
            $table->string('pin', 30)->nullable()->after('city');           // KRA PIN
            $table->string('vat_number', 30)->nullable()->after('pin');
            $table->boolean('is_withholding_vat_agent')->default(false)->after('vat_number');
            $table->decimal('discount_multiplier', 8, 4)->default(0)->after('credit_limit');
            $table->decimal('initial_opening_balance', 14, 2)->default(0)->after('discount_multiplier');
        });
    }

    public function down(): void
    {
        Schema::table('credit_customers', function (Blueprint $table) {
            $table->dropColumn([
                'contact', 'address', 'city', 'pin', 'vat_number',
                'is_withholding_vat_agent', 'discount_multiplier', 'initial_opening_balance',
            ]);
        });
    }
};
