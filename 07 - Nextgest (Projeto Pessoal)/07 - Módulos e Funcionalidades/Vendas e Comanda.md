---
projeto: Nextgest
tipo: módulo
status: implementado
criado: 2026-06-21
tags: [nextgest, vendas, comanda, financeiro, estoque, comissao, fatia-2]
---

# Vendas / Comanda (Fatia 2B)

> Projeto: [[Nextgest - Visão Geral]] · Modelo: [[Modelo de Dados - Produtos e Vendas]]
> (3.5/3.6/6) · Decisões: [[Decisões de Arquitetura]] (D13, D14). Continuação de
> [[Produtos e Estoque]] (2A). A agenda guarda o atendimento; a **venda** guarda o
> financeiro (papéis diferentes, doc §6).

## O que é
A comanda/venda: produtos + serviços, **avulsa** (balcão) **ou** a partir de um
**agendamento concluído**. Ao **pagar**: baixa de estoque + comissão básica (snapshot).

- Tabelas: `vendas`, `venda_itens` (já existiam na migration **190004** — Tarefa 0
  confirmou contra o doc; **nenhuma migration nova**).
- Models: `Venda`, `VendaItem`.
- Serviço de domínio: **`App\Services\Venda\Comanda`** (toda a regra).
- UI: `App\Livewire\Painel\Vendas\Index` (lista, `painel.vendas`) e `...\Detalhe`
  (`painel.vendas.detalhe`).
- Permissão: **`criar_venda`** (Dono/Gerente/Recepção).

## Regras (serviço `Comanda`)
- **Itens (snapshot):** cada item é OU serviço OU produto; grava `descricao` e
  `preco_unitario` no momento; `subtotal = preco_unitario × quantidade`.
- **Totais:** recalcula a cada mudança — `valor_bruto` (soma dos subtotais) e
  `valor_total = max(0, bruto − desconto)`; desconto nunca passa do bruto.
- **Baixa de estoque ao pagar:** para cada item de produto com `controla_estoque`, dá
  **saída** via `MovimentadorEstoque` (reusado da 2A — **não** reimplementado) na
  unidade da venda, gravando `venda_id` na movimentação.
- **Bloqueio:** não vende produto acima do estoque da unidade — `adicionarProduto`
  (soma o que já está na comanda) e `pagar` (confere agregado) lançam
  `EstoqueInsuficienteException` com mensagem clara.
- **Comissão básica (snapshot):** ao pagar, por item — produto usa
  `percentual_comissao` do cadastro; **serviço fica sem comissão** (a % padrão de
  serviço e o **override por profissional ficam para a 2C**). Grava
  `percentual_comissao` e `valor_comissao`; `profissional_id` = quem executou/vendeu.
- **Cancelar:** vira `cancelada` (não apaga). Se estava **paga**, **estorna** o estoque
  (entrada com `venda_id`) para não furar.
- **Comanda só edita quando `aberta`** (`VendaNaoEditavelException`).

## Fluxos
- **Balcão:** "Nova comanda" → unidade + cliente **opcional** (anônimo ok) → detalhe;
  adicionar produtos (com quantidade) e serviços, profissional por item, desconto.
- **A partir de agendamento concluído:** na agenda, no detalhe do atendimento
  concluído, **"Gerar comanda"** → `Comanda::apartirDeAgendamento` copia os serviços do
  `agendamento_servico` (snapshot), profissional = quem atendeu; **idempotente** (não
  duplica). Leva ao detalhe para adicionar produtos.
- **Fechar/pagar:** status → `paga` (sem forma de pagamento ainda — bloco
  **Pagamentos** depois); dispara baixa de estoque + comissão.

## UI (painel, claro/escuro, responsivo)
- **Lista:** filtros por status/período/unidade, busca por cliente; estados loading
  (skeleton)/vazio (CTA); colunas data, cliente, status (badge), total; paginação.
- **Detalhe:** itens (adicionar/remover via `flux:modal`, sem `confirm` nativo),
  profissional por item, **desconto e totais ao vivo**, botões **fechar/pagar** e
  **cancelar** com confirmação por modal.

## Demo
`nextgest:demo` semeia 3 comandas (idempotente): uma **paga** de balcão (produto +
serviço, com desconto → baixa estoque + comissão), uma **aberta**, e uma a partir de um
**atendimento concluído**.

## Testes
`tests/Feature/Painel/VendasTest.php` (10): abrir, snapshot do item,
`total = bruto − desconto`, **pagar baixa estoque** (com `venda_id`) + **comissão
snapshot**, **bloqueio acima do estoque**, **cancelar paga estorna**, cancelar aberta
não mexe no estoque, **a partir de agendamento copia serviços** (idempotente), não
editar paga, e permissão (Profissional 403). Suíte **181 verde**.

## Fora do escopo (próximas)
- **Pagamentos** (forma/gateway/status de pagamento) — "pagar" aqui só muda status.
- **2C:** relatório de comissões e **override por profissional** (`comissoes_profissional`,
  tabela já existe); % padrão de serviço; desconto por item.

## Relacionado
- [[Produtos e Estoque]] · [[Modelo de Dados - Produtos e Vendas]] ·
  [[Modelo de Dados - Núcleo de Agendamento]] (agendamento_servico).
