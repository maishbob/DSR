<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lock individual financial records when DSR is approved
        // Each table uses a different "after" anchor column
        $afterColumns = [
            'meter_readings' => 'entered_by',
            'tank_dips'      => 'entered_by',
            'credit_sales'   => 'entered_by',
            'payments'       => 'received_by',
        ];

        foreach ($afterColumns as $table => $afterCol) {
            if (Schema::hasColumn($table, 'is_locked')) continue;
            Schema::table($table, function (Blueprint $blueprint) use ($afterCol) {
                $blueprint->boolean('is_locked')->default(false)->after($afterCol)->index();
            });
        }

        // Variance classification + override support on the DSR
        Schema::table('daily_sales_records', function (Blueprint $table) {
            $table->string('variance_status', 20)->nullable()->after('variance')
                ->comment('ok | warning | critical');
            $table->text('override_reason')->nullable()->after('variance_status')
                ->comment('Required when approving a CRITICAL variance DSR');
            $table->unsignedBigInteger('override_by')->nullable()->after('override_reason');
            $table->timestamp('override_at')->nullable()->after('override_by');
            $table->foreign('override_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('daily_sales_records', function (Blueprint $table) {
            $table->dropForeign(['override_by']);
            $table->dropColumn(['variance_status', 'override_reason', 'override_by', 'override_at']);
        });

        foreach (['meter_readings', 'tank_dips', 'credit_sales', 'payments'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('is_locked');
            });
        }
    }
};
