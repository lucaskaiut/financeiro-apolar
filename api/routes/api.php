<?php

use App\Modules\Account\Http\Controllers\AccountController;
use App\Modules\ACL\Http\Controllers\RoleController;
use App\Modules\Assistant\Http\Controllers\AiSettingsController;
use App\Modules\Assistant\Http\Controllers\ChatController;
use App\Modules\Assistant\Http\Controllers\ConversationController;
use App\Modules\Audit\Http\Controllers\AuditLogController;
use App\Modules\Auth\Http\Controllers\AuthController;
use App\Modules\CashFlow\Http\Controllers\CashFlowController;
use App\Modules\Category\Http\Controllers\CategoryController;
use App\Modules\CostCenter\Http\Controllers\CostCenterController;
use App\Modules\Dashboard\Http\Controllers\DashboardController;
use App\Modules\Reconciliation\Http\Controllers\ReconciliationController;
use App\Modules\Recurrence\Http\Controllers\RecurrenceController;
use App\Modules\Report\Http\Controllers\ReportController;
use App\Modules\Shared\Http\Controllers\FileUploadController;
use App\Modules\Tenant\Http\Controllers\TenantController;
use App\Modules\Transfer\Http\Controllers\TransferController;
use App\Modules\User\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');

    Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('select-tenant', [AuthController::class, 'selectTenant']);
    });
});

Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    Route::get('tenant', [TenantController::class, 'show'])->middleware('permission:tenant.read');
    Route::match(['put', 'patch'], 'tenant', [TenantController::class, 'update'])->middleware('permission:tenant.update');

    Route::get('users', [UserController::class, 'index'])->middleware('permission:user.read');
    Route::post('users', [UserController::class, 'store'])->middleware('permission:user.create');
    Route::get('users/{user}', [UserController::class, 'show'])->middleware('permission:user.read');
    Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update'])->middleware('permission:user.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('permission:user.delete');

    Route::get('roles', [RoleController::class, 'index'])->middleware('permission:role.read');
    Route::post('roles', [RoleController::class, 'store'])->middleware('permission:role.create');
    Route::get('roles/{role}', [RoleController::class, 'show'])->middleware('permission:role.read');
    Route::match(['put', 'patch'], 'roles/{role}', [RoleController::class, 'update'])->middleware('permission:role.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:role.delete');

    Route::post('uploads', FileUploadController::class);

    // Dashboard financeiro
    Route::get('dashboard', [DashboardController::class, 'summary']);

    // Centros de custo (contas bancárias)
    Route::get('cost-centers', [CostCenterController::class, 'index'])->middleware('permission:cost_centers.view');
    Route::post('cost-centers', [CostCenterController::class, 'store'])->middleware('permission:cost_centers.create');
    Route::get('cost-centers/{cost_center}', [CostCenterController::class, 'show'])->middleware('permission:cost_centers.view');
    Route::match(['put', 'patch'], 'cost-centers/{cost_center}', [CostCenterController::class, 'update'])->middleware('permission:cost_centers.update');
    Route::delete('cost-centers/{cost_center}', [CostCenterController::class, 'destroy'])->middleware('permission:cost_centers.delete');

    // Categorias financeiras
    Route::get('categories', [CategoryController::class, 'index'])->middleware('permission:categories.view');
    Route::post('categories', [CategoryController::class, 'store'])->middleware('permission:categories.create');
    Route::get('categories/{category}', [CategoryController::class, 'show'])->middleware('permission:categories.view');
    Route::match(['put', 'patch'], 'categories/{category}', [CategoryController::class, 'update'])->middleware('permission:categories.update');
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->middleware('permission:categories.delete');

    // Contas a pagar / receber
    Route::get('accounts', [AccountController::class, 'index'])->middleware('permission:accounts.view');
    Route::post('accounts', [AccountController::class, 'store'])->middleware('permission:accounts.create');
    Route::post('accounts/import', [AccountController::class, 'import'])->middleware('permission:accounts.create');
    Route::get('accounts/{account}', [AccountController::class, 'show'])->middleware('permission:accounts.view');
    Route::match(['put', 'patch'], 'accounts/{account}', [AccountController::class, 'update'])->middleware('permission:accounts.update');
    Route::delete('accounts/{account}', [AccountController::class, 'destroy'])->middleware('permission:accounts.delete');
    Route::post('accounts/{account}/settle', [AccountController::class, 'settle'])->middleware('permission:accounts.settle');
    Route::delete('accounts/{account}/settlements/{settlement}', [AccountController::class, 'unsettle'])->middleware('permission:accounts.settle');
    Route::post('accounts/{account}/reopen', [AccountController::class, 'reopen'])->middleware('permission:accounts.settle');
    Route::post('accounts/{account}/cancel', [AccountController::class, 'cancel'])->middleware('permission:accounts.update');
    Route::get('accounts/{account}/documents', [AccountController::class, 'indexDocuments'])->middleware('permission:accounts.view');
    Route::post('accounts/{account}/documents', [AccountController::class, 'storeDocuments'])->middleware('permission:accounts.update');
    Route::get('accounts/{account}/documents/{document}/download', [AccountController::class, 'downloadDocument'])->middleware('permission:accounts.view');
    Route::delete('accounts/{account}/documents/{document}', [AccountController::class, 'destroyDocument'])->middleware('permission:accounts.update');

    // Recorrências
    Route::get('recurrences', [RecurrenceController::class, 'index'])->middleware('permission:recurrences.view');
    Route::post('recurrences', [RecurrenceController::class, 'store'])->middleware('permission:recurrences.create');
    Route::get('recurrences/{recurrence}', [RecurrenceController::class, 'show'])->middleware('permission:recurrences.view');
    Route::match(['put', 'patch'], 'recurrences/{recurrence}', [RecurrenceController::class, 'update'])->middleware('permission:recurrences.update');
    Route::delete('recurrences/{recurrence}', [RecurrenceController::class, 'destroy'])->middleware('permission:recurrences.delete');

    // Transferências entre contas
    Route::get('transfers', [TransferController::class, 'index'])->middleware('permission:transfers.view');
    Route::post('transfers', [TransferController::class, 'store'])->middleware('permission:transfers.create');
    Route::delete('transfers/{transfer}', [TransferController::class, 'destroy'])->middleware('permission:transfers.delete');

    // Fluxo de caixa
    Route::get('cash-flow/realized', [CashFlowController::class, 'realized'])->middleware('permission:cash_flow.view');
    Route::get('cash-flow/projected', [CashFlowController::class, 'projected'])->middleware('permission:cash_flow.view');

    // Conciliação bancária
    Route::get('reconciliation/transactions', [ReconciliationController::class, 'index'])->middleware('permission:reconciliation.view');
    Route::post('reconciliation/import', [ReconciliationController::class, 'import'])->middleware('permission:reconciliation.execute');
    Route::post('reconciliation/auto', [ReconciliationController::class, 'autoReconcile'])->middleware('permission:reconciliation.execute');
    Route::post('reconciliation/reconcile-many', [ReconciliationController::class, 'reconcileMany'])->middleware('permission:reconciliation.execute');
    Route::get('reconciliation/transactions/{transaction}/candidates', [ReconciliationController::class, 'candidates'])->middleware('permission:reconciliation.view');
    Route::post('reconciliation/transactions/{transaction}/reconcile', [ReconciliationController::class, 'reconcile'])->middleware('permission:reconciliation.execute');
    Route::post('reconciliation/transactions/{transaction}/ignore', [ReconciliationController::class, 'ignore'])->middleware('permission:reconciliation.execute');
    Route::post('reconciliation/transactions/{transaction}/create-account', [ReconciliationController::class, 'createAccount'])->middleware('permission:reconciliation.execute');
    Route::post('reconciliation/transactions/{transaction}/undo', [ReconciliationController::class, 'undo'])->middleware('permission:reconciliation.undo');

    // Relatórios
    Route::get('reports/daily', [ReportController::class, 'daily'])->middleware('permission:reports.view');
    Route::get('reports/daily/export', [ReportController::class, 'dailyExport'])->middleware('permission:reports.export');
    Route::get('reports/weekly', [ReportController::class, 'weekly'])->middleware('permission:reports.view');
    Route::get('reports/weekly/export', [ReportController::class, 'weeklyExport'])->middleware('permission:reports.export');
    Route::get('reports/provision', [ReportController::class, 'provision'])->middleware('permission:reports.view');
    Route::get('reports/provision/export', [ReportController::class, 'provisionExport'])->middleware('permission:reports.export');
    Route::get('reports/by-category', [ReportController::class, 'byCategory'])->middleware('permission:reports.view');
    Route::get('reports/by-category/export', [ReportController::class, 'byCategoryExport'])->middleware('permission:reports.export');
    Route::get('reports/monthly-summary', [ReportController::class, 'monthlySummary'])->middleware('permission:reports.view');
    Route::get('reports/monthly-summary/export', [ReportController::class, 'monthlySummaryExport'])->middleware('permission:reports.export');
    Route::get('reports/by-cost-center', [ReportController::class, 'byCostCenter'])->middleware('permission:reports.view');
    Route::get('reports/by-cost-center/export', [ReportController::class, 'byCostCenterExport'])->middleware('permission:reports.export');
    Route::get('reports/cash-flow', [ReportController::class, 'cashFlow'])->middleware('permission:reports.view');
    Route::get('reports/cash-flow/export', [ReportController::class, 'cashFlowExport'])->middleware('permission:reports.export');
    Route::get('reports/payables', [ReportController::class, 'payables'])->middleware('permission:reports.view');
    Route::get('reports/payables/export', [ReportController::class, 'payablesExport'])->middleware('permission:reports.export');

    // Auditoria
    Route::get('audit', [AuditLogController::class, 'index'])->middleware('permission:audit.view');

    // Assistente financeiro com IA
    Route::get('assistant/suggestions', [ConversationController::class, 'suggestions'])->middleware('permission:assistant.view');
    Route::get('assistant/conversations', [ConversationController::class, 'index'])->middleware('permission:assistant.view');
    Route::post('assistant/conversations', [ConversationController::class, 'store'])->middleware('permission:assistant.view');
    Route::get('assistant/conversations/{conversation}', [ConversationController::class, 'show'])->middleware('permission:assistant.view');
    Route::match(['put', 'patch'], 'assistant/conversations/{conversation}', [ConversationController::class, 'update'])->middleware('permission:assistant.view');
    Route::delete('assistant/conversations/{conversation}', [ConversationController::class, 'destroy'])->middleware('permission:assistant.view');
    Route::post('assistant/conversations/{conversation}/messages', [ChatController::class, 'send'])->middleware('permission:assistant.view');

    Route::get('assistant/settings', [AiSettingsController::class, 'show'])->middleware('permission:assistant.configure');
    Route::match(['put', 'patch'], 'assistant/settings', [AiSettingsController::class, 'update'])->middleware('permission:assistant.configure');
    Route::post('assistant/settings/test', [AiSettingsController::class, 'test'])->middleware('permission:assistant.configure');
});
