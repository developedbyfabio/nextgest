---
projeto: Nextgest
tipo: módulo
status: implementado
criado: 2026-06-22
tags: [nextgest, comissao, financeiro, vendas, fatia-2]
---

# Comissões (Fatia 2C)

> Projeto: [[Nextgest - Visão Geral]] · Decisão: [[Decisões de Arquitetura]] (D14) ·
> Modelo: [[Modelo de Dados - Produtos e Vendas]] (3.7). Fecha a comissão iniciada
> (básica) na [[Vendas e Comanda]] (2B).

## O que é
Comissão do profissional por item de venda, com **% padrão** por serviço/produto e
**override por profissional**, mais um **relatório** de comissão a pagar.

- A comissão é gravada como **snapshot** em `venda_itens` (`percentual_comissao`,
  `valor_comissao`) ao **pagar** a comanda (ver [[Vendas e Comanda]]).
- Relatório/overrides: `App\Livewire\Painel\Comissoes\Index` (rota `painel.comissoes`).
- Agregação: `App\Services\Dashboard\Metricas::comissoesPorProfissional()`.
- Model do override: `App\Models\ComissaoProfissional` (`comissoes_profissional`,
  já existia na migration 190003).

## Precedência da % (em `App\Services\Venda\Comanda`)
1. **Override do profissional** (`comissoes_profissional`) para aquele serviço/produto;
2. **% padrão do cadastro** — `produtos.percentual_comissao` ou
   `servicos.percentual_comissao` (esta última **adicionada na 2C**, migration aditiva);
3. **Nenhuma** comissão (null).
O override é por `(user_id, servico_id)` **ou** `(user_id, produto_id)` e não vaza
para outro profissional.

## UI (painel)
- **Relatório** (`painel.comissoes`, permissão **`ver_financeiro`** — Dono): total
  geral + comissão por profissional, com filtros de período/unidade (reusa o padrão do
  dashboard). Estados de loading/vazio.
- **Comissões personalizadas** (modal): escolhe o profissional e define a % por serviço
  e por produto (em branco = usa a % padrão do cadastro → remove o override).
- O **% padrão de serviço** entra no CRUD de Serviços (`painel.servicos`); o de produto
  já existia (`painel.produtos`).
- O **"Comissão a pagar"** do dashboard (2D) usa o mesmo snapshot.

## Demo
`nextgest:demo`: serviços com % padrão (40–50%) e um override (Jorge 50% no "Corte
masculino"). As vendas pagas do histórico gravam a comissão conforme a precedência.

## Testes
`tests/Feature/Painel/ComissaoTest.php` (8): % padrão de serviço, sem %/sem override →
sem comissão, override tem precedência (serviço e produto), override não vaza, relatório
agrega por profissional, salvar/remover override pela UI, e permissão (`ver_financeiro`).

## Fora do escopo / próximas
- **Desconto por item** (hoje só no total da venda).
- **Pagamentos** (forma/gateway/status).

## Relacionado
- [[Vendas e Comanda]] · [[Dashboard do Dono]] (comissão a pagar) ·
  [[Modelo de Dados - Produtos e Vendas]] (3.7).
