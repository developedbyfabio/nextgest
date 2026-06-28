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

## Fatia 4.5 (D80) — número dedicado + termo de risco (trava) + detecção de queda
- **Número dedicado:** aviso na tela de Conexão (usar número secundário, não o principal).
- **Termo de risco (trava no servidor):** `whatsapp_config.termo_aceito_em/por/versao`. Sem aceite,
  `Automacoes::salvar()` **força tudo off** (não basta esconder o toggle); `aceitarTermo()` registra
  quem/quando/versão; bump de `config('whatsapp.termo_versao')` re-exige aceite.
- **Detecção de queda:** estado `caiu` na tela (D76) + **banner no topo do painel**
  (`Painel\AvisoWhatsappConexao`, `wire:init` → `status()`), condicional: recurso + permissão + já
  conectou + status ≠ open; Evolution fora → não alarma. Link "Reconectar".
- 8 testes (`TermoEAvisoTest`). Não dispara nada.

## Fatia 5 (D81) — avaliação pós-serviço (link assinado)
- **Comando** `nextgest:enviar-avaliacoes` (scheduler, a cada minuto): por tenant com
  `avaliacao_pos_servico` ligada + termo aceito (D80) + conectado, acha **concluídos** que terminaram
  há ~X min (`data_hora_fim ∈ (now-apos-buffer, now-apos]`), não avaliados/não pedidos, cliente não
  opt-out → enfileira o link. Reusa freios/idempotência do D79 (`pedidos_avaliacao`).
- **Link = URL ASSINADA** (`temporarySignedRoute('tenant.avaliar', …)`, middleware `signed`): página
  pública `Portal\AvaliacaoPublica` (sem login) que **reusa** a criação de `Avaliacao` (D51).
  Não-adivinhável, expira, sem dado pessoal na URL; não dá p/ avaliar o de outro. **Anonimato do D51
  intacto** (avaliação na web; o painel esconde o cliente do profissional).
- **NÃO recebe resposta** no WhatsApp (Fatia 8). `apos_min` editável no card (D77). 10 testes
  (`AvaliacaoPosServicoTest`).

## Modo Aquecimento (D82) — curva de volume p/ número novo
- **Teto efetivo do dia = `min(normal, curva do dia)`**, consumo **combinado** (lembrete + avaliação)
  — orçamento diário único por número. Serviço `Services\WhatsApp\Aquecimento`, consumido pelos
  comandos D79/D81. Dia 1 = `whatsapp_config.conectado_em` (capturado no `status()` ao conectar, fuso).
- **Troca de número reinicia** a curva (compara `ownerJid` via `/instance/fetchInstances`); mesmo
  número reconectado continua. **Broadcast** só a partir de `broadcast_a_partir_dia` (default 11).
- **Defaults conservadores** (config; override em `whatsapp_config.aquecimento`): 1–2 **10**, 3–6
  **20**, 7–13 **40**, 14–21 **80**, 22+ normal. **Tela "Aquecimento"** (3ª aba, gated, validada).
- 6 testes (`AquecimentoTest`). Não dispara nada novo.

## Controle de mensagens (D83) — histórico + janela de horário + opt-out
- **Log `mensagens_whatsapp`** (tenant): metadados (automação, cliente/telefone, status, quando) +
  **conteúdo**. Gravado pelos jobs D79/D81 e pelo "testar" manual, via `Services\WhatsApp\RegistroMensagem`
  (mascara links — o **link assinado** da avaliação não vira credencial viva no log). **Expurgo**
  automático do conteúdo (`nextgest:whatsapp-expurgar-conteudo`, diário; `historico.expurgo_dias`=**90**,
  mantém metadados).
- **Janela de horário** (`Services\WhatsApp\JanelaEnvio`): **global** (`config('whatsapp.janela')`
  08:00–20:00 → override em `whatsapp_config.janela`) + **override por automação**
  (`automacoes[chave].janela`). Decidida **no envio (job)**, fuso `APP_TIMEZONE`. Fora da janela:
  **lembrete** descarta se o atendimento já teria começado, senão **adia**; **avaliação** sempre
  **adia**. Represamento via `*.agendado_para` (sem mexer no enum); o comando **re-despacha** os
  vencidos antes dos novos (sem recursão em fila `sync`).
- **Telas (gated):** **Janela**, **Histórico** (filtros), **Opt-out** (marca/desmarca
  `clientes.whatsapp_optout`). Área WhatsApp agora com 6 abas.
- **Anonimato (D51):** histórico = ENVIO, nunca o resultado da avaliação (não cruza com `avaliacoes`;
  só Dono/Gerente). 13 testes (`ControleMensagensTest`). **Não recebe mensagem.**

## Melhorias de UI/UX (D84)
- **Confirmações nativas → modal D65** (`x-ng.confirmar`): Desconectar (Conexão) e "voltar a enviar"
  (Opt-out). Sem `confirm()` do navegador na área.
- **Erro de validação → toast + foco** no 1º campo inválido (trait `Concerns\FocaPrimeiroErro` +
  Alpine `@wa-erro-validacao.window`), em Automações/Janela/Aquecimento.
- **Indicador de aba ativa** (bug): deixou de depender de `request()->routeIs()` (sumia no
  `/livewire/update`); agora vem por literal no `@include('_abas', ['ativa'=>...])` — persiste em
  erro/re-render/`wire:navigate`; Conexão marcada ao abrir; abas com ícone+rótulo.
- **Número de teste persistente por tenant** (`whatsapp_config.numero_teste`). 5 testes
  (`MelhoriasUiTest`). Só UI — lógica intacta.

## Salvar por card (D85)
- Cada card da aba **Automações** tem o **próprio "Salvar"** (`salvarCard`), que grava só aquela
  automação (merge em `automacoes[chave]`, isolado), reusa termo (D80) + toast/foco (D84). Global
  mantido. **Correção:** o salvar global passou a fazer merge e **não apaga mais** a janela própria
  por automação (D83). Lógica de envio intacta.

## Consentimento de marketing (D86) — base do broadcast
- **Dois opt-outs independentes** no cliente: `whatsapp_optout` (geral/transacional, D83 — bloqueia
  tudo) e `whatsapp_marketing_optout` (novo — só broadcast). Sair do marketing **não** tira lembrete/
  avaliação (os comandos D79/D81 seguem olhando só o geral).
- `Cliente::aceitaMarketing()` (`!geral && !marketing`) + escopo `aceitamMarketing()` — prontos p/ a
  Fatia 2 (seleção em massa), **sem disparar** nada aqui.
- **Tela Opt-out** agora gerencia os **dois** consentimentos por cliente (colunas Tudo/Marketing),
  bloquear imediato e liberar com confirmação (D65). 6 testes (`MarketingOptoutTest`).

## Próximas fatias
- **Conversas tipo WhatsApp Web:** **recebimento** via webhook Evolution exposto (o maior; exige a
  decisão de exposição/produção da Evolution). Hoje **nada é recebido**.
- **Broadcast real:** sender de massa respeitando opt-in/LGPD + `broadcastLiberado()` do aquecimento.
- **Cobrança do clube** (G2/G3 do gateway) e **contatos**.
- **Evolution em produção** (exposição/segurança blindada à parte).
