# 04 — Clube de Assinatura

Migration: `...190003_*` (planos/benefícios/descontos/assinaturas) e `...190004_*`
(`usos_clube`).

## Tabelas

- **planos_clube** — `preco_mensal`, `periodicidade` (mensal), `ativo`.
- **plano_beneficios** — serviço incluído; `tipo` (ilimitado/cota),
  `cota_quantidade`, restrição de `dias_semana_permitidos` (json) e faixa de
  horário (`hora_inicio`/`hora_fim`).
- **plano_descontos** — desconto em serviço/produto/categoria/todos;
  `tipo_desconto` (percentual/valor), `valor`.
- **assinaturas_clube** — `cliente_id`, `plano_id`, `status` (ativa, suspensa,
  cancelada, inadimplente), `preco_contratado` (snapshot), `data_inicio`,
  `proxima_cobranca`, `gateway_id`, `gateway_assinatura_id`.
- **usos_clube** — consumo de benefício; `periodo_referencia` (ex.: "2026-06"),
  liga a `agendamento_id` e/ou `venda_item_id`. Índice
  `(assinatura_id, periodo_referencia)`.

## Regras (a implementar)

- **Validar benefício** ao agendar/fechar venda: serviço está num benefício do
  plano? Há cota no ciclo (conta `usos_clube` do período)? Respeita
  dia/horário?
- **Item coberto** (D15): ilimitado ou com cota → `venda_item` com
  `coberto_por_assinatura=true`, `preco_unitario=0`, `assinatura_id` setado;
  registra um `uso_clube`.
- **Item com desconto** (D17): não coberto mas com `plano_desconto` aplicável →
  `preco_unitario` já com desconto e `assinatura_id` setado.
- **Ciclo da cota por data de adesão** (D18): reinicia no dia do mês em que
  assinou (não no dia 1º).
- **Recorrência**: `proxima_cobranca` é o gancho; cobrança via gateway (ver 05).

## Em aberto

- Troca/upgrade de plano (cancela+cria nova vs altera a existente). **A confirmar.**
