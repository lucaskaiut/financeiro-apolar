<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('financial_accounts', 'cost_center_id') && ! Schema::hasColumn('financial_accounts', 'bank_account_id')) {
            Schema::table('financial_accounts', function (Blueprint $table): void {
                $table->renameColumn('cost_center_id', 'bank_account_id');
            });
        }

        Schema::table('financial_accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('financial_accounts', 'company_id')) {
                $table->uuid('company_id')->nullable()->after('counterparty');
            }

            if (! Schema::hasColumn('financial_accounts', 'cost_center_id')) {
                $table->uuid('cost_center_id')->nullable()->after('company_id');
            }

            if (! Schema::hasColumn('financial_accounts', 'credit_card_id')) {
                $table->uuid('credit_card_id')->nullable()->after('cost_center_id');
            }

            if (! Schema::hasColumn('financial_accounts', 'credit_card_invoice_id')) {
                $table->uuid('credit_card_invoice_id')->nullable()->after('credit_card_id');
            }

            if (! Schema::hasColumn('financial_accounts', 'is_card_purchase')) {
                $table->boolean('is_card_purchase')->default(false)->after('credit_card_invoice_id');
            }

            if (! Schema::hasColumn('financial_accounts', 'is_card_invoice_payable')) {
                $table->boolean('is_card_invoice_payable')->default(false)->after('is_card_purchase');
            }

            if (! Schema::hasColumn('financial_accounts', 'allocation_mode')) {
                $table->string('allocation_mode')->default('single')->after('is_card_invoice_payable');
            }
        });

        Schema::table('financial_accounts', function (Blueprint $table): void {
            if (Schema::hasColumn('financial_accounts', 'company_id')) {
                $table->index(['tenant_id', 'company_id'], 'financial_accounts_tenant_company_idx');
            }

            if (Schema::hasColumn('financial_accounts', 'cost_center_id')) {
                $table->index(['tenant_id', 'cost_center_id'], 'financial_accounts_tenant_cost_center_idx');
            }

            if (Schema::hasColumn('financial_accounts', 'credit_card_id')) {
                $table->index(['tenant_id', 'credit_card_id'], 'financial_accounts_tenant_credit_card_idx');
            }

            if (Schema::hasColumn('financial_accounts', 'credit_card_invoice_id')) {
                $table->index(['tenant_id', 'credit_card_invoice_id'], 'financial_accounts_tenant_credit_card_invoice_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('financial_accounts', function (Blueprint $table): void {
            foreach ([
                'financial_accounts_tenant_credit_card_invoice_idx',
                'financial_accounts_tenant_credit_card_idx',
                'financial_accounts_tenant_cost_center_idx',
                'financial_accounts_tenant_company_idx',
            ] as $index) {
                try {
                    $table->dropIndex($index);
                } catch (\Throwable) {
                }
            }

            foreach ([
                'allocation_mode',
                'is_card_invoice_payable',
                'is_card_purchase',
                'credit_card_invoice_id',
                'credit_card_id',
                'cost_center_id',
                'company_id',
            ] as $column) {
                if (Schema::hasColumn('financial_accounts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (Schema::hasColumn('financial_accounts', 'bank_account_id') && ! Schema::hasColumn('financial_accounts', 'cost_center_id')) {
            Schema::table('financial_accounts', function (Blueprint $table): void {
                $table->renameColumn('bank_account_id', 'cost_center_id');
            });
        }
    }
};
