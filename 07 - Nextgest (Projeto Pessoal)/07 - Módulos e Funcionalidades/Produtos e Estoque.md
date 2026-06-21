---
projeto: Nextgest
tipo: módulo
status: implementado
criado: 2026-06-21
tags: [nextgest, produtos, estoque, fatia-2]
---

# Produtos e Estoque (Fatia 2A)

> Projeto: [[Nextgest - Visão Geral]] · Modelo: [[Modelo de Dados - Produtos e Vendas]]
> · Decisões: [[Decisões de Arquitetura]] (D12). Primeira parte da Fatia 2; a
> **venda/comanda** (vendas/venda_itens/comissão) é a **2B**.

## O que é
Cadastro de **produtos**, **categorias** e **estoque por unidade** no painel do tenant.

- Componente: `App\Livewire\Painel\Produtos\Index` (rota `painel.produtos`).
- Models: `Produto`, `CategoriaProduto`, `ProdutoUnidade`, `MovimentacaoEstoque`.
- Serviço: `App\Services\Estoque\MovimentadorEstoque` (movimenta estoque + registra
  histórico, de forma atômica; reusável na 2B para baixa por venda).

> [!important] Reconciliação (Tarefa 0) — as tabelas JÁ EXISTIAM
> As 4 tabelas desta fatia já vinham do scaffold: `categorias_produto`, `produtos`,
> `produto_unidade` (migration **190003**) e `movimentacoes_estoque` (migration
> **190004**, junto de `vendas`/`venda_itens`). **Nenhuma migration nova** foi
> necessária — só models, permissões, UI e demo. (Uma migration duplicada chegou a
> ser criada e removida.)

## Permissões
- `criar_produto` / `editar_produto` — catálogo e categorias (Dono/Gerente). Reusadas
  do scaffold (espelham `criar_servico`/`editar_servico`).
- **`gerir_estoque`** (nova) — movimentações de estoque. Dono/Gerente **e Recepção**.
- A página `painel.produtos` abre para quem tem `editar_produto` **ou** `gerir_estoque`
  (gate no `mount`); as ações de catálogo (criar/editar/inativar/categorias) exigem
  `editar_produto`; o botão **Estoque** exige `gerir_estoque`. Assim a Recepção entra
  só para mexer no estoque.

## UI (painel, claro/escuro, responsivo)
- **Lista:** busca (nome/SKU, debounce), filtro por categoria e por status
  (ativos/inativos/todos), **paginação** (12/pág.); estados de **loading** (skeleton),
  **vazio** (com CTA) e tabela. Mostra nome, categoria, preço, **estoque total** (badge
  verde/vermelho) quando controla estoque, e status.
- **Criar/editar produto:** `flux:modal` com validação — nome, categoria, SKU, preço de
  venda, preço de custo (opcional), comissão padrão % (opcional), **controla estoque**
  (switch), ativo.
- **Categorias:** modal com CRUD inline (criar/renomear/alternar ativo).
- **Estoque por unidade:** modal (produtos com `controla_estoque`) — estoque atual por
  filial, formulário de **entrada** (somar) ou **ajuste** (definir total) com motivo, e
  **histórico** das últimas movimentações.
- **Inativar** = `ativo = false` (não apaga), confirmado por `flux:modal`.

## Movimentação de estoque (regra)
- `movimentacoes_estoque.quantidade` é o **delta sinalizado** (+entra / −sai); o estoque
  da filial = soma dos deltas. **Entrada** soma `qtd`; **ajuste** grava
  `delta = alvo − atual`. Tudo numa transação, com lock pessimista na linha do estoque
  (real no MySQL; SQLite de teste ignora o lock, a lógica de soma segue correta).
  Estoque nunca fica negativo (clamp em 0).

## Demo
`nextgest:demo` semeia 3 categorias e 7 produtos (barbearia/salão): pomada, cera, óleo,
shampoo (esgotado, p/ mostrar o estado vazio), água, cerveja e um "Vale-presente" (não
controla estoque). Estoque inicial por movimentação (idempotente: só semeia se o
produto ainda não tem movimentação).

## Testes
`tests/Feature/Painel/ProdutosTest.php` (9): CRUD + validação, inativar/reativar,
categorias, **entrada soma** / **ajuste define** o estoque, serviço `MovimentadorEstoque`
(inclui clamp em 0) e **permissões** (Profissional 403; Recepção entra só p/ estoque,
não edita catálogo).

## Relacionado
- [[Modelo de Dados - Produtos e Vendas]] · [[Modelo de Dados - Núcleo de Agendamento]]
  (unidades) · próxima: **Fatia 2B** (vendas/comanda + comissão + baixa de estoque).
