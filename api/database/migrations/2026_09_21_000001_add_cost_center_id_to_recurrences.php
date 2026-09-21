<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('recurrences') || Schema::hasColumn('recurrences', 'cost_center_id')) {
            return;
        }

        Schema::table('recurrences', function (Blueprint $table) {
            $table->uuid('cost_center_id')->nullable()->after('bank_account_id');
            $table->index(['tenant_id', 'cost_center_id'], 'recurrences_tenant_cost_center_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('recurrences') || ! Schema::hasColumn('recurrences', 'cost_center_id')) {
            return;
        }

        Schema::table('recurrences', function (Blueprint $table) {
            $table->dropIndex('recurrences_tenant_cost_center_idx');
            $table->dropColumn('cost_center_id');
        });
    }
};
