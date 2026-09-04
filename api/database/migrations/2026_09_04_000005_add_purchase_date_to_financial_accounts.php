<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('financial_accounts', 'purchase_date')) {
                $table->date('purchase_date')->nullable()->after('due_date');
            }
        });

        if (Schema::hasColumn('financial_accounts', 'purchase_date')) {
            DB::table('financial_accounts')
                ->where('is_card_purchase', true)
                ->whereNull('purchase_date')
                ->update(['purchase_date' => DB::raw('due_date')]);
        }
    }

    public function down(): void
    {
        Schema::table('financial_accounts', function (Blueprint $table): void {
            if (Schema::hasColumn('financial_accounts', 'purchase_date')) {
                $table->dropColumn('purchase_date');
            }
        });
    }
};
