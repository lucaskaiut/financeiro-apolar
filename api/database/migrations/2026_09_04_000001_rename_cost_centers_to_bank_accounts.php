<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cost_centers') && ! Schema::hasTable('bank_accounts')) {
            Schema::rename('cost_centers', 'bank_accounts');
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite' && Schema::hasTable('bank_accounts')) {
            $indexes = [
                'cost_centers_tenant_id_status_index' => 'bank_accounts_tenant_id_status_index',
                'cost_centers_uuid_unique' => 'bank_accounts_uuid_unique',
            ];

            foreach ($indexes as $from => $to) {
                try {
                    DB::statement("ALTER INDEX \"{$from}\" RENAME TO \"{$to}\"");
                } catch (\Throwable) {
                }
            }
        }

        if (Schema::hasTable('bank_accounts')) {
            $this->renameForeignKey('bank_accounts', 'cost_centers_tenant_id_foreign', 'bank_accounts_tenant_id_foreign');
        }

        if (Schema::hasColumn('financial_accounts', 'cost_center_id') && ! Schema::hasColumn('financial_accounts', 'bank_account_id')) {
            Schema::table('financial_accounts', function (Blueprint $table): void {
                $table->renameColumn('cost_center_id', 'bank_account_id');
            });
        }

        if (Schema::hasColumn('bank_transactions', 'cost_center_id') && ! Schema::hasColumn('bank_transactions', 'bank_account_id')) {
            Schema::table('bank_transactions', function (Blueprint $table): void {
                $table->renameColumn('cost_center_id', 'bank_account_id');
            });
        }

        if (Schema::hasColumn('recurrences', 'cost_center_id') && ! Schema::hasColumn('recurrences', 'bank_account_id')) {
            Schema::table('recurrences', function (Blueprint $table): void {
                $table->renameColumn('cost_center_id', 'bank_account_id');
            });
        }

        if (Schema::hasColumn('transfers', 'from_cost_center_id') && ! Schema::hasColumn('transfers', 'from_bank_account_id')) {
            Schema::table('transfers', function (Blueprint $table): void {
                $table->renameColumn('from_cost_center_id', 'from_bank_account_id');
                $table->renameColumn('to_cost_center_id', 'to_bank_account_id');
            });
        }

        if (Schema::hasTable('account_imports') && Schema::hasColumn('account_imports', 'cost_center_id') && ! Schema::hasColumn('account_imports', 'bank_account_id')) {
            Schema::table('account_imports', function (Blueprint $table): void {
                $table->renameColumn('cost_center_id', 'bank_account_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('account_imports') && Schema::hasColumn('account_imports', 'bank_account_id')) {
            Schema::table('account_imports', function (Blueprint $table): void {
                $table->renameColumn('bank_account_id', 'cost_center_id');
            });
        }

        if (Schema::hasColumn('transfers', 'from_bank_account_id')) {
            Schema::table('transfers', function (Blueprint $table): void {
                $table->renameColumn('from_bank_account_id', 'from_cost_center_id');
                $table->renameColumn('to_bank_account_id', 'to_cost_center_id');
            });
        }

        if (Schema::hasColumn('recurrences', 'bank_account_id')) {
            Schema::table('recurrences', function (Blueprint $table): void {
                $table->renameColumn('bank_account_id', 'cost_center_id');
            });
        }

        if (Schema::hasColumn('bank_transactions', 'bank_account_id')) {
            Schema::table('bank_transactions', function (Blueprint $table): void {
                $table->renameColumn('bank_account_id', 'cost_center_id');
            });
        }

        if (Schema::hasColumn('financial_accounts', 'bank_account_id') && ! Schema::hasColumn('financial_accounts', 'cost_center_id')) {
            Schema::table('financial_accounts', function (Blueprint $table): void {
                $table->renameColumn('bank_account_id', 'cost_center_id');
            });
        }

        if (Schema::hasTable('bank_accounts')) {
            $this->renameForeignKey('bank_accounts', 'bank_accounts_tenant_id_foreign', 'cost_centers_tenant_id_foreign');
        }

        if (Schema::hasTable('bank_accounts') && ! Schema::hasTable('cost_centers')) {
            Schema::rename('bank_accounts', 'cost_centers');
        }
    }

    private function renameForeignKey(string $table, string $from, string $to): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $database = Schema::getConnection()->getDatabaseName();

        $targetExists = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $to)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();

        if ($targetExists) {
            return;
        }

        $foreignKey = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $from)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->first(['COLUMN_NAME', 'REFERENCED_TABLE_NAME', 'REFERENCED_COLUMN_NAME']);

        if ($foreignKey === null) {
            return;
        }

        DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$from}`");
        DB::statement(sprintf(
            'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`)',
            $table,
            $to,
            $foreignKey->COLUMN_NAME,
            $foreignKey->REFERENCED_TABLE_NAME,
            $foreignKey->REFERENCED_COLUMN_NAME,
        ));
    }
};
