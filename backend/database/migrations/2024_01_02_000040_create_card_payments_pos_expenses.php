<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Card payment transactions (EQUITY, BARCLAYS, etc.)
        Schema::create('card_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained()->onDelete('cascade');
            $table->string('card_name', 50);   // e.g. EQUITY, BARCLAYS
            $table->date('trans_date');
            $table->string('reference', 50);   // card transaction reference
            $table->decimal('amount', 12, 2);
            $table->foreignId('entered_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index('shift_id');
        });

        // POS machine transactions
        Schema::create('pos_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained()->onDelete('cascade');
            $table->string('reference', 50);   // POS slip reference
            $table->decimal('amount', 12, 2);
            $table->foreignId('entered_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index('shift_id');
        });

        // Daily operating expenses
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained()->onDelete('cascade');
            $table->string('expense_item', 150);
            $table->decimal('amount', 12, 2);
            $table->foreignId('entered_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index('shift_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('pos_transactions');
        Schema::dropIfExists('card_payments');
    }
};
