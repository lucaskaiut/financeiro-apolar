# Documento de Requisitos Funcionais

## Sistema Financeiro, Fluxo de Caixa, Contas a Pagar e Receber, Conciliação Bancária e Projeções Financeiras

### Versão 2.0

---

# 1. Objetivo

Desenvolver uma plataforma financeira corporativa para controle de fluxo de caixa, contas a pagar, contas a receber, conciliação bancária automatizada, provisões financeiras, parcelamentos, recorrências, projeções de caixa e controle de acesso por perfil.

Cada centro de custo representará uma conta bancária operacional da empresa.

---

# 2. Escopo

O sistema deverá contemplar:

* Autenticação de usuários.
* Controle de acesso baseado em perfis e permissões (ACL).
* Gestão de centros de custo.
* Gestão de categorias financeiras.
* Contas a pagar.
* Contas a receber.
* Parcelamentos.
* Recorrências.
* Fluxo de caixa realizado.
* Fluxo de caixa projetado.
* Conciliação bancária.
* Importação OFX.
* Auditoria.
* Relatórios financeiros.

---

# 3. Controle de Acesso (ACL)

## RF001 - Perfis

O sistema deverá permitir:

* Criar perfil.
* Editar perfil.
* Excluir perfil.
* Associar usuários.

Perfis padrão:

* Administrador
* Financeiro
* Gestor Financeiro
* Auditor
* Consulta

---

## RF002 - Permissões

O sistema deverá permitir permissões granulares.

### Exemplos

#### Centros de custo

* cost_centers.view
* cost_centers.create
* cost_centers.update
* cost_centers.delete

#### Categorias

* categories.view
* categories.create
* categories.update
* categories.delete

#### Contas

* accounts.view
* accounts.create
* accounts.update
* accounts.delete
* accounts.settle

#### Conciliação

* reconciliation.view
* reconciliation.execute
* reconciliation.undo

#### Relatórios

* reports.view
* reports.export

#### Auditoria

* audit.view

---

# 4. Centros de Custo

## RF003 - Cadastro de Centros de Custo

Representam contas bancárias.

### Campos

* Nome
* Banco
* Agência
* Conta
* Tipo
* Saldo inicial
* Status

---

# 5. Categorias Financeiras

## RF004 - Cadastro de Categorias

### Campos

* Nome
* Tipo

  * Receita
  * Despesa
* Cor
* Status

---

# 6. Contas a Pagar

## RF005 - Cadastro de Contas a Pagar

### Campos

* Descrição
* Fornecedor
* Centro de custo
* Categoria
* Valor
* Data de vencimento
* Data prevista de pagamento
* Observação
* Status

### Status

* Aberto
* Parcialmente pago
* Pago
* Cancelado

---

# 7. Contas a Receber

## RF006 - Cadastro de Contas a Receber

### Campos

* Descrição
* Cliente
* Centro de custo
* Categoria
* Valor
* Data de vencimento
* Data prevista de recebimento
* Observação
* Status

### Status

* Aberto
* Parcialmente recebido
* Recebido
* Cancelado

---

# 8. Parcelamentos

## RF007 - Parcelamento de Contas

O sistema deverá permitir parcelar contas a pagar e receber.

### Exemplo

Compra:

* R$ 12.000,00

Parcelamento:

* 12x R$ 1.000,00

Resultado:

O sistema deverá gerar automaticamente:

* 12 lançamentos financeiros.

### Configurações

* Quantidade de parcelas.
* Intervalo entre parcelas.
* Data da primeira parcela.

---

## RF008 - Controle das Parcelas

Cada parcela deverá possuir:

* Número da parcela.
* Quantidade total.
* Valor.
* Vencimento.
* Status individual.

Exemplo:

* Parcela 5/12 paga.
* Parcela 6/12 aberta.

---

# 9. Recorrências

## RF009 - Lançamentos Recorrentes

O sistema deverá permitir geração automática de contas recorrentes.

### Frequências

* Diária
* Semanal
* Quinzenal
* Mensal
* Bimestral
* Trimestral
* Semestral
* Anual

---

## RF010 - Regras da Recorrência

### Campos

* Frequência
* Data inicial
* Data final
* Quantidade máxima de ocorrências
* Valor

### Exemplo

Internet

* R$ 200
* Todo dia 10

Resultado

O sistema gera automaticamente os próximos lançamentos.

---

## RF011 - Alteração da Recorrência

O usuário poderá escolher:

* Alterar apenas a ocorrência atual.
* Alterar ocorrências futuras.
* Alterar toda a série.

---

# 10. Fluxo de Caixa

## RF012 - Fluxo de Caixa Realizado

Exibir:

* Entradas realizadas.
* Saídas realizadas.
* Saldo inicial.
* Saldo final.

Filtros:

* Período.
* Centro de custo.
* Categoria.

---

## RF013 - Fluxo de Caixa Projetado

Exibir:

* Contas futuras.
* Parcelas futuras.
* Recorrências futuras.

Permitir projeção para:

* 7 dias.
* 15 dias.
* 30 dias.
* 60 dias.
* 90 dias.
* 180 dias.
* 365 dias.

---

# 11. Conciliação Bancária

## RF014 - Importação OFX

Importar:

* Data
* Valor
* Histórico
* Tipo
* Identificador da transação

---

## RF015 - Conciliação Automática

Critérios:

* Valor
* Data
* Centro de custo

---

## RF016 - Baixa Automática

Quando houver correspondência única.

---

## RF017 - Conciliação Múltipla

Quando existirem quantidades equivalentes de registros dos dois lados.

---

## RF018 - Ambiguidade

Quando houver múltiplos registros possíveis.

O sistema deverá solicitar escolha do usuário.

---

## RF019 - Movimentações Não Encontradas

Quando houver lançamento no extrato sem correspondente.

O sistema deverá permitir:

* Criar receita.
* Criar despesa.
* Ignorar.

---

## RF020 - Desfazer Conciliação

O sistema deverá permitir:

* Remover vínculo.
* Reabrir lançamento.
* Registrar auditoria.

---

# 12. Transferências Entre Contas

## RF021 - Transferência Interna

O sistema deverá permitir transferências entre centros de custo.

### Exemplo

Conta Banco A:

* Saída R$ 5.000

Conta Banco B:

* Entrada R$ 5.000

O sistema deverá gerar os dois movimentos automaticamente.

---

# 13. Auditoria

## RF022 - Auditoria Financeira

Registrar:

* Inclusão
* Alteração
* Exclusão
* Conciliação
* Desfazer conciliação
* Baixas
* Criação automática por recorrência
* Criação automática por parcelamento

---

# 14. Relatórios

## RF023 - Relatório Diário

* Pagamentos realizados
* Recebimentos realizados
* Saldo do dia

---

## RF024 - Relatório Semanal

* Total pago
* Total recebido
* Saldo líquido

---

## RF025 - Relatório de Provisão

* Contas futuras
* Parcelas futuras
* Recorrências futuras

---

## RF026 - Relatório por Categoria

* Receita por categoria
* Despesa por categoria

---

## RF027 - Relatório por Centro de Custo

* Saldo
* Entradas
* Saídas

---

## RF028 - Demonstrativo de Fluxo de Caixa

* Realizado
* Projetado
* Comparativo

---

# 15. Regras de Negócio

## RN001

Todo lançamento deverá possuir:

* Categoria
* Centro de custo

---

## RN002

Lançamentos conciliados não poderão ser excluídos.

---

## RN003

Parcelas podem ser baixadas individualmente.

---

## RN004

Recorrências não poderão alterar lançamentos já conciliados.

---

## RN005

Transferências não impactam resultado financeiro consolidado.

---

## RN006

Toda movimentação deverá ser auditável.

---

# 16. Perfis Padrão

## Administrador

Acesso total.

## Financeiro

Operação financeira diária.

## Gestor Financeiro

Operação + relatórios + conciliações.

## Auditor

Somente leitura e auditoria.

## Consulta

Somente leitura.
