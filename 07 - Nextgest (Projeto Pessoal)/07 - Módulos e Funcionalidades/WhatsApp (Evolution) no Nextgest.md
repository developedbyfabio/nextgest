---
projeto: Nextgest
tipo: módulo
status: Fatia 1 (envio de teste manual) — dev
criado: 2026-06-27
tags: [nextgest, whatsapp, evolution, tenant, integracao]
---

# WhatsApp (Evolution) no Nextgest

> Envio de WhatsApp pela **Evolution única** (ver [[Infra — Evolution API (WhatsApp)]]). **Fatia 1**:
> config por tenant + serviço/driver + envio MANUAL de teste. **Sem automação** (Fatia 3) e **sem
> tela de QR** (Fatia 2). Decisão: [[Decisões de Arquitetura]] (**D75**). Só **dev**.

## Modelo
- **1 Evolution, 1 instância por salão** → `ng_{tenantId}` (único na Evolution compartilhada).
- **Credenciais separadas (regra dura):**
  - **Key GLOBAL** (administra a Evolution) = **infra**, só no `.env` do Nextgest
    (`EVOLUTION_BASE_URL`, `EVOLUTION_API_KEY`, `EVOLUTION_TIMEOUT`) → `config('whatsapp.*')`.
  - **No banco do tenant** (`whatsapp_config`): só `instancia` (nome) + `instancia_token` (token
    DAQUELA instância — `encrypted` + `$hidden`) + `status_conexao`. **Nunca** a chave-mestra.

## Código
- `config/whatsapp.php` — base_url/api_key/timeout/prefixo (do `.env`).
- Migration aditiva (tenant): `whatsapp_config` + `instancia`, `instancia_token`, `status_conexao`.
- `App\Services\WhatsApp\WhatsAppGateway` (contrato) ← `EvolutionGateway` (HTTP puro por nome de
  instância): `criarInstancia`, `conectar` (QR), `statusConexao`, `enviarTexto` (apikey = token da
  instância, ou global se ausente), `normalizarNumero` (BR → 55, sem `+`). Erro/timeout →
  `WhatsAppException` (log sem segredo).
- `App\Services\WhatsApp\WhatsAppService` (por tenant): lê/grava `whatsapp_config` e delega ao gateway.
- Binding `WhatsAppGateway → EvolutionGateway` no `AppServiceProvider` (trocar provedor = trocar binding).

## Comandos (CLI; gated pelo recurso `whatsapp`)
```bash
# 1) conectar (cria/garante a instância do salão + salva o QR em PNG p/ escanear)
php artisan nextgest:whatsapp-conectar {tenant}
#    QR salvo em storage/tenant{tenant}/app/whatsapp-qr-{tenant}.png
#    (WhatsApp → Aparelhos conectados → Conectar aparelho → escanear)

# 2) enviar teste (depois de conectar um número)
php artisan nextgest:whatsapp-teste {tenant} {numero} [--mensagem="..."]
```
> O QR expira rápido — gere um novo com `whatsapp-conectar` logo antes de escanear.

## Validação
- 8 testes (`WhatsAppFatia1Test`, Evolution mockada) — suíte verde (575/575).
- Ponta a ponta real **até o QR**: `nextgest:whatsapp-conectar barbeariateste` criou `ng_barbeariateste`
  e gerou o QR (config persistida, token cifrado). A **entrega da mensagem** é validada conectando um
  número de teste e rodando o `whatsapp-teste`.

## Próximas fatias
- **Fatia 2:** tela de QR no painel (gated `whatsapp`) + monitorar sessão (queda → avisar).
- **Fatia 3:** lembrete antes do horário (job agendado, opt-in, idempotente, fuso correto).
- **Depois:** Evolution em produção (exposição/segurança blindada à parte).
