<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds cash reconciliation fields to the shifts table.
 *
 * actual_cash  — physically counted cash in the drawer at shift end.
 *                Entered by the operator. This is the "actual" side of the reconciliation.
 *
 * mpesa_amount — total MPESA/mobile money received during the shift.
 *                A non-cash channel that must be subtracted from total revenue
 *                to isolate the cash component. Captured per-shift so cash can
 *                be reconciled before the DSR is generated.
 *
 * cash_variance_status — ok | warning | critical, computed and stored when
 *                        actual_cash is submitted. Persisted so it survives page reloads
 *                        and can be referenced during DSR generation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->decimal('actual_cash', 12, 2)->nullable()->after('closed_by')
                ->comment('Physically counted cash at shift end');
            $table->decimal('mpesa_amount', 12, 2)->nullable()->after('actual_cash')
                ->comment('MPESA/mobile money total for this shift (non-cash channel)');
            $table->string('cash_variance_status', 20)->nullable()->after('mpesa_amount')
                ->comment('ok | warning | critical — set when actual_cash is saved');
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn(['actual_cash', 'mpesa_amount', 'cash_variance_status']);
        });
    }
};
