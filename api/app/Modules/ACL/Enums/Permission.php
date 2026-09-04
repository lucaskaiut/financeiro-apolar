<?php

namespace App\Modules\ACL\Enums;

enum Permission: string
{
    case USER_CREATE = 'user.create';
    case USER_READ = 'user.read';
    case USER_UPDATE = 'user.update';
    case USER_DELETE = 'user.delete';

    case TENANT_READ = 'tenant.read';
    case TENANT_UPDATE = 'tenant.update';

    case ROLE_CREATE = 'role.create';
    case ROLE_READ = 'role.read';
    case ROLE_UPDATE = 'role.update';
    case ROLE_DELETE = 'role.delete';

    case BANK_ACCOUNTS_VIEW = 'bank_accounts.view';
    case BANK_ACCOUNTS_CREATE = 'bank_accounts.create';
    case BANK_ACCOUNTS_UPDATE = 'bank_accounts.update';
    case BANK_ACCOUNTS_DELETE = 'bank_accounts.delete';

    case COST_CENTERS_VIEW = 'cost_centers.view';
    case COST_CENTERS_CREATE = 'cost_centers.create';
    case COST_CENTERS_UPDATE = 'cost_centers.update';
    case COST_CENTERS_DELETE = 'cost_centers.delete';

    case COMPANIES_VIEW = 'companies.view';
    case COMPANIES_CREATE = 'companies.create';
    case COMPANIES_UPDATE = 'companies.update';
    case COMPANIES_DELETE = 'companies.delete';

    case CREDIT_CARDS_VIEW = 'credit_cards.view';
    case CREDIT_CARDS_CREATE = 'credit_cards.create';
    case CREDIT_CARDS_UPDATE = 'credit_cards.update';
    case CREDIT_CARDS_DELETE = 'credit_cards.delete';

    case CATEGORIES_VIEW = 'categories.view';
    case CATEGORIES_CREATE = 'categories.create';
    case CATEGORIES_UPDATE = 'categories.update';
    case CATEGORIES_DELETE = 'categories.delete';

    case ACCOUNTS_VIEW = 'accounts.view';
    case ACCOUNTS_CREATE = 'accounts.create';
    case ACCOUNTS_UPDATE = 'accounts.update';
    case ACCOUNTS_DELETE = 'accounts.delete';
    case ACCOUNTS_SETTLE = 'accounts.settle';

    case RECURRENCES_VIEW = 'recurrences.view';
    case RECURRENCES_CREATE = 'recurrences.create';
    case RECURRENCES_UPDATE = 'recurrences.update';
    case RECURRENCES_DELETE = 'recurrences.delete';

    case TRANSFERS_VIEW = 'transfers.view';
    case TRANSFERS_CREATE = 'transfers.create';
    case TRANSFERS_DELETE = 'transfers.delete';

    case CASH_FLOW_VIEW = 'cash_flow.view';

    case RECONCILIATION_VIEW = 'reconciliation.view';
    case RECONCILIATION_EXECUTE = 'reconciliation.execute';
    case RECONCILIATION_UNDO = 'reconciliation.undo';

    case REPORTS_VIEW = 'reports.view';
    case REPORTS_EXPORT = 'reports.export';

    case AUDIT_VIEW = 'audit.view';

    case ASSISTANT_VIEW = 'assistant.view';
    case ASSISTANT_CONFIGURE = 'assistant.configure';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Permissões de leitura (somente visualização) de todo o sistema.
     *
     * @return list<self>
     */
    public static function viewOnly(): array
    {
        return [
            self::USER_READ,
            self::TENANT_READ,
            self::ROLE_READ,
            self::BANK_ACCOUNTS_VIEW,
            self::COST_CENTERS_VIEW,
            self::COMPANIES_VIEW,
            self::CREDIT_CARDS_VIEW,
            self::CATEGORIES_VIEW,
            self::ACCOUNTS_VIEW,
            self::RECURRENCES_VIEW,
            self::TRANSFERS_VIEW,
            self::CASH_FLOW_VIEW,
            self::RECONCILIATION_VIEW,
            self::REPORTS_VIEW,
            self::ASSISTANT_VIEW,
        ];
    }
}
