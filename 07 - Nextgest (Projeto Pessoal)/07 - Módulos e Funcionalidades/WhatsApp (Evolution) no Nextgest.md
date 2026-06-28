---
projeto: Nextgest
tipo: módulo
status: Fatia 1 validada ponta a ponta (envio de teste manual) — dev
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
- Ponta a ponta real **validado (28/06/2026)**: `ng_barbeariateste` conectada (state `open`),
  `whatsapp-teste` enviou e a mensagem **chegou** no destinatário. Sessão persiste ao recriar o
  container (creds no volume → reconecta sozinha).
- **Gotcha (WhatsApp):** "Não é possível conectar novos dispositivos no momento / tente mais tarde" =
  throttle do WhatsApp no número após vários scans seguidos (não é bug da Evolution). Esperar uns
  minutos e tentar **uma** vez; manter o app do WhatsApp atualizado.

## Fatia 2 (D76) — item no menu + tela de conexão
- **Item próprio "WhatsApp"** (grupo Gestão), gated por `@recurso('whatsapp')` +
  `@can('gerenciar_whatsapp')`. Saiu de *Integrações* (o editor antigo da API Cloud da Meta foi
  **aposentado**; o hub de Integrações ficou só com Pagamento).
- **Permissão:** reusa a já existente **`gerenciar_whatsapp`** (Dono + Gerente). Sem permissão nova,
  sem backfill.
- **Tela** `App\Livewire\Painel\Whatsapp\Conexao` (rota `painel.whatsapp`): estados **desconectado /
  aguardando / conectado / caiu / erro**. `wire:init=sincronizar` (estado real no load), `wire:poll.3s`
  enquanto aguarda (para ao conectar), `wire:poll.20s` enquanto conectado (detecta queda → caiu), QR
  renovável, desconectar/reconectar. Reusa `WhatsAppService` (D75) + `desconectar()` (logout da
  instância). Erros tratados, sem expor segredo. 9 testes (`WhatsAppConexaoTest`).

## Fatia 3 (D77) — configuração de automações
- **Dados:** JSON `whatsapp_config.automacoes` (`{chave: {ativo, template}}`, overrides por tenant). O
  **catálogo** (categoria, variáveis, template padrão) está no enum `App\Enums\AutomacaoWhatsapp`.
- **Catálogo:** transacionais (`lembrete_servico`, `cobranca_clube`, `avaliacao_pos_servico`) e
  broadcast (`noticias`, `funcionamento`, `avisos_gerais`). Broadcast é **sensível** (massa → risco de
  ban; opt-in/LGPD): **off por padrão** + aviso na tela.
- **Template:** `RenderizadorTemplate` troca `{var}` conhecidas; `{xpto}` desconhecida fica **literal**
  (nunca quebra); sem injeção (só `str_replace`, valores sem caracteres de controle).
- **Tela** `Painel\Whatsapp\Automacoes` (rota `painel.whatsapp.automacoes`, aba ao lado de Conexão):
  toggle + editor + chips de variáveis por automação; "número para teste" + botão **Testar** que
  renderiza com **dados de exemplo** e envia via D75. **NADA dispara automaticamente** (sem job/gatilho).
- 10 testes (`WhatsAppAutomacoesTest`).

## Fatia 4 (D79) — lembrete de serviço (1ª automação real, anti-ban)
- **Comando** `nextgest:enviar-lembretes` (scheduler, a cada minuto) lê a agenda — janela
  `(now, now+antecedência]`, status a-atender, fuso `APP_TIMEZONE` — **sem tocar o motor**. Enfileira
  o job `EnviarLembreteWhatsApp` por agendamento elegível.
- **Idempotente:** `lembretes_servico.agendamento_id` único (1 por agendamento; re-run não duplica).
- **Anti-ban (config `whatsapp.lembretes`):** teto por minuto (4) e por dia (150); espaçamento via
  `delay()` (fila assíncrona); WhatsApp caído → não enfileira (não acumula). Tabela `jobs` central.
- **Job** (`tries=1`): revalida (status/futuro/opt-out/automação), renderiza o template (D77) com
  dados reais e envia via D75; marca enviado/falhou.
- **Opt-out:** `clientes.whatsapp_optout` (respeitado). **Antecedência** editável no card do lembrete.
- **Produção:** `QUEUE_CONNECTION=database` + worker (em dev a fila é `sync` → envia na hora).
- 10 testes (`LembreteServicoTest`).

## Próximas fatias
- **Controle de mensagens:** histórico/log de envios + janela de horário permitido + gestão de opt-out.
- **Fatia 5:** avaliação pós-serviço (liga na avaliação D51, respeitando anonimato).
- **Depois:** cobrança do clube (G2/G3 do gateway), contatos, caixa de conversas, broadcast real.
- **Evolution em produção** (exposição/segurança blindada à parte).
