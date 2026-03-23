<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unified financial ledger.
 *
 * Every financial event (fuel sale, credit sale, payment, delivery, expense)
 * writes an entry here. This table is the source of truth for all balance
 * derivations and allows full reconstruction of any historical state.
 *
 * Rules:
 * - Records are NEVER updated after creation (append-only)
 * - Corrections are made via new entries with type = 'adjustment'
 * - direction: 'debit'  = money/stock OUT (sale, expense, delivery out)
 *              'credit' = money/stock IN  (payment received, delivery in)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('station_id');
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->date('trans_date');

            $table->string('type', 40)->comment(
                'fuel_sale | credit_sale | payment | delivery | expense | ' .
                'oil_sale | card_payment | pos | adjustment'
            );

            // Polymorphic reference to the originating record
            $table->string('reference_type', 100)->nullable()->comment('Model class e.g. MeterReading');
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->string('description', 255)->nullable();
            $table->decimal('amount', 14, 2)->comment('Always positive');
            $table->enum('direction', ['debit', 'credit']);

            // Optional links for filtered queries
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('credit_customer_id')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['station_id', 'trans_date']);
            $table->index(['station_id', 'type']);
            $table->index(['credit_customer_id', 'trans_date']);
            $table->index(['reference_type', 'reference_id']);

            $table->foreign('station_id')->references('id')->on('stations')->cascadeOnDelete();
            $table->foreign('shift_id')->references('id')->on('shifts')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
    }
};
