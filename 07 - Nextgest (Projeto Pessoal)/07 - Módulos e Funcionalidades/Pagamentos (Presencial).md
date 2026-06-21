---
projeto: Nextgest
tipo: módulo
status: implementado
criado: 2026-06-22
tags: [nextgest, pagamentos, financeiro, vendas]
---

# Pagamentos — etapa 1 (presencial)

> Projeto: [[Nextgest - Visão Geral]] · Modelo: [[Modelo de Dados - Pagamentos]]
> (§1, §4.2, §7) · Decisões: [[Decisões de Arquitetura]] (D20, D21). Liga-se a
> [[Vendas e Comanda]].

> [!important] Fronteira — só PRESENCIAL nesta etapa
> Sem gateway: nada de `gateways_pagamento`, `cartoes_tokenizados`,
> `webhooks_pagamento`, chamada externa, credenciais/segredos ou tokenização. Os
> campos de gateway em `pagamentos` (`gateway_id`, `gateway_transacao_id`,
> `pix_copia_cola`, `link_pagamento`) existem mas ficam **sem uso**. O gateway
> (Mercado Pago) é a **etapa 2**, com a direção do Fabio.

## O que é
Ao fechar uma comanda, registra **um ou mais pagamentos presenciais** (dinheiro,
cartão débito/crédito, pix, maquininha). Suporta **pagamento dividido** (N formas
somando o total).

- Tabela `pagamentos` (já existia na migration **190004**; confere com §4.2 — **sem
  migration nova**). Model `Pagamento` (`Pagamento::METODOS`/`METODO_LABEL`).
- Regras no serviço `App\Services\Venda\Comanda`.

## Regras (presencial)
- Cada pagamento: `gateway_id` nulo, `status = aprovado` na hora, `pago_em = agora`,
  `criado_por_user_id` = quem registrou (§7).
- **`pagarPresencial(venda, [{metodo, valor}], userId)`**: valida método e valor > 0;
  a **soma precisa ser igual ao `valor_total`** (não grava acima do devido); cobrindo
  o total, dispara a **finalização** (baixa de estoque + comissão — reaproveita a
  lógica da 2B, não duplica). Tudo numa transação (se faltar estoque, nada é gravado).
- **`pagar(venda, userId)`** continua existindo como atalho (um pagamento de dinheiro
  do total) — usado em testes/seed e fluxos simples.
- **Cancelar venda paga:** os pagamentos `aprovado` viram **`estornado`** (além do
  estorno de estoque que já existia).

## UI (painel)
- **Modal "Fechar e pagar"** (detalhe da comanda): escolher a(s) forma(s) e valor(es),
  com **somatório e validação ao vivo** ("Falta/Excede R$"); botão de confirmar
  desabilitado enquanto a soma ≠ total; **"Dividir pagamento"** adiciona formas.
- **Troco (dinheiro):** campo opcional "valor recebido" que **só calcula o troco na
  UI** — não grava pagamento acima do total.
- **Detalhe da venda:** lista os pagamentos (forma, valor, status).

## Demo
As vendas pagas do histórico passam a ter pagamento(s) presencial(is) coerentes
(formas variadas, somando o total; uma comanda com pagamento **dividido**). Há um
**backfill** idempotente que cria o pagamento das vendas pagas antigas (na data da
venda), para a base ficar coerente.

## Testes
`tests/Feature/Painel/PagamentoTest.php` (8): aprovado na hora (gateway nulo/pago_em/
criado_por), soma = total libera a venda (baixa + comissão), pagamento dividido,
rejeita soma abaixo/acima do total, método inválido, cancelar paga estorna os
pagamentos, e o atalho `pagar()`. Suíte **203 verde**.

## Próximo (etapa 2)
Gateway **Mercado Pago** (online: Pix/cartão): cobrança via adapter `GatewayPagamento`,
`status = pendente` até o **webhook** confirmar, credenciais criptografadas. Ver
[[Modelo de Dados - Pagamentos]] §2/§7.

## Relacionado
- [[Vendas e Comanda]] · [[Modelo de Dados - Pagamentos]] · [[Dashboard do Dono]].
