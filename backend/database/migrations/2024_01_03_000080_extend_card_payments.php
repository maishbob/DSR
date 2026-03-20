<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_payments', function (Blueprint $table) {
            // Date the bank settled/reconciled this batch
            $table->date('recon_date')->nullable()->after('amount');
            // Bank batch settlement reference
            $table->string('batch_ref', 50)->nullable()->after('recon_date');
        });
    }

    public function down(): void
    {
        Schema::table('card_payments', function (Blueprint $table) {
            $table->dropColumn(['recon_date', 'batch_ref']);
        });
    }
};
