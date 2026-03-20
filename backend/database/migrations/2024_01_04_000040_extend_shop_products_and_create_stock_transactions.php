<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add cost, forecourt_stock, store_stock to shop_products
        Schema::table('shop_products', function (Blueprint $table) {
            $table->decimal('cost', 10, 2)->default(0)->after('current_price');
            $table->decimal('forecourt_stock', 10, 3)->default(0)->after('cost');
            $table->decimal('store_stock',     10, 3)->default(0)->after('forecourt_stock');
        });

        // Full stock transaction ledger per shop product
        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_product_id')->constrained()->onDelete('cascade');
            $table->foreignId('station_id')->constrained()->onDelete('cascade');
            // 'grn' = received, 'iss' = issued/sold, 'adj' = manual adjustment
            $table->enum('type', ['grn', 'iss', 'adj']);
            $table->date('trans_date');
            $table->decimal('quantity', 10, 3);
            // Reference: DSR serial number (for ISS), GRN number, etc.
            $table->string('document_ref', 50)->nullable();
            $table->string('notes', 255)->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['shop_product_id', 'trans_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transactions');
        Schema::table('shop_products', function (Blueprint $table) {
            $table->dropColumn(['cost', 'forecourt_stock', 'store_stock']);
        });
    }
};
