<?php

namespace App\Modules\ACL\Enums;

enum DefaultRole: string
{
    case ADMINISTRATOR = 'Administrador';
    case FINANCEIRO = 'Financeiro';
    case GESTOR_FINANCEIRO = 'Gestor Financeiro';
    case AUDITOR = 'Auditor';
    case CONSULTA = 'Consulta';

    public function description(): string
    {
        return match ($this) {
            self::ADMINISTRATOR => 'Acesso completo ao tenant.',
            self::FINANCEIRO => 'Operação financeira diária.',
            self::GESTOR_FINANCEIRO => 'Operação, relatórios e conciliações.',
            self::AUDITOR => 'Somente leitura e auditoria.',
            self::CONSULTA => 'Somente leitura.',
        };
    }

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::ADMINISTRATOR => Permission::cases(),
            self::FINANCEIRO => [
                ...Permission::viewOnly(),
                ...self::operational(),
            ],
            self::GESTOR_FINANCEIRO => [
                ...Permission::viewOnly(),
                ...self::operational(),
                Permission::RECONCILIATION_EXECUTE,
                Permission::RECONCILIATION_UNDO,
                Permission::REPORTS_EXPORT,
            ],
            self::AUDITOR => [
                ...Permission::viewOnly(),
                Permission::AUDIT_VIEW,
            ],
            self::CONSULTA => Permission::viewOnly(),
        };
    }

    /**
     * Permissões de mutação da operação financeira diária.
     *
     * @return list<Permission>
     */
    private static function operational(): array
    {
        return [
            Permission::COST_CENTERS_CREATE,
            Permission::COST_CENTERS_UPDATE,
            Permission::COST_CENTERS_DELETE,
            Permission::CATEGORIES_CREATE,
            Permission::CATEGORIES_UPDATE,
            Permission::CATEGORIES_DELETE,
            Permission::ACCOUNTS_CREATE,
            Permission::ACCOUNTS_UPDATE,
            Permission::ACCOUNTS_DELETE,
            Permission::ACCOUNTS_SETTLE,
            Permission::RECURRENCES_CREATE,
            Permission::RECURRENCES_UPDATE,
            Permission::RECURRENCES_DELETE,
            Permission::TRANSFERS_CREATE,
            Permission::TRANSFERS_DELETE,
        ];
    }
}
