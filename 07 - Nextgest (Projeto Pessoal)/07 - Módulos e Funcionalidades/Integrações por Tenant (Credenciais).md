# Integrações por Tenant (Credenciais cifradas)

> Projeto: [[Nextgest - Visão Geral]] · Fase **0b** · Ver [[Decisões de Arquitetura#D38]] e [[Decisões de Arquitetura#D21]]

Cada estabelecimento guarda as credenciais das integrações dele (token do Mercado Pago,
token do WhatsApp) **no banco do próprio tenant**, **cifradas** (`encrypted`). Nunca em
texto puro, nunca em `.env`, nunca em log. É o **primeiro consumidor real** das flags da
[[Recursos por Tenant (Feature Flags)|Fase 0a]].

> [!warning] Limite de escopo (0b)
> Esta fase é **só armazenamento + UI**. NÃO chama API externa: sem "testar conexão", sem
> webhook, sem SDK em runtime. As chamadas reais são Fases 2/4 (dependem de VPS/sandbox).

## Onde mora (cofres REUSADOS — sem tabela nova)
- **Mercado Pago** → tabela `gateways_pagamento`, model `App\Models\GatewayPagamento`
  (`credenciais` cast `encrypted:array`). O token vai em `credenciais['access_token']`,
  `provedor='mercadopago'`. Já existe o `App\Services\Pagamentos\GatewayResolver` que a
  Fase 2 vai usar para cobrar — por isso reusamos, não duplicamos.
- **WhatsApp** → tabela `whatsapp_config`, model **novo** `App\Models\WhatsappConfig`
  (`token` cast `encrypted`, `$hidden`). `telefone`/`phone_number_id`/`business_account_id`
  são config **não-secreta**.
- `clube` **não** tem credencial (consome o gateway). Sem migração nesta fase (as duas
  tabelas já vinham do baseline de tenant).

## Fonte única
- `App\Enums\Integracao` (`mercadopago`, `whatsapp`): mapeia cada integração →
  **recurso** (flag 0a), → **permissão** (spatie) e → **rota** do editor.
  - `mercadopago` ↔ recurso `gateway` ↔ permissão `gerenciar_pagamentos`
  - `whatsapp` ↔ recurso `whatsapp` ↔ permissão `gerenciar_whatsapp`

## Segurança do segredo (write-only)
- O campo de segredo **carrega vazio** (nunca renderiza o token de volta no value/HTML).
- Salvar: segredo **preenchido** → substitui; **vazio** → mantém o anterior.
- A tela mostra só **status** (configurado/não) + **máscara** `••••1234` — nunca o valor
  cheio. `$hidden` + cast `encrypted` impedem vazamento em serialização/log/dump.
- **Prova de cifragem** (verificada em 22/06/2026): valor cru de
  `gateways_pagamento.credenciais` no banco do tenant = `eyJpdiI6...` (payload cifrado do
  Laravel), **sem** o token em claro; o model lê de volta decifrado em memória.

## Tela (painel do tenant, gated)
- Menu **"Integrações"** no grupo "Gestão" (`painel.blade.php`), visível só para quem tem
  ao menos uma permissão de integração (Dono/Gerente).
- `App\Livewire\Painel\Integracoes\Index` — hub: um **card por integração disponível** =
  recurso ligado (`tenant_tem_recurso`) **+** o usuário tem a permissão dela. Nenhuma
  disponível → "Nenhuma integração disponível" (correto: tudo nasce desligado na 0a).
- `App\Livewire\Painel\Integracoes\MercadoPago` e `...\Whatsapp` — editores. Rotas
  (`routes/tenant.php`): índice em `painel.integracoes`; editores em
  `painel.integracoes.mercadopago` / `.whatsapp`, **gated** por
  `middleware('recurso:{slug}')` (0a) + `can:{permissão}`. Recurso off → **404**.

## Testes
`tests/Feature/Painel/IntegracoesTest.php` (efeito + HTTP por tenant):
- token salvo **cifrado**, lido decifrado em memória; cru no banco **difere** do texto puro;
- **write-only**: vazio mantém, preenchido substitui;
- **máscara**: HTML nunca contém o segredo em claro;
- **flag off** → índice sem card **e** editor **404**; **flag on** → card visível / editor **200**
  (request HTTP autenticada no contexto do tenant; toggle = um request por teste, gotcha 0a);
- **isolamento**: credencial do tenant A não aparece no B;
- **permissão**: Recepção (sem `gerenciar_pagamentos`) → **403** na tela e no editor.

## Próximas fases (na prateleira)
Fase 2 (gateway real: cobrança/checkout/webhook) e Fase 4 (envio real de WhatsApp) leem
estes mesmos cofres — sem migração nova. Dependem de VPS/sandbox (custo) e ficam fora daqui.
