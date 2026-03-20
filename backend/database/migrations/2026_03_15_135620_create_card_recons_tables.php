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
        Schema::create('card_recons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->onDelete('cascade');
            $table->string('card_name', 50);        // EQUITY, BARCLAYS, KCB, etc.
            $table->string('batch_ref', 30)->nullable();
            $table->date('recon_date');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['station_id', 'recon_date']);
        });

        Schema::create('card_recon_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_recon_id')->constrained()->onDelete('cascade');
            $table->date('trans_date');
            $table->string('ref', 50)->nullable();
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_recon_lines');
        Schema::dropIfExists('card_recons');
    }
};
