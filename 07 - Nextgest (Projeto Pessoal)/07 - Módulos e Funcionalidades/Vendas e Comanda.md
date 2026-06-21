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
- **Comissão (snapshot, completa na 2C):** ao pagar, por item, grava
  `percentual_comissao` e `valor_comissao` resolvendo a **precedência**: (1) override
  do profissional (`comissoes_profissional`) para aquele serviço/produto → (2) %
  padrão do cadastro (`produtos.percentual_comissao` / `servicos.percentual_comissao`)
  → (3) nenhuma. `profissional_id` = quem executou/vendeu. Ver [[Comissões]].
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
- **Fechar/pagar:** registra **pagamento(s) presencial(is)** (forma + valor; aceita
  dividido); quando a soma cobre o `valor_total`, a venda vira `paga` e dispara baixa
  de estoque + comissão. Ver [[Pagamentos (Presencial)]].

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

## Finalizar atendimento → comanda (a partir da agenda do profissional) — 2026-06-22
Quando o profissional termina de atender, ele — **na própria agenda** — abre o detalhe do
agendamento e clica em **"Finalizar atendimento"** (`Agenda\Index::finalizarAtendimento`):
1. **conclui** o atendimento (se ainda não estiver) respeitando as transições;
2. **gera/abre** a comanda via `Comanda::apartirDeAgendamento` (reuso, **idempotente** — se
   já existe, abre a existente) e leva ao detalhe.

A comanda nasce **travada**: **cliente** = o do agendamento e **profissional/vendedor** =
quem atendeu; os **itens de serviço** já vêm com esse profissional (snapshot). No detalhe,
cliente e "quem vendeu" aparecem com cadeado (não editáveis). O profissional escolhe a
**forma de pagamento** e, se houver, **adiciona produtos** (o profissional do item já vem
pré-preenchido com quem atendeu) — a comissão é por item.

**"Quem vendeu/atendeu" (`vendas.profissional_id`)** — responsável/vendedor da comanda
(migração aditiva). Em **avulsas** (balcão) é um campo selecionável na "Nova comanda" e no
detalhe, e **pré-preenche** o profissional dos itens novos (ajustável por item). A comissão
continua **por item** (`venda_itens.profissional_id`); esta coluna é só o padrão da venda.

**Permissão** (`finalizar_atendimento_proprio`, novo): o **Profissional** finaliza só os
**próprios** atendimentos e gere a comanda **daquele atendimento** — sem acesso a comandas
avulsas nem às de outros. Decidido por `App\Policies\VendaPolicy::gerir` (criar_venda **ou**
profissional do próprio atendimento). A rota `vendas/{venda}` deixou de usar
`can:criar_venda` (a policy decide por comanda); o **índice** de comandas segue exigindo
`criar_venda` (Dono/Gerente/Recepção). Reaproveita `apartirDeAgendamento`, a comissão
(2A/2C) e o pagamento presencial — nada reescrito.

## Testes
`tests/Feature/Painel/FinalizarAtendimentoTest.php` (7): finalizar gera comanda travada
(cliente + profissional) e itens de serviço; **idempotente**; profissional **não** finaliza
de outro; acessa a comanda do **próprio** atendimento mas **não** avulsas; vendedor/cliente
travados na finalização; "quem vendeu" **pré-preenche** o item e a comissão grava por item.

`tests/Feature/Painel/VendasTest.php` (10): abrir, snapshot do item,
`total = bruto − desconto`, **pagar baixa estoque** (com `venda_id`) + **comissão
snapshot**, **bloqueio acima do estoque**, **cancelar paga estorna**, cancelar aberta
não mexe no estoque, **a partir de agendamento copia serviços** (idempotente), não
editar paga, e permissão (Profissional 403). Suíte **181 verde**.

## Fora do escopo (próximas)
- **Pagamentos** (forma/gateway/status de pagamento) — "pagar" aqui só muda status.
- ~~2C: comissões~~ — ✅ feito (override por profissional + % padrão de serviço +
  relatório). Ver [[Comissões]].
- Ainda pendente: **desconto por item** (hoje o desconto é só no total da venda).

## Relacionado
- [[Produtos e Estoque]] · [[Modelo de Dados - Produtos e Vendas]] ·
  [[Modelo de Dados - Núcleo de Agendamento]] (agendamento_servico).
