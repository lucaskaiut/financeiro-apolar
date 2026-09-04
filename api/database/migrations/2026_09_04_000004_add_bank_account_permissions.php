<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private array $permissionMap = [
        'cost_centers.view' => 'bank_accounts.view',
        'cost_centers.create' => 'bank_accounts.create',
        'cost_centers.update' => 'bank_accounts.update',
        'cost_centers.delete' => 'bank_accounts.delete',
    ];

    /**
     * @var list<string>
     */
    private array $newPermissions = [
        'bank_accounts.view',
        'bank_accounts.create',
        'bank_accounts.update',
        'bank_accounts.delete',
        'companies.view',
        'companies.create',
        'companies.update',
        'companies.delete',
        'credit_cards.view',
        'credit_cards.create',
        'credit_cards.update',
        'credit_cards.delete',
    ];

    public function up(): void
    {
        foreach ($this->permissionMap as $old => $new) {
            $roles = DB::table('role_permissions')
                ->where('permission', $old)
                ->pluck('role_id')
                ->unique();

            foreach ($roles as $roleId) {
                $exists = DB::table('role_permissions')
                    ->where('role_id', $roleId)
                    ->where('permission', $new)
                    ->exists();

                if (! $exists) {
                    DB::table('role_permissions')->insert([
                        'role_id' => $roleId,
                        'permission' => $new,
                    ]);
                }
            }
        }

        $adminRoles = DB::table('roles')
            ->where('name', 'Administrador')
            ->pluck('id');

        foreach ($adminRoles as $roleId) {
            foreach ($this->newPermissions as $permission) {
                $exists = DB::table('role_permissions')
                    ->where('role_id', $roleId)
                    ->where('permission', $permission)
                    ->exists();

                if (! $exists) {
                    DB::table('role_permissions')->insert([
                        'role_id' => $roleId,
                        'permission' => $permission,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->whereIn('permission', $this->newPermissions)->delete();
    }
};
