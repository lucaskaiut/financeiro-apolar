<?php

namespace App\Modules\Assistant\Services;

use App\Modules\Account\Enums\AccountStatus;
use App\Modules\Account\Enums\AccountType;
use App\Modules\Account\Models\FinancialAccount;
use App\Modules\Account\Models\Settlement;
use App\Modules\Account\Services\AccountService;
use App\Modules\Assistant\Support\Tool;
use App\Modules\Assistant\Support\ToolRegistry;
use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\CashFlow\Services\CashFlowService;
use App\Modules\Category\Models\Category;
use App\Modules\CostCenter\Models\CostCenter;
use App\Modules\Reconciliation\Models\BankTransaction;
use App\Modules\Reconciliation\Services\ReconciliationService;
use App\Modules\Recurrence\Services\RecurrenceService;
use App\Modules\User\Models\User;
use Carbon\Carbon;

/**
 * Catálogo de ferramentas financeiras expostas ao modelo.
 *
 * O modelo nunca acessa banco de dados diretamente — toda consulta ou
 * mutação passa por uma ferramenta registrada aqui.
 */
final class AssistantTools
{
    public function __construct(
        private readonly AccountService $accounts,
        private readonly RecurrenceService $recurrences,
        private readonly ReconciliationService $reconciliation,
        private readonly CashFlowService $cashFlow,
        private readonly AuditLogService $audit,
    ) {}

    public function build(User $user): ToolRegistry
    {
        $registry = new ToolRegistry;

        $registry
            ->register($this->financialSummary())
            ->register($this->listCostCenters())
            ->register($this->listCategories())
            ->register($this->queryAccounts())
            ->register($this->cashFlowRealized())
            ->register($this->cashFlowProjected())
            ->register($this->queryPayables())
            ->register($this->queryReceivables())
            ->register($this->createExpense($user))
            ->register($this->createIncome($user))
            ->register($this->createInstallment($user))
            ->register($this->createRecurrence($user))
            ->register($this->queryReconciliation())
            ->register($this->queryAudit());

        return $registry;
    }

    private function financialSummary(): Tool
    {
        return new Tool(
            name: 'get_financial_summary',
            description: 'Resumo financeiro geral do tenant: saldo atual, receitas e despesas do mês, '
                .'contas a receber e a pagar em aberto e total vencido. Use para responder perguntas '
                .'como "qual meu saldo?" ou "como está minha situação financeira?".',
            parameters: [
                'cost_center_id' => ['type' => 'string', 'description' => 'UUID opcional do centro de custo.'],
            ],
            handler: function (array $args): array {
                $cc = $args['cost_center_id'] ?? null;

                $today = now()->startOfDay();
                $monthStart = now()->startOfMonth();
                $monthEnd = now()->endOfMonth();

                $open = FinancialAccount::query()
                    ->whereIn('status', [AccountStatus::Open->value, AccountStatus::Partial->value])
                    ->when($cc, fn ($q) => $q->where('cost_center_id', $cc))
                    ->withSum('settlements', 'value')
                    ->get();

                $receivable = $open->where('type', AccountType::Receivable);
                $payable = $open->where('type', AccountType::Payable);
                $overdue = $open->where('due_date', '<', $today->toDateString());

                $remaining = fn ($a) => round((float) $a->value - (float) ($a->settlements_sum_value ?? 0), 2);

                return [
                    'saldo_atual' => $this->currentBalance($cc),
                    'receitas_do_mes' => $this->settledBetween($monthStart, $monthEnd, AccountType::Receivable, $cc),
                    'despesas_do_mes' => $this->settledBetween($monthStart, $monthEnd, AccountType::Payable, $cc),
                    'resultado_do_mes' => round(
                        $this->settledBetween($monthStart, $monthEnd, AccountType::Receivable, $cc)
                        - $this->settledBetween($monthStart, $monthEnd, AccountType::Payable, $cc),
                        2,
                    ),
                    'contas_a_receber_abertas' => [
                        'quantidade' => $receivable->count(),
                        'total' => round($receivable->sum($remaining), 2),
                    ],
                    'contas_a_pagar_abertas' => [
                        'quantidade' => $payable->count(),
                        'total' => round($payable->sum($remaining), 2),
                    ],
                    'vencidas' => [
                        'quantidade' => $overdue->count(),
                        'total' => round($overdue->sum($remaining), 2),
                    ],
                ];
            },
        );
    }

    private function listCostCenters(): Tool
    {
        return new Tool(
            name: 'list_cost_centers',
            description: 'Lista os centros de custo (contas bancárias) do tenant. Útil para obter o ID correto antes de criar lançamentos.',
            parameters: [],
            handler: fn (): array => CostCenter::query()
                ->orderBy('name')
                ->get(['uuid', 'name', 'bank'])
                ->map(fn (CostCenter $cc) => ['id' => $cc->uuid, 'name' => $cc->name, 'banco' => $cc->bank])
                ->all(),
        );
    }

    private function listCategories(): Tool
    {
        return new Tool(
            name: 'list_categories',
            description: 'Lista as categorias financeiras do tenant. Útil para obter o ID correto antes de criar lançamentos.',
            parameters: [
                'type' => ['type' => 'string', 'enum' => ['income', 'expense'], 'description' => 'Filtro opcional por tipo.'],
            ],
            handler: fn (array $args): array => Category::query()
                ->when(isset($args['type']), fn ($q) => $q->where('type', $args['type']))
                ->orderBy('name')
                ->get(['uuid', 'name', 'type'])
                ->map(fn (Category $c) => ['id' => $c->uuid, 'name' => $c->name, 'type' => $c->type?->value])
                ->all(),
        );
    }

    private function queryAccounts(): Tool
    {
        return new Tool(
            name: 'query_accounts',
            description: 'Consulta lançamentos (contas a pagar e a receber) com filtros por tipo, status e período de vencimento.',
            parameters: [
                'type' => ['type' => 'string', 'enum' => ['payable', 'receivable'], 'description' => 'Tipo: payable (a pagar) ou receivable (a receber).'],
                'status' => ['type' => 'string', 'enum' => ['open', 'partial', 'settled', 'cancelled', 'overdue'], 'description' => 'Status. "overdue" lista vencidas em aberto.'],
                'due_from' => ['type' => 'string', 'description' => 'Data inicial (YYYY-MM-DD) do vencimento.'],
                'due_to' => ['type' => 'string', 'description' => 'Data final (YYYY-MM-DD) do vencimento.'],
                'search' => ['type' => 'string', 'description' => 'Busca por descrição ou contraparte.'],
                'limit' => ['type' => 'integer', 'description' => 'Máximo de resultados (padrão 20).'],
            ],
            handler: function (array $args): array {
                $query = FinancialAccount::query()
                    ->with(['costCenter:id,uuid,name', 'category:id,uuid,name'])
                    ->withSum('settlements', 'value');

                $query->when(isset($args['type']), fn ($q) => $q->where('type', $args['type']));

                if (($args['status'] ?? null) === 'overdue') {
                    $query->whereIn('status', [AccountStatus::Open->value, AccountStatus::Partial->value])
                        ->whereDate('due_date', '<', now()->toDateString());
                } else {
                    $query->when(isset($args['status']), fn ($q) => $q->where('status', $args['status']));
                }

                $query->when(isset($args['due_from']), fn ($q) => $q->whereDate('due_date', '>=', $args['due_from']));
                $query->when(isset($args['due_to']), fn ($q) => $q->whereDate('due_date', '<=', $args['due_to']));
                $query->when(filled($args['search'] ?? null), function ($q) use ($args): void {
                    $search = $args['search'];
                    $q->where(fn ($q) => $q->where('description', 'like', "%{$search}%")->orWhere('counterparty', 'like', "%{$search}%"));
                });

                $limit = min(max((int) ($args['limit'] ?? 20), 1), 100);

                return $query
                    ->orderBy('due_date')
                    ->limit($limit)
                    ->get()
                    ->map(fn (FinancialAccount $a) => $this->presentAccount($a))
                    ->all();
            },
        );
    }

    private function queryPayables(): Tool
    {
        return new Tool(
            name: 'query_payables',
            description: 'Consulta contas a pagar por situação: vencidas, em aberto ou pagas.',
            parameters: [
                'status' => ['type' => 'string', 'enum' => ['overdue', 'open', 'settled'], 'description' => 'overdue = vencidas; open = a vencer; settled = pagas.'],
                'limit' => ['type' => 'integer', 'description' => 'Máximo de resultados (padrão 20).'],
            ],
            handler: function (array $args): array {
                $status = $args['status'] ?? 'open';
                $query = FinancialAccount::query()
                    ->with(['costCenter:id,uuid,name', 'category:id,uuid,name'])
                    ->withSum('settlements', 'value')
                    ->where('type', AccountType::Payable);

                if ($status === 'overdue') {
                    $query->whereIn('status', [AccountStatus::Open->value, AccountStatus::Partial->value])
                        ->whereDate('due_date', '<', now()->toDateString());
                } elseif ($status === 'settled') {
                    $query->where('status', AccountStatus::Settled->value);
                } else {
                    $query->whereIn('status', [AccountStatus::Open->value, AccountStatus::Partial->value])
                        ->whereDate('due_date', '>=', now()->toDateString());
                }

                $limit = min(max((int) ($args['limit'] ?? 20), 1), 100);

                return $query->orderBy('due_date')->limit($limit)->get()
                    ->map(fn (FinancialAccount $a) => $this->presentAccount($a))
                    ->all();
            },
        );
    }

    private function queryReceivables(): Tool
    {
        return new Tool(
            name: 'query_receivables',
            description: 'Consulta contas a receber por situação: atrasadas, em aberto ou recebidas.',
            parameters: [
                'status' => ['type' => 'string', 'enum' => ['overdue', 'open', 'settled'], 'description' => 'overdue = atrasadas; open = a receber; settled = recebidas.'],
                'limit' => ['type' => 'integer', 'description' => 'Máximo de resultados (padrão 20).'],
            ],
            handler: function (array $args): array {
                $status = $args['status'] ?? 'open';
                $query = FinancialAccount::query()
                    ->with(['costCenter:id,uuid,name', 'category:id,uuid,name'])
                    ->withSum('settlements', 'value')
                    ->where('type', AccountType::Receivable);

                if ($status === 'overdue') {
                    $query->whereIn('status', [AccountStatus::Open->value, AccountStatus::Partial->value])
                        ->whereDate('due_date', '<', now()->toDateString());
                } elseif ($status === 'settled') {
                    $query->where('status', AccountStatus::Settled->value);
                } else {
                    $query->whereIn('status', [AccountStatus::Open->value, AccountStatus::Partial->value])
                        ->whereDate('due_date', '>=', now()->toDateString());
                }

                $limit = min(max((int) ($args['limit'] ?? 20), 1), 100);

                return $query->orderBy('due_date')->limit($limit)->get()
                    ->map(fn (FinancialAccount $a) => $this->presentAccount($a))
                    ->all();
            },
        );
    }

    private function cashFlowRealized(): Tool
    {
        return new Tool(
            name: 'get_cash_flow_realized',
            description: 'Fluxo de caixa realizado (entradas e saídas efetivadas) em um período.',
            parameters: [
                'from' => ['type' => 'string', 'description' => 'Data inicial (YYYY-MM-DD).'],
                'to' => ['type' => 'string', 'description' => 'Data final (YYYY-MM-DD).'],
                'cost_center_id' => ['type' => 'string', 'description' => 'UUID opcional do centro de custo.'],
            ],
            handler: fn (array $args): array => $this->cashFlow->realized(
                $args['from'] ?? null,
                $args['to'] ?? null,
                $args['cost_center_id'] ?? null,
            ),
        );
    }

    private function cashFlowProjected(): Tool
    {
        return new Tool(
            name: 'get_cash_flow_projected',
            description: 'Fluxo de caixa projetado (previsão de saldo futuro com base em contas, parcelas e recorrências).',
            parameters: [
                'days' => ['type' => 'integer', 'description' => 'Horizonte em dias (7, 15, 30, 60, 90, 180, 365).'],
                'cost_center_id' => ['type' => 'string', 'description' => 'UUID opcional do centro de custo.'],
            ],
            handler: fn (array $args): array => $this->cashFlow->projected(
                null,
                null,
                min(max((int) ($args['days'] ?? 30), 1), 365),
                $args['cost_center_id'] ?? null,
            ),
        );
    }

    private function createExpense(User $user): Tool
    {
        return $this->createAccountTool($user, 'create_expense', 'payable', 'Cria uma despesa (conta a pagar).');
    }

    private function createIncome(User $user): Tool
    {
        return $this->createAccountTool($user, 'create_income', 'receivable', 'Cria uma receita (conta a receber).');
    }

    private function createInstallment(User $user): Tool
    {
        return new Tool(
            name: 'create_installment',
            description: 'Cria um lançamento parcelado, gerando automaticamente as parcelas. '
                .'Use para dividir uma compra em N vezes. Ex.: 12x de R$ 1.000,00.',
            parameters: $this->accountWriteParameters(),
            writes: true,
            handler: function (array $args) use ($user): array {
                $args['type'] = $args['type'] ?? 'payable';
                $args['installments'] = [
                    'quantity' => (int) ($args['installments']['quantity'] ?? 1),
                    'interval' => $args['installments']['interval'] ?? 'monthly',
                ];

                $accounts = $this->accounts->create($this->accountPayload($args));

                $this->auditWrite($user, AuditAction::InstallmentGenerate, $accounts);

                return [
                    'criado' => true,
                    'parcelas' => count($accounts),
                    'lancamentos' => array_map(fn (FinancialAccount $a) => $this->presentAccount($a), $accounts),
                ];
            },
        );
    }

    private function createRecurrence(User $user): Tool
    {
        return new Tool(
            name: 'create_recurrence',
            description: 'Cria uma série de lançamentos recorrentes (ex.: aluguel mensal, assinatura semanal).',
            parameters: [
                'type' => ['type' => 'string', 'enum' => ['payable', 'receivable'], 'description' => 'payable (despesa) ou receivable (receita).', 'required' => true],
                'description' => ['type' => 'string', 'description' => 'Descrição do lançamento.', 'required' => true],
                'counterparty' => ['type' => 'string', 'description' => 'Fornecedor ou cliente.'],
                'cost_center_id' => ['type' => 'string', 'description' => 'UUID do centro de custo.', 'required' => true],
                'category_id' => ['type' => 'string', 'description' => 'UUID da categoria.', 'required' => true],
                'value' => ['type' => 'number', 'description' => 'Valor de cada ocorrência.', 'required' => true],
                'frequency' => ['type' => 'string', 'enum' => ['daily', 'weekly', 'biweekly', 'monthly', 'bimonthly', 'quarterly', 'semiannual', 'annual'], 'description' => 'Frequência da recorrência.', 'required' => true],
                'start_date' => ['type' => 'string', 'description' => 'Data inicial (YYYY-MM-DD).', 'required' => true],
                'end_date' => ['type' => 'string', 'description' => 'Data final opcional (YYYY-MM-DD).'],
                'day_of_month' => ['type' => 'integer', 'description' => 'Dia do mês (1-31) para recorrências mensais ou superiores.'],
            ],
            writes: true,
            handler: function (array $args) use ($user): array {
                $recurrence = $this->recurrences->create($this->recurrencePayload($args));

                $this->audit->recordEntity(
                    $user,
                    AuditAction::RecurrenceGenerate,
                    'recurrence',
                    $recurrence->uuid,
                    ['description' => $recurrence->description, 'frequency' => $recurrence->frequency?->value],
                );

                return [
                    'criado' => true,
                    'id' => $recurrence->uuid,
                    'descricao' => $recurrence->description,
                    'frequencia' => $recurrence->frequency?->value,
                    'valor' => (float) $recurrence->value,
                ];
            },
        );
    }

    private function queryReconciliation(): Tool
    {
        return new Tool(
            name: 'query_reconciliation',
            description: 'Consulta a conciliação bancária: pendências, transações não conciliadas e transações ignoradas.',
            parameters: [
                'status' => ['type' => 'string', 'enum' => ['pending', 'matched', 'ignored'], 'description' => 'Situação das transações do extrato.'],
                'cost_center_id' => ['type' => 'string', 'description' => 'UUID opcional do centro de custo.'],
            ],
            handler: function (array $args): array {
                $query = BankTransaction::query()->with('costCenter:id,uuid,name');

                $query->when(isset($args['status']), fn ($q) => $q->where('status', $args['status']));
                $query->when(isset($args['cost_center_id']), fn ($q) => $q->where('cost_center_id', $args['cost_center_id']));

                $transactions = $query->orderByDesc('date')->limit(100)->get();

                return [
                    'resumo' => [
                        'pendentes' => BankTransaction::query()->where('status', 'pending')->count(),
                        'conciliadas' => BankTransaction::query()->where('status', 'matched')->count(),
                        'ignoradas' => BankTransaction::query()->where('status', 'ignored')->count(),
                    ],
                    'transacoes' => $transactions->map(fn (BankTransaction $t) => [
                        'id' => $t->uuid,
                        'data' => $t->date?->toDateString(),
                        'valor' => (float) $t->value,
                        'tipo' => $t->type,
                        'descricao' => $t->description,
                        'status' => $t->status,
                        'centro_de_custo' => $t->costCenter?->name,
                    ])->all(),
                ];
            },
        );
    }

    private function queryAudit(): Tool
    {
        return new Tool(
            name: 'query_audit',
            description: 'Consulta o histórico de auditoria: alterações, inclusões, exclusões e responsáveis.',
            parameters: [
                'action' => ['type' => 'string', 'description' => 'Ação específica (ex.: financial.create, account.settle, reconciliation.execute).'],
                'limit' => ['type' => 'integer', 'description' => 'Máximo de resultados (padrão 20).'],
            ],
            handler: function (array $args): array {
                $limit = min(max((int) ($args['limit'] ?? 20), 1), 100);

                return $this->audit
                    ->paginate($limit, $args['action'] ?? null)
                    ->through(fn ($log) => [
                        'acao' => $log->action,
                        'entidade' => $log->entity_type,
                        'usuario' => $log->user?->name,
                        'email' => $log->user?->email,
                        'data' => $log->created_at?->toIso8601String(),
                        'detalhes' => $log->details,
                    ])
                    ->values()
                    ->all();
            },
        );
    }

    private function createAccountTool(User $user, string $name, string $type, string $description): Tool
    {
        return new Tool(
            name: $name,
            description: $description.' Exija a confirmação explícita do usuário antes de executar.',
            parameters: $this->accountWriteParameters(),
            writes: true,
            handler: function (array $args) use ($user, $type): array {
                $args['type'] = $type;

                $accounts = $this->accounts->create($this->accountPayload($args));

                $this->auditWrite($user, AuditAction::FinancialCreate, $accounts);

                return [
                    'criado' => true,
                    'lancamentos' => array_map(fn (FinancialAccount $a) => $this->presentAccount($a), $accounts),
                ];
            },
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function accountWriteParameters(): array
    {
        return [
            'description' => ['type' => 'string', 'description' => 'Descrição do lançamento.', 'required' => true],
            'value' => ['type' => 'number', 'description' => 'Valor do lançamento (positivo).', 'required' => true],
            'due_date' => ['type' => 'string', 'description' => 'Data de vencimento (YYYY-MM-DD).', 'required' => true],
            'cost_center_id' => ['type' => 'string', 'description' => 'UUID do centro de custo.', 'required' => true],
            'category_id' => ['type' => 'string', 'description' => 'UUID da categoria.', 'required' => true],
            'counterparty' => ['type' => 'string', 'description' => 'Fornecedor ou cliente (opcional).'],
            'observation' => ['type' => 'string', 'description' => 'Observação opcional.'],
            'installments' => ['type' => 'object', 'description' => 'Opcional: parcelamento.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function accountPayload(array $args): array
    {
        return [
            'type' => $args['type'],
            'description' => $args['description'],
            'counterparty' => $args['counterparty'] ?? null,
            'cost_center_id' => $args['cost_center_id'],
            'category_id' => $args['category_id'],
            'value' => (float) $args['value'],
            'due_date' => $args['due_date'],
            'observation' => $args['observation'] ?? null,
            'installments' => $args['installments'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function recurrencePayload(array $args): array
    {
        return [
            'type' => $args['type'],
            'description' => $args['description'],
            'counterparty' => $args['counterparty'] ?? null,
            'cost_center_id' => $args['cost_center_id'],
            'category_id' => $args['category_id'],
            'value' => (float) $args['value'],
            'frequency' => $args['frequency'],
            'start_date' => $args['start_date'],
            'end_date' => $args['end_date'] ?? null,
            'day_of_month' => $args['day_of_month'] ?? null,
        ];
    }

    /**
     * @param  list<FinancialAccount>  $accounts
     */
    private function auditWrite(User $user, AuditAction $action, array $accounts): void
    {
        foreach ($accounts as $account) {
            $this->audit->recordEntity(
                $user,
                $action,
                'account',
                $account->uuid,
                ['description' => $account->description, 'type' => $account->type?->value],
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function presentAccount(FinancialAccount $account): array
    {
        return [
            'id' => $account->uuid,
            'tipo' => $account->type?->value,
            'descricao' => $account->description,
            'contraparte' => $account->counterparty,
            'centro_de_custo' => $account->costCenter?->name,
            'categoria' => $account->category?->name,
            'valor' => (float) $account->value,
            'pago' => round((float) ($account->settlements_sum_value ?? 0), 2),
            'restante' => round((float) $account->value - (float) ($account->settlements_sum_value ?? 0), 2),
            'vencimento' => $account->due_date?->toDateString(),
            'status' => $account->status?->value,
            'parcela' => $account->installment_total > 1 ? "{$account->installment_number}/{$account->installment_total}" : null,
            'conciliado' => $account->reconciled_at !== null,
        ];
    }

    private function currentBalance(?string $costCenterId): float
    {
        $initial = (float) CostCenter::query()
            ->when($costCenterId, fn ($q) => $q->where('uuid', $costCenterId))
            ->sum('initial_balance');

        return round($initial + $this->netSettled(now()->endOfDay(), $costCenterId), 2);
    }

    private function settledBetween(Carbon $from, Carbon $to, AccountType $type, ?string $costCenterId): float
    {
        return round(
            (float) Settlement::query()
                ->whereBetween('settled_at', [$from, $to])
                ->whereHas('account', fn ($q) => $q->where('type', $type->value))
                ->when($costCenterId, fn ($q) => $q->whereHas('account', fn ($a) => $a->where('cost_center_id', $costCenterId)))
                ->sum('value'),
            2,
        );
    }

    private function netSettled(Carbon $upTo, ?string $costCenterId): float
    {
        $base = fn () => Settlement::query()
            ->where('settled_at', '<=', $upTo)
            ->when($costCenterId, fn ($q) => $q->whereHas('account', fn ($a) => $a->where('cost_center_id', $costCenterId)));

        $in = (float) $base()->whereHas('account', fn ($q) => $q->where('type', AccountType::Receivable->value))->sum('value');
        $out = (float) $base()->whereHas('account', fn ($q) => $q->where('type', AccountType::Payable->value))->sum('value');

        return round($in - $out, 2);
    }
}
