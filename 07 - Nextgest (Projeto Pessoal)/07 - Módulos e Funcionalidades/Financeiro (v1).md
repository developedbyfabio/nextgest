---
projeto: Nextgest
tipo: módulo
status: implementado
criado: 2026-06-23
tags: [nextgest, financeiro, faturamento, lucro, contador, csv, tenant]
---

# Financeiro (v1) — números do negócio, prontos pro contador

> Projeto: [[Nextgest - Visão Geral]] · Reusa [[Dashboard do Dono]] (`Metricas`),
> comanda/`Venda`, comissões. Decisão: [[Decisões de Arquitetura#D43]]. RBAC: D39/D40.

## O que é (e responsabilidade)
Item-pai **"Financeiro"** no painel com os **números do negócio** — faturamento, recebimentos por
forma, lucro **bruto** — por período, exportáveis pro contador. **Banner fixo na tela e no export:**
"Estes são os números do seu negócio para organização e para entregar ao seu contador. Não é
cálculo de impostos nem substitui um contador." **NÃO** calcula imposto/DAS/regime e **NÃO** promete
lucro **líquido** (despesas = v2).

## Fonte de verdade única (bate com o dashboard)
`App\Services\Financeiro\ResumoFinanceiro` usa **o mesmo critério de receita do `Metricas`**:
comanda **paga** (`vendas.status='paga'`) + `vendas.data` no período (+ unidade). Filtro EXTRA por
**profissional** (responsável da comanda). Com profissional nulo, `faturamento()` é **idêntico** ao
`Metricas::faturamento()` — garantido por teste. Tudo **SQL agregado** (set-based), contagem de
query **constante** (teste de teto), nunca loop.

## Números (v1)
- **Faturamento (receita bruta)** = soma de `valor_total` das pagas. **Ticket médio** e **nº de
  vendas** (mesmos do dashboard) — `totais()` numa query.
- **Recebimentos por forma** = `pagamentos` aprovados das pagas do período, `GROUP BY metodo`. A
  soma == faturamento (os pagamentos de uma comanda paga somam o total). Filtro opcional por forma.
- **Lucro bruto** = receita − **comissões** (`venda_itens.valor_comissao`, snapshot) − **CPV**.
  **A fórmula é mostrada na tela** ("como calculamos"), sem caixa-preta.
- **CPV** = Σ (`produtos.preco_custo` × quantidade) dos itens-produto vendidos. **Ressalva (na
  tela):** usa o custo de compra **atual** (sem snapshot histórico — `venda_itens` não guarda
  custo); produtos sem custo não entram; se nenhum tem custo, CPV = R$ 0,00.
- **Série** de faturamento por **dia** (uma query; `DATE`/`date` driver-aware).

## Competência ("recebido no período")
`vendas.data` + `status=paga` (mesmo do dashboard) — não `pago_em`. No presencial `pago_em ≈
vendas.data` (mesmo dia), e isso garante que o Financeiro **bate** com o dashboard.

## Export CSV (pro contador)
`exportarCsv()` (StreamedResponse): cabeçalho com **estabelecimento + período + o aviso**, depois
os agregados (receita, recebido por forma, comissões, CPV, lucro bruto, nº vendas, ticket). **Sem
PII** (é agregado; nada de lista de clientes). Confirmado por teste (não contém nome/telefone).

## Navegação e permissão
Grupo **"Financeiro"** no `painel.blade.php` (item "Visão financeira"), no padrão de
"Operação"/"Gestão", dentro de `@can('ver_financeiro')`. Rota `painel.financeiro` sob
`auth:web` + **`can:ver_financeiro`** (camada dupla: rota + `abort_unless` no mount). **`ver_financeiro`
é só do Dono** (D40; Gerente/Recepção/Profissional não têm) → 403 cru para os demais. Gate por
**permissão, nunca `hasRole`**. **Sem migração** (v1 é leitura).

## Testes (suíte 404 → 412)
`tests/Feature/Painel/FinanceiroTest.php` (7): faturamento **== dashboard**; lucro bruto = receita −
comissões − CPV (cenário 110 − 5 − 20 = 85); recebimentos somam o faturamento; **gate** Dono ok /
Recepção+Profissional **403** (via mount — mesmo `ver_financeiro` da rota); banner visível + "lucro
líquido" **não** prometido; **CSV** com aviso e **sem PII**; isolamento por tenant.
`ContagemQueriesTest` (+1): financeiro com contagem **constante** (5 vs 45 vendas).

> **Nota de teste:** o gate é verificado por `Livewire::test` (mount `abort_unless`), não por GET
> HTTP — `actingAs` + `EscoparAutenticacaoPorTenant` oscila (302) no processo multi-tenant de teste;
> em produção o login real seta `_tenant_sessao` e o GET é 200. O gate (`ver_financeiro`) é o que importa.

## Verificação
Faturamento bate com `Metricas` (162120 no volumeteste). Rota guest → 302 login (sem 500).
`laravel.log` vazio. Contagem de query: totais/comissões/CPV/recebimentos/série = 1 cada.

## v2 (próximo — NÃO neste)
**Lançamento de despesas** (aluguel, fornecedores…) p/ fechar o **lucro líquido** real + relatórios
por categoria de despesa. Possível snapshot de custo em `venda_itens` p/ CPV histórico.
