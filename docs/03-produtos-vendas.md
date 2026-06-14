# 03 — Produtos e Vendas

Migration: `database/migrations/tenant/2026_06_14_190003_*` (cadastro) e
`...190004_*` (vendas/itens/estoque).

## Tabelas

- **categorias_produto** — organização opcional.
- **produtos** — `preco_venda`, `preco_custo` (lucro), `controla_estoque`,
  `percentual_comissao` (padrão), `sku`.
- **produto_unidade** — estoque **por filial** (`quantidade`).
- **movimentacoes_estoque** — histórico (entrada/saida/ajuste), liga a `venda_id`.
- **vendas** (comanda) — `unidade_id`, `cliente_id` (nullable, balcão),
  `agendamento_id` (nullable), `status` (aberta/paga/cancelada),
  `valor_bruto`, `desconto`, `valor_total`.
- **venda_itens** — serviço **ou** produto; snapshots de `descricao`,
  `preco_unitario`, `percentual_comissao`, `valor_comissao`;
  `coberto_por_assinatura` + `assinatura_id` (clube).
- **comissoes_profissional** — override de % por profissional.

## Regras (a implementar)

- **Comanda unificada** (D13): venda reúne produtos e serviços; pode nascer de um
  agendamento (serviços copiados de `agendamento_servico` com snapshot) ou ser
  avulsa.
- **Baixa de estoque**: ao pagar, item de produto com `controla_estoque` gera
  `movimentacao_estoque` de saída e reduz `produto_unidade.quantidade`.
- **Comissão** (D14): override do profissional → % do item → nenhuma. Grava
  `valor_comissao` como snapshot.
- **Desconto**: só no total da venda (sem desconto por item).
