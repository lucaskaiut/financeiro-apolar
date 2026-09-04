export const TOOL_RUNNING_LABELS: Record<string, string> = {
  get_financial_summary: 'Consultando resumo financeiro...',
  list_cost_centers: 'Listando centros de custo...',
  list_categories: 'Listando categorias...',
  query_accounts: 'Consultando lançamentos...',
  query_payables: 'Consultando contas a pagar...',
  query_receivables: 'Consultando contas a receber...',
  get_cash_flow_realized: 'Consultando fluxo de caixa realizado...',
  get_cash_flow_projected: 'Consultando fluxo de caixa projetado...',
  create_expense: 'Criando despesa...',
  create_income: 'Criando receita...',
  create_installment: 'Gerando parcelamento...',
  create_recurrence: 'Criando recorrência...',
  query_reconciliation: 'Verificando conciliações...',
  query_audit: 'Consultando auditoria...',
}

export const TOOL_DONE_LABELS: Record<string, string> = {
  get_financial_summary: 'Resumo financeiro consultado',
  list_cost_centers: 'Centros de custo listados',
  list_categories: 'Categorias listadas',
  query_accounts: 'Lançamentos consultados',
  query_payables: 'Contas a pagar consultadas',
  query_receivables: 'Contas a receber consultadas',
  get_cash_flow_realized: 'Fluxo realizado consultado',
  get_cash_flow_projected: 'Projeção consultada',
  create_expense: 'Despesa criada',
  create_income: 'Receita criada',
  create_installment: 'Parcelamento gerado',
  create_recurrence: 'Recorrência criada',
  query_reconciliation: 'Conciliações verificadas',
  query_audit: 'Auditoria consultada',
}

export function toolRunningLabel(name: string): string {
  return TOOL_RUNNING_LABELS[name] ?? 'Executando ferramenta...'
}

export function toolDoneLabel(name: string): string {
  return TOOL_DONE_LABELS[name] ?? 'Operação concluída'
}
