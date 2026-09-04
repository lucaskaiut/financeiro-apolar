<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->uuid('subcategory_id')->nullable()->after('category_id');
            $table->index(['tenant_id', 'subcategory_id']);
        });
    }

    public function down(): void
    {
        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'subcategory_id']);
            $table->dropColumn('subcategory_id');
        });
    }
};
