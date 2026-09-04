<?php

namespace App\Modules\Audit\Enums;

enum AuditAction: string
{
    case MasterLogin = 'master_login';
    case TenantSelected = 'tenant_selected';
    case TenantSwitched = 'tenant_switched';

    case FinancialCreate = 'financial.create';
    case FinancialUpdate = 'financial.update';
    case FinancialDelete = 'financial.delete';

    case AccountSettle = 'account.settle';
    case AccountUnsettle = 'account.unsettle';
    case AccountReopen = 'account.reopen';

    case InstallmentGenerate = 'installment.generate';
    case RecurrenceGenerate = 'recurrence.generate';

    case ReconciliationExecute = 'reconciliation.execute';
    case ReconciliationUndo = 'reconciliation.undo';
}
