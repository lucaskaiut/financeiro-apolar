<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $dates = DB::table('settlements')
            ->selectRaw('account_id, MAX(settled_at) as last_settled_at')
            ->groupBy('account_id')
            ->pluck('last_settled_at', 'account_id');

        foreach ($dates as $accountId => $paidDate) {
            DB::table('financial_accounts')
                ->where('id', $accountId)
                ->update(['paid_date' => $paidDate]);
        }
    }

    public function down(): void
    {
        // Data backfill cannot be reversed safely.
    }
};
