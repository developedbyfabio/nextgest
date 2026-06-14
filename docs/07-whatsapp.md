# 07 — WhatsApp (API oficial — Meta Cloud)

Migration: `database/migrations/tenant/2026_06_14_190005_*`.

Automações via API oficial (D23): número verificado e templates aprovados.
Credenciais criptografadas.

## Tabelas

- **whatsapp_config** — `telefone`, `phone_number_id`, `business_account_id`,
  `token` (cast `encrypted` no model — a implementar), `verificado`, `ativo`.
- **whatsapp_templates** — `nome`, `conteudo`, `categoria`, `idioma` (pt_BR),
  `status_aprovacao` (pendente/aprovado/rejeitado).
- **whatsapp_automacoes** — `evento` (lembrete_agendamento,
  confirmacao_agendamento, cancelamento_agendamento, aniversario_cliente),
  `template_id`, `antecedencia_minutos`, `ativo`.
- **whatsapp_mensagens** — log de envio; `status` (enviado, entregue, lido,
  falhou), `gateway_message_id`, `enviado_em`.

## Regras (a implementar)

- Disparo das automações por eventos do agendamento (criar/cancelar) e job
  agendado (lembrete por `antecedencia_minutos`, aniversário).
- O envio só ocorre com config `ativo` + número `verificado` + template
  `aprovado`.

## Em aberto (A confirmar)

- Credenciamento da API oficial (número, verificação, aprovação de templates na
  Meta).
- Ao implementar o model `WhatsappConfig`, aplicar cast `encrypted` no `token`
  (mesmo padrão de `GatewayPagamento.credenciais`).
