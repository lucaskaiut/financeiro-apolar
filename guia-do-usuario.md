# Guia do usuário — Financeiro Apolar

Este documento explica, para o usuário final, como o **Financeiro Apolar** funciona no dia a dia: o que cada tela faz, como cadastrar informações e como as peças se encaixam (lançamentos, contas bancárias, cartões, centros de custo, conciliação e relatórios).

---

## Sumário

1. [O que é o sistema](#1-o-que-é-o-sistema)
2. [Primeiro acesso](#2-primeiro-acesso)
3. [Como a tela está organizada](#3-como-a-tela-está-organizada)
4. [Conceitos que você precisa conhecer](#4-conceitos-que-você-precisa-conhecer)
5. [Dashboard](#5-dashboard)
6. [Cadastros essenciais](#6-cadastros-essenciais)
7. [Contas a pagar e a receber](#7-contas-a-pagar-e-a-receber)
8. [Cartões de crédito e faturas](#8-cartões-de-crédito-e-faturas)
9. [Recorrências](#9-recorrências)
10. [Transferências entre contas](#10-transferências-entre-contas)
11. [Fluxo de caixa realizado e projetado](#11-fluxo-de-caixa-realizado-e-projetado)
12. [Conciliação bancária](#12-conciliação-bancária)
13. [Relatórios](#13-relatórios)
14. [Assistente de IA](#14-assistente-de-ia)
15. [Usuários, perfis e auditoria](#15-usuários-perfis-e-auditoria)
16. [Fluxo recomendado para começar](#16-fluxo-recomendado-para-começar)
17. [Perguntas frequentes](#17-perguntas-frequentes)
18. [Glossário](#18-glossário)

---

## 1. O que é o sistema

O Financeiro Apolar é o painel financeiro da organização. Ele concentra:

- contas a pagar e a receber
- saldo e movimento das contas bancárias
- compras e faturas de cartão de crédito
- classificação por categoria, centro de custo e empresa
- fluxo de caixa realizado e projetado
- conciliação com extrato OFX
- relatórios para acompanhamento e impressão
- trilha de auditoria e controle de acesso por perfil

Os dados de cada organização (empresa/grupo) ficam isolados. O que você vê depende do perfil de acesso: alguns usuários só consultam, outros operam o financeiro, e administradores gerenciam usuários e permissões.

---

## 2. Primeiro acesso

### Entrar

Na tela **Entrar**, informe:

- **E-mail**
- **Senha**

Depois clique em **Entrar**. O sistema abre o **Dashboard**.

Não há cadastro público nem recuperação de senha nesta tela. Se você não consegue acessar, peça ao administrador da organização para criar ou redefinir seu usuário.

### Trocar de empresa (usuários master)

Se o seu usuário for **master**, aparece no topo um seletor **Selecionar empresa**. Use-o para alternar entre as empresas do grupo. A empresa principal aparece com o sufixo `(grupo)`.

Os demais usuários veem apenas a organização à qual pertencem.

### Sair e tema

No canto superior direito:

- ícone de tema para alternar entre **claro** e **escuro**
- menu do usuário, com **Sair da conta**

---

## 3. Como a tela está organizada

À esquerda fica o menu. No celular, abra-o pelo botão **Abrir menu**. Os itens só aparecem se o seu perfil tiver permissão.

| Grupo | Item | Para que serve |
|--------|------|----------------|
| **Geral** | Dashboard | Visão geral da saúde financeira |
| | Assistente de IA | Perguntas em linguagem natural sobre o financeiro |
| **Financeiro** | Fluxo realizado | Entradas e saídas que já aconteceram |
| | Fluxo projetado | Previsão de entradas e saídas futuras |
| | Contas a pagar/receber | Lançamentos (pagar, receber, baixar, parcelar, ratear) |
| | Recorrências | Contas que se repetem automaticamente |
| | Transferências | Movimentar valor entre contas bancárias |
| | Conciliação | Conferir extrato OFX com os lançamentos |
| | Relatórios | Visões diárias, semanais, por categoria, provisão etc. |
| **Cadastros** | Contas bancárias | Conta corrente, poupança, investimento |
| | Centros de custo | Área ou departamento (Administrativo, Comercial…) |
| | Empresas | Classificação extra do lançamento |
| | Cartões de crédito | Cartões, compras e fechamento de faturas |
| | Categorias | Receitas e despesas (com subcategorias) |
| **Gestão** | Usuários | Quem acessa o sistema |
| | Perfis de acesso | O que cada grupo pode fazer |
| | Auditoria | Histórico de inclusões, baixas, exclusões etc. |
| | Inteligência Artificial | Configuração técnica do assistente |

Há também um **widget flutuante** do assistente de IA nas telas principais.

---

## 4. Conceitos que você precisa conhecer

Estes cadastros se parecem, mas cada um tem um papel diferente.

### Conta bancária

É a conta operacional no banco (ou investimento). Tem nome, banco, agência, número, tipo e **saldo inicial**.

O saldo atual parte do saldo inicial e soma as entradas e saídas **já liquidadas** (baixas e transferências). Use a conta bancária em lançamentos, transferências, pagamento de fatura e conciliação OFX.

Tipos: **Conta corrente**, **Conta poupança**, **Investimento** e **Outro**.

### Centro de custo

É a **classificação organizacional** do lançamento — por área, departamento ou projeto (ex.: Administrativo, Comercial, TI).

Não substitui a conta bancária. No lançamento o centro de custo é **opcional**. Serve para saber *quem* gerou a despesa ou receita, não *de onde saiu o dinheiro*.

Quando uma mesma compra atende mais de uma área, use o **rateio**: cada fatia pode ter um centro de custo diferente, sem criar várias contas.

### Empresa

Cadastro simples (nome e status) para vincular o lançamento a uma empresa do grupo, quando a organização precisa dessa classificação. Também é **opcional**. Não confunda com a organização (tenant) em que você está logado.

### Categoria e subcategoria

Dizem **o que** é o movimento: aluguel, folha, energia, vendas, etc.

- Tipo **Receita** ou **Despesa**
- Subcategoria herda o tipo da categoria pai
- Cores (Índigo, Azul, Verde, Âmbar, Vermelho, Roxo, Cinza) ajudam na leitura dos gráficos

Todo lançamento precisa de **categoria** — no cabeçalho, ou em cada linha se você usar **rateio**. Subcategoria é opcional.

### Lançamento (conta a pagar ou a receber)

É o compromisso financeiro: o que você deve pagar ou o que espera receber. Tem vencimento, valor, status e, quando pago/recebido, uma **baixa**.

Um lançamento é **um** compromisso com o banco (um boleto, um débito no extrato). Se a nota mistura categorias ou centros de custo, o valor pode ser **rateado** em várias fatias — a conciliação continua vendo uma conta só.

### Cartão de crédito

Compras no cartão **não saem da conta bancária na hora**. Elas entram em uma **fatura**. Só quando a fatura é paga (baixa da conta “Fatura …”) o dinheiro sai da conta bancária e as compras são liquidadas.

### Transferência

Move dinheiro de uma conta bancária para outra. Não é receita nem despesa operacional: o consolidado da organização não muda, só a distribuição entre contas.

---

## 5. Dashboard

**Menu:** Geral → Dashboard

A saudação mostra seu primeiro nome e o texto *Visão geral da saúde financeira.*

### Filtro por centro de custo

No topo, um seletor permite ver **Todos** ou um centro de custo. Os números e gráficos acompanham o filtro. Em lançamentos **rateados**, entra só a fatia daquele centro — não o valor inteiro da conta.

### Filtro por conta bancária

Logo abaixo, o mesmo tipo de seletor permite ver **Todas** as contas ou uma conta bancária. Os indicadores, vencidos e saldos passam a considerar só aquela conta. Os dois filtros podem ser combinados.

### Indicadores (KPIs)

| Indicador | Significado |
|-----------|-------------|
| **Saldo atual** | Saldo consolidado das contas (ou da fatia do centro de custo filtrado) |
| **Saídas do mês** | Despesas já realizadas no mês corrente |
| **A pagar (em aberto)** | Total ainda não liquidado de contas a pagar |
| **Vencidas** | Valor em atraso e quantidade de lançamentos atrasados |

### Widgets

- **Contas a pagar dos próximos 7 dias** — o que vence em breve
- **Saldo por conta bancária** — saldo de cada conta
- **Despesas por categoria** — onde o dinheiro está sendo gasto (lançamentos rateados entram na categoria de cada fatia)
- **Lançamentos vencidos** — atrasos, com atalho **Ver todos** para a lista filtrada
- **Próximos vencimentos** — horizonte de 30 dias

Cada item da lista mostra badge **A pagar** ou **A receber**.

Se ainda não houver dados, aparece *Sem dados financeiros* com o botão **Criar primeiro lançamento**.

---

## 6. Cadastros essenciais

Faça estes cadastros antes de lançar o movimento do dia a dia.

### 6.1 Contas bancárias

**Menu:** Cadastros → Contas bancárias

Cadastre cada conta operacional da organização.

| Campo | Obrigatório | Observação |
|-------|-------------|------------|
| Nome | Sim | Ex.: Banco A - Conta principal |
| Banco | Não | Ex.: Banco do Brasil |
| Agência | Não | |
| Conta | Não | Número da conta |
| Tipo | Sim | Corrente, poupança, investimento ou outro |
| Saldo inicial | Não | Ponto de partida do saldo |
| Status | Sim | **Ativo** ou **Inativo** |

Ações: criar, editar e excluir (conforme permissão). Contas inativas deixam de aparecer nas escolhas do dia a dia.

### 6.2 Centros de custo

**Menu:** Cadastros → Centros de custo

*Organize despesas e receitas por área ou departamento.*

| Campo | Obrigatório |
|-------|-------------|
| Nome | Sim (ex.: Administrativo) |
| Status | Sim (Ativo / Inativo) |

Use o centro de custo nos lançamentos quando quiser relatórios e faturas de cartão por área.

### 6.3 Empresas

**Menu:** Cadastros → Empresas

Cadastro de empresas para classificação de lançamentos (nome e status). Útil quando um mesmo financeiro atende mais de uma razão social.

### 6.4 Categorias

**Menu:** Cadastros → Categorias

*Classifique receitas e despesas da sua operação.*

| Campo | Observação |
|-------|------------|
| Nome | Ex.: Fornecedores, Folha, Vendas |
| Categoria pai | Se preenchida, o registro vira **subcategoria** |
| Cor | Identificação visual nos gráficos |
| Status | Ativo / Inativo |
| Tipo | **Receita** ou **Despesa**. Na subcategoria, o tipo vem da categoria pai |

Dica: comece pelas categorias principais e só depois crie subcategorias. Na importação de planilha, o sistema também cria categoria e subcategoria automaticamente a partir das colunas GRUPO e TIPO DE DESPESA.

### 6.5 Cartões de crédito (cadastro)

**Menu:** Cadastros → Cartões de crédito

Detalhes de uso (compras e faturas) estão na [seção 8](#8-cartões-de-crédito-e-faturas). No cadastro informe:

| Campo | Observação |
|-------|------------|
| Nome | Ex.: Cartão corporativo |
| Instituição | Ex.: Nubank |
| Limite | Valor do limite |
| Dia de fechamento | 1 a 31 |
| Dia de vencimento | 1 a 31 |
| Conta para pagamento | Conta bancária de onde a fatura será paga |
| Status | Ativo / Inativo |

O **dia de fechamento** define em qual fatura a compra entra. O **dia de vencimento** define quando a fatura deve ser paga.

- Se o vencimento é **depois** do fechamento no calendário (fecha dia 10, vence dia 17), o pagamento é no **mesmo mês**.
- Se o vencimento é **antes** ou no mesmo dia do fechamento (fecha dia 25, vence dia 5), o pagamento é no **mês seguinte**.

---

## 7. Contas a pagar e a receber

**Menu:** Financeiro → Contas a pagar/receber

Esta é a tela central do controle financeiro. *Gerencie os lançamentos financeiros da operação.*

### 7.1 Lista

No cabeçalho:

- **Importar planilha** — traz despesas de um arquivo XLSX
- **Novo lançamento** — cria conta a pagar ou a receber

**Filtros:**

- Busca por descrição ou fornecedor/cliente
- Tipo: Todos, **A pagar**, **A receber**
- Status: Todos, Aberto, Parcial, Liquidado, Cancelado, ou **Vencidos**
- Conta bancária e/ou cartão de crédito
- Intervalo de **vencimento** e de **data da baixa**

**Colunas:** Lançamento, Valor, Data da compra, Vencimento, Data da baixa, Status, Ações.

Parcelas aparecem com badge no formato `n/total` (ex.: 2/12). Lançamentos rateados mostram o badge **Rateio** e, na linha, as categorias das fatias.

### 7.2 Status do lançamento

| Status | Significado |
|--------|-------------|
| **Aberto** | Ainda não houve baixa, ou o valor restante é o total |
| **Parcial** | Houve baixa de parte do valor |
| **Liquidado** | Valor totalmente baixado (pago ou recebido) |
| **Cancelado** | Lançamento anulado; não volta para aberto |

Contas **canceladas** não podem ser reabertas pelo cancelamento. Para um lançamento liquidado que precisa voltar, use **Desfazer baixa** ou, na edição, **Reabrir conta**.

### 7.3 Ações por lançamento

| Ação | Quando aparece | O que faz |
|------|----------------|-----------|
| **Baixar** | Aberto ou Parcial, e **não** é compra de cartão | Registra pagamento/recebimento |
| **Desfazer baixa** | Liquidado | Estorna as baixas |
| **Clonar** | Sempre (com permissão) | Abre um novo lançamento copiando os dados |
| **Editar** | Sempre | Altera o lançamento |
| **Cancelar** | Aberto ou Parcial | Anula o compromisso |
| **Excluir** | Sempre (com permissão) | Remove o registro |

Compras de cartão **não** são baixadas uma a uma nesta lista. Elas liquidam quando a **fatura** é paga.

### 7.4 Criar ou editar lançamento

Campos principais:

| Campo | Observação |
|-------|------------|
| Tipo | **Conta a pagar** ou **Conta a receber** |
| Descrição | Obrigatório |
| Fornecedor / Cliente | Muda conforme o tipo |
| Cartão de crédito | Só na criação. Padrão: *Nenhum (fluxo normal)* |
| Conta bancária | Obrigatória no fluxo normal (sem cartão) |
| Empresa | Opcional |
| Centro de custo | Opcional no cabeçalho. Some daqui se o rateio estiver ligado |
| Categoria | Obrigatória no cabeçalho, salvo se o rateio estiver ligado |
| Subcategoria | Opcional no cabeçalho. Some daqui se o rateio estiver ligado |
| Valor | Obrigatório (é o total da conta, o que sai no extrato) |
| Data da compra | Obrigatória em compra de cartão; opcional no fluxo normal |
| Data de vencimento | Obrigatória no fluxo normal. No cartão, o sistema calcula pelo ciclo |
| Data prevista de pagamento/recebimento | Opcional (não aparece em cartão) |
| Data da baixa | Na edição, se já houver baixas |
| Observação | Texto livre |
| Ratear este lançamento | Divide o valor entre categorias e centros de custo |
| Parcelar este lançamento | Só na criação |
| Documentos | Anexos na criação (faturas, boletos, comprovantes) |

Se você escolher um **cartão**:

- o tipo fica fixo em **conta a pagar**
- a conta bancária some do formulário
- o vencimento segue o ciclo da fatura
- a data da compra define em qual fatura a compra entra
- o rateio continua disponível: a compra fica classificada em fatias; a fatura segue sendo um único título para pagar e conciliar

### 7.5 Rateio

Use o rateio quando **uma única compra** precisa aparecer em mais de uma categoria e/ou centro de custo.

**Exemplo:** você comprou R$ 330 em produtos. Parte foi para a categoria X no centro de custo A (R$ 150), parte para Y no centro B (R$ 100) e o restante para Z no centro C (R$ 80). No banco sai **um** débito de R$ 330. Nos relatórios, cada fatia aparece no seu lugar.

#### Como lançar

1. Preencha descrição, conta bancária (ou cartão), valor total e vencimento como de costume.
2. Ative **Ratear este lançamento**.
3. Categoria, subcategoria e centro de custo saem do cabeçalho e passam para as **linhas**.
4. Em cada linha informe categoria (obrigatória), subcategoria (opcional), centro de custo (opcional) e valor.
5. Use **Adicionar linha** se precisar de mais fatias (mínimo duas).
6. Confira o texto *Rateado … de …*: a soma das linhas precisa ser **igual ao valor do lançamento**.

A empresa do cabeçalho vale para a conta inteira.

#### O que o rateio altera (e o que não altera)

| Onde | O que acontece |
|------|----------------|
| Lista de contas | Continua **uma** linha, com badge **Rateio** |
| Baixa e saldo bancário | Um pagamento do valor total |
| Conciliação OFX | Casa com **uma** transação do extrato |
| Relatórios por categoria e centro de custo | Mostram as fatias separadas |
| Fluxo de caixa realizado | Continua **uma** saída do valor total (as categorias aparecem juntas na descrição) |
| Dashboard filtrado por centro de custo | Conta só a fatia daquele centro |

Não crie três contas a pagar para o mesmo boleto só para classificar: isso complica a conciliação. Rateio é classificação; várias contas só fazem sentido quando realmente existem vários títulos (vários boletos no mesmo débito — aí use a conciliação múltipla).

Baixa parcial também respeita a proporção: se você pagar metade, cada fatia entra pela metade nos relatórios realizados; o restante fica em aberto na mesma proporção.

Na **edição**, você pode ligar, desligar ou ajustar o rateio. Clonar um lançamento rateado copia as fatias.

### 7.6 Baixa (pagamento ou recebimento)

Clique em **Baixar**. No modal **Registrar baixa**:

- **Valor da baixa** — pode ser menor que o restante (baixa parcial)
- **Data da baixa** — data em que o dinheiro saiu ou entrou
- **Forma de pagamento** — ex.: Pix, boleto

A baixa entra no **fluxo de caixa realizado** e altera o saldo da conta bancária.

- Baixa parcial → status **Parcial**
- Baixa do valor restante → status **Liquidado**

**Desfazer baixa** remove as liquidações e tira o movimento do realizado.

**Reabrir conta** (na tela de edição) remove todas as baixas, limpa a data da baixa, volta o status para aberto e, se estiver conciliado, desfaz a conciliação. Essa operação não se desfaz sozinha.

### 7.7 Parcelamento

Na criação, ative **Parcelar este lançamento**.

**Fluxo normal (conta bancária):**

- informe a quantidade de parcelas
- escolha o intervalo: **Diário**, **Semanal** ou **Mensal**
- o sistema gera vários lançamentos independentes, cada um com seu vencimento
- cada parcela pode ser baixada separadamente

**Cartão de crédito:**

- informe só a quantidade
- cada parcela entra em uma **fatura mensal** seguinte
- a **data da compra** permanece a da compra original em todas as parcelas

Rateio e parcelamento podem ser usados juntos. Cada parcela recebe as mesmas proporções (a última parcela absorve o centavo de arredondamento). Cada parcela continua sendo um lançamento independente para baixa e conciliação.

### 7.8 Importar planilha

Use **Importar planilha** para trazer despesas de um XLSX. É obrigatório escolher a **conta bancária** e o **centro de custo** de destino.

O sistema lê a aba **BASE DE DADOS** (planilhas Apolar). Colunas reconhecidas:

- **GRUPO** → categoria (criada se ainda não existir)
- **TIPO DE DESPESA** → subcategoria (criada se ainda não existir)
- **TIPO DE DESPESA 2** → descrição; se estiver vazia, usa TIPO DE DESPESA
- **VENCIMENTO** → data de vencimento
- **PGTO** → data de pagamento
- **STATUS** → Pago/Baixada liquida a conta; A Vencer/Vencido entra em aberto
- **$ REALIZADO** / **R$ PREVISTO** → valor (pago usa o realizado; em aberto usa o previsto)
- **OBSERVAÇÃO** → observação da conta

Se a conta estiver paga e a data de PGTO estiver vazia, a baixa usa a data de **vencimento**.

O formato antigo também continua válido: **Data**, **Histórico**, **Débito (R$)**, **TIPO**, **CONSIDERAR**, **GRUPO**. Nesse formato, só linhas com CONSIDERAR vazio ou **SIM** são importadas, e entram como contas a pagar já liquidadas.

---

## 8. Cartões de crédito e faturas

**Menu:** Cadastros → Cartões de crédito

Fluxo resumido:

1. Cadastre o cartão (fechamento, vencimento e conta de pagamento).
2. Lance **compras** (em Contas a pagar/receber ou pelo botão **Nova compra** no cartão).
3. No fim do ciclo, **feche a fatura** do mês de referência.
4. Pague a fatura com **baixa** da conta a pagar gerada — isso liquida as compras e sai da conta bancária.

### 8.1 Detalhe do cartão

No cartão escolhido você vê as **faturas** e as ações **Nova compra** e **Editar cartão**.

Texto da tela: *O fechamento gera uma única conta a pagar para conciliação. As compras permanecem com centro de custo e categoria e são liquidadas quando a fatura for paga.*

Se a compra no cartão for **rateada**, as fatias (categoria e centro de custo) ficam na compra. A fatura continua um único valor a pagar e a conciliar no extrato.

### 8.2 Como a compra entra na fatura

A **data da compra** e o **dia de fechamento** definem o mês de referência:

- compra **até** o dia de fechamento → fatura daquele mês
- compra **depois** do fechamento → fatura do mês seguinte

O vencimento da compra é o vencimento dessa fatura (não a data da compra).

**Exemplo A** — fecha dia 10, vence dia 17  
Compra em 8 de março entra na fatura de março, com vencimento em 17 de março.

**Exemplo B** — fecha dia 25, vence dia 5  
Compra em 26 de março (depois do fechamento) entra na fatura de abril, com vencimento em 5 de maio.

### 8.3 Fechar fatura

1. Abra o cartão.
2. Informe o **Mês de referência**.
3. Clique em **Fechar fatura**.

O sistema agrupa as compras **abertas ou parciais** daquele vencimento que ainda não estão em fatura e cria uma conta a pagar no formato:

`Fatura {nome do cartão} — {AAAA-MM}`

A fatura fica com status **Fechada**. Não é possível fechar duas vezes o mesmo mês. É preciso haver compras com saldo pendente.

### 8.4 Status da fatura

| Status | Significado |
|--------|-------------|
| **Aberta** | Ainda não fechada (ou sem fatura gerada) |
| **Fechada** | Conta a pagar criada, aguardando pagamento |
| **Paga** | A conta da fatura foi liquidada |

Na tabela: Referência, Total, Vencimento, Status, quantidade de compras. Use **Compras** para expandir (centro de custo, categoria, valor) e **Fatura** para abrir a conta a pagar.

### 8.5 Pagar a fatura

Baixe a conta a pagar da fatura (em Contas a pagar/receber ou pelo atalho **Fatura**).

O que acontece:

- o valor sai da **conta para pagamento** cadastrada no cartão
- as compras ligadas são liquidadas automaticamente (método interno de fatura)
- a fatura passa a **Paga**

Se você **desfizer a baixa** da fatura, as liquidações das compras também são revertidas.

Não baixe as compras individualmente: o pagamento correto é o da fatura, para conciliar um único valor com o extrato do banco.

---

## 9. Recorrências

**Menu:** Financeiro → Recorrências

*Lançamentos que se repetem automaticamente* — aluguel, assinaturas, mensalidades, pró-labore etc.

### Frequências

Diária, Semanal, Quinzenal, Mensal, Bimestral, Trimestral, Semestral, Anual.

### Campos

| Campo | Observação |
|-------|------------|
| Tipo | Conta a pagar ou a receber |
| Descrição, fornecedor/cliente | Iguais ao lançamento |
| Conta bancária | Obrigatória |
| Categoria / subcategoria | Categoria obrigatória |
| Valor | |
| Frequência | |
| Data inicial | Início da série |
| Dia do mês | Opcional (ex.: 10 para todo dia 10) |
| Data final | Opcional |
| Quantidade máxima de ocorrências | Opcional |

O sistema gera os lançamentos futuros a partir desse modelo.

### Editar a série

Ao alterar uma recorrência, escolha o **alcance da alteração**:

- **Alterar toda a série**
- **Alterar ocorrências futuras**
- **Alterar apenas a recorrência** (o modelo, sem mexer nos lançamentos já gerados da mesma forma)

Há também opção de **clonar** uma recorrência.

---

## 10. Transferências entre contas

**Menu:** Financeiro → Transferências

*Movimente valores entre contas bancárias.*

Campos: **Conta de origem**, **Conta de destino**, **Valor**, **Data**, **Descrição**.

O sistema gera automaticamente a saída na origem e a entrada no destino. No fluxo realizado, esses movimentos aparecem com o badge **Transferência**.

Excluir a transferência também remove os movimentos gerados.

Use transferência para, por exemplo, enviar dinheiro da conta corrente para a poupança. Não use um “lançamento de despesa” para isso: distorceria o resultado operacional.

---

## 11. Fluxo de caixa realizado e projetado

### 11.1 Fluxo realizado

**Menu:** Financeiro → Fluxo realizado

*Entradas e saídas efetivamente realizadas no período.*

Entram aqui as **baixas** (pagamentos e recebimentos) e as **transferências**, na data em que o dinheiro moveu — não a data de vencimento.

Filtros: período, conta bancária, categoria.

Indicadores: **Saldo inicial**, **Entradas**, **Saídas**, **Saldo final** do período.

A lista mostra data, lançamento (com conta e categoria) e valor. Entradas aparecem positivas; saídas, negativas. Transferências têm badge próprio.

Lançamentos **rateados** aparecem como **uma** linha, com o valor total. As categorias e centros de custo das fatias vêm juntos na descrição (separados por `/`). Se você filtrar por uma categoria, o valor mostrado é só a fatia daquela categoria.

### 11.2 Fluxo projetado

**Menu:** Financeiro → Fluxo projetado

*Previsão de entradas e saídas futuras.*

Considera contas **em aberto** (e o saldo restante das parciais), parcelas ainda não pagas, faturas a vencer etc., pela data de **vencimento**.

Filtros: período e conta bancária. O padrão costuma ser da data de hoje até 30 dias à frente.

Indicadores: **Saldo atual**, **Entradas previstas**, **Saídas previstas**, **Saldo final projetado**.

Há coluna de **parcela** quando o item faz parte de um parcelamento.

Use o realizado para conferir o que já aconteceu e o projetado para se antecipar a apertos de caixa.

---

## 12. Conciliação bancária

**Menu:** Financeiro → Conciliação

*Importe extratos OFX e concilie com os lançamentos.*

A conciliação confirma que o que está no sistema bate com o que o banco registrou.

### Passo a passo

1. Selecione a **Conta do extrato** (conta bancária).
2. Importe o arquivo **OFX** (`.ofx` ou `.xml`).
3. Filtre por status: **Pendentes**, **Conciliadas** ou **Ignoradas**.
4. Opcionalmente, informe um período e use **Conciliação automática**.

### Por transação

| Ação | Efeito |
|------|--------|
| **Conciliar** | Liga a linha do extrato a um ou mais lançamentos |
| **Ignorar** | Marca a transação para não conciliar (ex.: tarifa já tratada de outro jeito) |
| **Desfazer** | Reabre uma conciliação já feita |

Na conciliação **manual**, você escolhe lançamentos candidatos ou **cria** um lançamento a partir da transação (tipo, descrição, categoria, conta bancária, centro de custo, valor e vencimento).

Se a conta bancária do lançamento for diferente da conta do extrato, o sistema avisa.

Status da transação: **Pendente**, **Conciliada**, **Ignorada**.

Dica: pague a **fatura do cartão** como um único lançamento e concilie esse valor com o débito correspondente no extrato.

A mesma lógica vale para o **rateio**: no extrato existe um débito só. Concilie a conta rateada (valor total) com essa linha. Não espere três lançamentos de 150, 100 e 80 para um débito de 330.

---

## 13. Relatórios

**Menu:** Financeiro → Relatórios

*Acompanhe a saúde financeira da operação.*

Todos os relatórios podem ser vistos na tela. Com permissão de exportação, também saem em **HTML/impressão** e **XLSX**. Há filtros por período, conta bancária e, nas tabelas, por coluna.

| Aba | O que mostra |
|-----|----------------|
| **Diário** | Pagamentos e recebimentos de um dia, com saldo do dia, agrupados por centro de custo |
| **Semanal** | Totais pagos e recebidos no período, agrupados por centro de custo |
| **Provisão** | Contas futuras ainda em aberto (matriz de compromissos) |
| **Por categoria** | Despesas liquidadas por categoria e centro de custo |
| **Resumo mensal** | Despesas liquidadas por centro de custo e mês |
| **Por conta bancária** | Saldo inicial, entradas, saídas e saldo de cada conta |
| **Demonstrativo** | Realizado versus projetado, com horizontes de 30, 60 ou 90 dias |
| **Contas a pagar** | Relatório específico de payables, inclusive atrasos |

No demonstrativo você vê **resultado realizado**, **resultado projetado** e **saldo final esperado**.

Nos relatórios **gerenciais** (diário, semanal, provisão, por categoria, contas a pagar), um lançamento rateado **aparece separado**: cada fatia no seu centro de custo e na sua categoria. O total continua o da conta. Já o relatório **por conta bancária** e o fluxo realizado tratam o movimento de caixa: uma saída só, na conta em que o dinheiro saiu.

---

## 14. Assistente de IA

**Menu:** Geral → Assistente de IA  
Também disponível no botão flutuante.

O assistente consulta os dados da organização e responde em linguagem natural. Exemplos de pergunta:

- Qual meu saldo atual?
- Quais contas vencem hoje?
- Como está meu caixa nos próximos 30 dias?
- Existem lançamentos pendentes de conciliação?
- Quais foram minhas maiores despesas este mês?
- Quais contas estão atrasadas?

Você pode abrir conversas novas, voltar ao histórico e excluir conversas. A qualidade das respostas depende da configuração feita em **Gestão → Inteligência Artificial** (ativação, modelo, chave de API). Se a IA estiver desconectada, o assistente não consegue consultar o financeiro.

---

## 15. Usuários, perfis e auditoria

### 15.1 Usuários

**Menu:** Gestão → Usuários

Cadastro: nome completo, e-mail, telefone, CPF, senha (mínimo 8 caracteres) e um ou mais **perfis de acesso**.

Quem o usuário é no sistema (o que ele pode clicar) vem dos perfis, não de um campo solto na ficha.

### 15.2 Perfis de acesso

**Menu:** Gestão → Perfis de acesso

*Defina o que cada grupo de usuários pode fazer.*

Um perfil tem nome, descrição e uma lista de permissões (visualizar, criar, editar, excluir, baixar, conciliar, exportar relatórios etc.), agrupadas por módulo.

**Perfis padrão:**

| Perfil | Uso típico |
|--------|------------|
| **Administrador** | Acesso completo à organização |
| **Financeiro** | Operação diária: lançamentos, cadastros, baixas, transferências e recorrências. Sem executar/desfazer conciliação nem exportar relatórios |
| **Gestor Financeiro** | Tudo da operação, mais conciliação e exportação de relatórios |
| **Auditor** | Somente leitura, com acesso à auditoria |
| **Consulta** | Somente leitura |

Menus e botões que você não tem permissão simplesmente não aparecem.

### 15.3 Auditoria

**Menu:** Gestão → Auditoria

*Trilha de auditoria das operações financeiras.*

Eventos registrados: Inclusão, Alteração, Exclusão, Baixa, Estorno de baixa, Reabertura de conta, Parcelamento, Recorrência, Conciliação e Desfazer conciliação.

Colunas: Evento, Entidade, Usuário, Data/hora. É possível filtrar por tipo de ação.

### 15.4 Inteligência Artificial (configuração)

**Menu:** Gestão → Inteligência Artificial

Tela técnica: ativar a integração, endpoint compatível com a API da OpenAI, modelo, temperatura, máximo de tokens, prompt da organização e chave de API. Há teste de conexão e indicação se a IA está conectada, desconectada ou desabilitada.

A maioria dos usuários do financeiro não precisa desta tela.

---

## 16. Fluxo recomendado para começar

1. Cadastre as **contas bancárias** com o saldo inicial correto.
2. Cadastre **categorias** (e subcategorias, se precisar).
3. Cadastre **centros de custo** e **empresas**, se a operação usar essa classificação.
4. Cadastre os **cartões**, com dia de fechamento, vencimento e conta de pagamento.
5. Lance as **contas a pagar e a receber** em aberto (ou importe a planilha). Se uma nota misturar categorias ou centros de custo, use o **rateio**.
6. Crie **recorrências** para o que se repete todo mês.
7. Conforme os pagamentos acontecem, registre as **baixas**.
8. No fechamento do cartão, **feche a fatura** e baixe a conta da fatura.
9. Importe o **OFX** e faça a **conciliação**.
10. Acompanhe o **dashboard**, o **fluxo projetado** e os **relatórios**.

---

## 17. Perguntas frequentes

**Por que o saldo da conta não mudou depois que eu criei um lançamento?**  
O saldo só muda com **baixa** (ou transferência). Criar a conta a pagar registra o compromisso; pagar é a baixa.

**Por que não consigo baixar uma compra de cartão na lista?**  
Compras de cartão liquidam no pagamento da **fatura**. Feche a fatura e baixe a conta “Fatura …”.

**Centro de custo e conta bancária são a mesma coisa?**  
Não. Conta bancária é o dinheiro no banco. Centro de custo é a área/departamento. Um aluguel pode sair da conta corrente (banco) e ser do centro de custo Administrativo.

**Transferência conta como despesa?**  
Não. Ela só move saldo entre contas. No fluxo realizado aparece identificada como transferência para não misturar com resultado operacional.

**O que entra no fluxo realizado versus no projetado?**  
Realizado: o que já foi pago/recebido (data da baixa). Projetado: o que ainda vai vencer (saldo em aberto).

**Posso parcelar e também usar cartão?**  
Sim. No cartão, cada parcela cai em uma fatura mensal, com a mesma data de compra.

**Comprei vários tipos de produto no mesmo boleto. Crio várias contas?**  
Não. Lance **uma** conta com o valor do boleto e ative **Ratear este lançamento**. Informe categoria, centro de custo e valor de cada fatia. A conciliação usa a conta única; os relatórios separam as fatias.

**O rateio muda o valor que sai no banco?**  
Não. O banco vê o total. O rateio só classifica o gasto (categoria e centro de custo) para relatórios e dashboard.

**A importação de planilha cria receitas?**  
Não. As linhas consideradas entram como **contas a pagar**.

**Cancelei um lançamento por engano. Consigo reabrir?**  
Cancelamento não reabre. Exclusão também é definitiva. Para um item **liquidado**, use desfazer baixa ou reabrir conta.

**Nem todos os menus aparecem para mim.**  
O perfil de acesso esconde o que você não pode usar. Peça ao administrador a permissão adequada.

---

## 18. Glossário

| Termo | Significado |
|-------|-------------|
| **A pagar** | Conta que a organização deve pagar |
| **A receber** | Conta que a organização deve receber |
| **Aberto** | Lançamento ainda não liquidado |
| **Baixa** | Registro de pagamento ou recebimento |
| **Cancelado** | Lançamento anulado |
| **Categoria** | Classificação do tipo de receita ou despesa |
| **Centro de custo** | Área ou departamento responsável pelo lançamento |
| **Conciliação** | Conferência entre extrato bancário e lançamentos |
| **Conta bancária** | Conta operacional (corrente, poupança, investimento) |
| **Empresa** | Classificação do lançamento por razão social |
| **Fatura** | Agrupamento de compras de um ciclo do cartão |
| **Fechamento** | Dia do mês em que o ciclo do cartão encerra |
| **Fluxo projetado** | Previsão com base em vencimentos em aberto |
| **Fluxo realizado** | Movimento já efetivado (baixas) |
| **Liquidado** | Valor totalmente pago ou recebido |
| **OFX** | Formato de extrato bancário para importação |
| **Parcial** | Parte do valor já baixada |
| **Parcela** | Fatia de um lançamento dividido no tempo |
| **Rateio** | Divisão de um único lançamento entre categorias e centros de custo, sem criar várias contas |
| **Recorrência** | Modelo que gera lançamentos periódicos |
| **Tenant / organização** | Empresa isolada no sistema (os dados não se misturam) |
| **Transferência** | Movimento entre duas contas bancárias |
| **Vencidos** | Lançamentos com vencimento anterior a hoje e ainda em aberto |

---

*Documento voltado ao usuário final do Financeiro Apolar. Menus e botões podem variar conforme o perfil de acesso.*
