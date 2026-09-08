<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('account_imports') || Schema::hasColumn('account_imports', 'cost_center_id')) {
            return;
        }

        Schema::table('account_imports', function (Blueprint $table) {
            $table->uuid('cost_center_id')->nullable()->after('bank_account_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('account_imports') || ! Schema::hasColumn('account_imports', 'cost_center_id')) {
            return;
        }

        Schema::table('account_imports', function (Blueprint $table) {
            $table->dropColumn('cost_center_id');
        });
    }
};
