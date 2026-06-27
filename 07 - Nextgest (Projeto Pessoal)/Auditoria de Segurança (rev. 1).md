---
projeto: Nextgest
tipo: auditoria
escopo: revisão de código (só leitura) por superfície de risco
data: 2026-06-27
ambiente: dev
status: rev. 1 (sem correções — só achados)
tags: [nextgest, seguranca, auditoria, multitenancy, pagamentos]
---

# Auditoria de Segurança (rev. 1)

> **Só leitura.** Nada foi alterado (`git status` limpo). Achados por severidade, cada um com onde
> está, como seria explorado e a correção sugerida. **Sem Dxx** — as correções, se houver, virão em
> fatias próprias. Sem segredos reais nesta nota. Enquadramento honesto: "impenetrável" não existe;
> abaixo está o que **resistiu** à auditoria adversarial e o que **não fechou**.

## Veredito resumido
As duas superfícies CRÍTICAS — **isolamento multi-tenant** e **webhook do Mercado Pago** — estão
**sólidas**. Anonimato de avaliações, RBAC/IDOR e segredos também resistiram. **Nenhum achado
Crítico ou Alto confirmado.** Os achados são **1 Médio de enforcement** (suspensão não revalidada nas
ações Livewire — **já corrigido, D71**), itens **Baixos** de hardening e um **checklist de produção**
(APP_DEBUG).

> **Atualização (D71):** o achado **M1 foi corrigido**. Os demais seguem em aberto para o Fabio
> priorizar (M2 checklist de produção; B1–B5 hardening).

---

## Achados por severidade

### MÉDIO

**M1 — Suspensão por pagamento não é revalidada nas ações Livewire** — ✅ **CORRIGIDO (D71)**
> `GarantirAssinaturaAtiva` virou **persistent middleware do Livewire** (auto-escopado ao painel; portal
> intacto). Reproduzido e verificado fim-a-fim (Playwright): suspenso bloqueia a ação e redireciona;
> dono ativo age normal; portal 200. +2 testes em `SuspensaoTest`. Ver [[Decisões de Arquitetura]] (D71).
- **Onde:** `app/Providers/AppServiceProvider.php` (persistent middleware do tenancy = **só**
  `InitializeTenancyByPath`); `GarantirAssinaturaAtiva` só está no grupo de rotas do painel
  (`routes/tenant.php`), aplicado no **GET** de página.
- **Risco:** o endpoint central `POST /livewire-c7530b77/update` reexecuta **apenas** a inicialização
  do tenancy — **não** o `GarantirAssinaturaAtiva`. Um tenant suspenso é barrado ao **carregar** uma
  página (redirect p/ a tela de suspensão), mas uma **aba já aberta antes da suspensão** mantém um
  snapshot Livewire válido e **continua executando ações** (registrar venda, agendamento, etc.) sem
  novo bloqueio. Como a suspensão é "ao vivo" (sem cron), o efeito é: assim que a fatura vence o prazo,
  o próximo GET bloqueia, mas a aba aberta segue operando até navegar/atualizar.
- **Como explora:** dono com o painel aberto deixa de pagar; segue usando o sistema pela aba viva.
- **Impacto:** fura o **lever de cobrança** (não é vazamento nem cross-tenant). Por isso Médio, não
  Alto.
- **Correção sugerida:** revalidar a assinatura também nas requisições Livewire — p.ex. um
  `Livewire::componentHook`/middleware no endpoint de update, ou um trait/base-component que chame a
  checagem de `situacaoAcesso()` em ações sensíveis, ou incluir o enforcement num ponto que rode no
  update. **A confirmar com um teste prático** (montar componente, suspender, disparar ação).

**M2 — `APP_DEBUG=true` / `APP_ENV=local` (checklist de produção)**
- **Onde:** ambiente atual (dev) — esperado aqui. Vira risco **se for para produção assim**.
- **Risco:** com `APP_DEBUG=true`, páginas de erro expõem stack trace, trechos de código, query e
  variáveis de ambiente → vazamento de estrutura/segredos.
- **Correção sugerida:** garantir no deploy: `APP_ENV=production`, `APP_DEBUG=false`, e `php artisan
  config:cache` no servidor de produção (e validar que a tela 500 é genérica). Item de checklist, não
  bug de código.

### BAIXO

**B1 — Gating de recurso (plano) também não revalidado nas ações Livewire** — mesmo mecanismo do M1
(`VerificaRecurso` é middleware de rota). Difícil de explorar: obter snapshot de um componente gated
exige ter **carregado a página** dele (que dá 404 com o recurso off). Só explorável se o recurso for
desligado **depois** da página carregada. Correção: mesma direção do M1.

**B2 — Webhook MP sem checagem de frescor do `ts`** — `ValidadorWebhook` valida o HMAC mas não rejeita
assinaturas antigas (janela de replay). Impacto baixo: o processamento é **idempotente** e **consulta
a API** (corpo forjado não vale). Correção: rejeitar `ts` fora de uma janela (ex.: ±5 min).

**B3 — Token de impersonação trafega na URL** — `…/suporte/{token}` (stancl UserImpersonation). Pode
cair em access log/histórico do navegador. Mitigado por **uso único + TTL ~60s** e geração só sob
`auth:admin`. O log do app **não** grava o token (verificado). Correção opcional: TTL curto explícito
e/ou token via POST.

**B4 — Webhook público sem rate limit** — `POST /webhooks/pagamentos/{gateway}` (grupo `web`, sem
throttle). DoS de baixo impacto: assinatura inválida é rejeitada barato (1 HMAC), sem tocar a API.
Correção: `throttle` por IP no endpoint.

**B5 — Login: throttle por (e-mail + IP)** — `AutenticaPorGuard` limita 5/min por e-mail+IP. Atacante
rotacionando IP/variando e-mail evade parcialmente (padrão aceitável). Correção opcional: throttle
adicional por IP puro.

### INFORMATIVO / defesa em profundidade (não é vulnerabilidade)
- **Rotas `produtos` e `integracoes` sem `can:` na rota** — porém o componente **autoriza no servidor**
  em `mount()` e em **todas** as ações (`Produtos\Index`, `Integracoes\*`). Sugestão: adicionar o gate
  na rota por consistência/defesa em profundidade.
- **Arquivos de tenant servidos sem auth** (`/{tenant}/arquivo/{path}`) — por design são públicos
  (logo/cabeçalho/fundo), nomes **hasheados**, anti-traversal por `realpath`+`str_starts_with`. As
  **fotos de perfil** caem no mesmo disco público → acessíveis por URL se ela vazar. Avaliar se é
  aceitável (privacidade baixa; sem enumeração).

---

## O que resistiu à auditoria adversarial (confirmado OK)

- **Isolamento multi-tenant (CRÍTICO):** banco **por tenant** (`tenant_{id}`) → IDOR/queries cruzadas
  são impossíveis por construção. `EscoparAutenticacaoPorTenant` desloga sessão de **outro** tenant
  (cookie compartilhado) e roda **antes** do `Authenticate` (correção VULN-001). `GarantirTenantAtivo`
  barra tenant inativo (VULN-002). `TenantArquivoController` bloqueia path traversal. O endpoint
  central de upload reinicializa a tenancy **antes** do throttle (sem vazar p/ o banco central).
- **Webhook Mercado Pago (CRÍTICO):** HMAC-SHA256 com `hash_equals` (timing-safe); **segredo só via
  `config()`** e **ausente → rejeita** (fail-safe); assinatura inválida → **401 sem processar**; o
  handler **consulta a API** do MP (não confia no corpo); **idempotência em 2 camadas**
  (`webhook_eventos` + `updateOrCreate` por competência). Forjar "pago" exige o segredo do webhook.
- **Anonimato das avaliações (ALTO):** forçado **no servidor** — `Avaliacoes\Index::escopo()` aplica
  `where profissional_id = auth id` quando não-`ver_avaliacoes`, e **só** faz eager-load/render de
  `cliente` quando `podeVerTudo`; filtros de cliente/profissional só existem para quem vê tudo e o
  servidor **ignora** `profissional_id` mandado por profissional (D67). Sem rota de export.
- **RBAC / IDOR (ALTO):** `can()`/`canAny()` nas rotas **e** `authorize()`/`abort_unless()` nos
  componentes (mount + ações). **Nenhum `hasRole`** no app. `VendaPolicy::gerir` fecha o IDOR de
  comanda (criar_venda OU profissional do próprio atendimento). Conclusão de atendimento só pelo
  "Finalizar" — `mudarStatus('concluido')` rejeitado (D70). Objetos resolvidos por `find` vivem no
  BD do tenant (sem cross-tenant) e as ações autorizam.
- **Segredos (ALTO):** nenhum literal no código; **sem `env()` fora de `config/`**; `.env` **não**
  versionado; logs **sem** token/segredo (inclui o webhook e a impersonação); `User` esconde
  `password`/`two_factor_*` e os fatores são `encrypted`.
- **SQL:** todos os `selectRaw/havingRaw/orderByRaw` usam **colunas fixas** + expressões
  **driver-aware** internas, com valores por **bind `?`** — sem interpolação de input → **sem SQLi**.
- **XSS:** os únicos `{!! !!}` renderizam `Aparencia::linkFonteGoogle()`, cuja família vem de um
  **catálogo fechado** (`Aparencia::FONTES`); valor fora do catálogo → `null` (link vazio). Sem input
  de usuário no HTML não-escapado.
- **Upload:** `['nullable','image','mimes:png,jpg,jpeg,webp','max:5120']` (sem SVG), gravado no **disco
  do tenant** — foto de perfil e imagens de aparência usam o mesmo caminho seguro.
- **Login:** throttle, mensagens genéricas (não revela se o e-mail existe), `session()->regenerate()`
  (anti-fixation), filtro `ativo` no attempt, 2FA opcional desfaz o login até o 2º fator.

---

## A investigar (não fechei 100%)
1. **M1 na prática:** escrever um teste que monte um componente do painel, suspenda a assinatura e
   dispare uma ação — confirmar se executa (esperado: hoje executa). É o que prioriza a correção.
2. **IDOR intra-tenant amplo:** amostrei Agenda/Vendas/Avaliações/Produtos/Integrações/Clube/Kanban
   (todos resolvem no BD do tenant + autorizam), mas **não** li 100% das ações de Kanban/Clube/Equipe.
   Varredura completa fica como tarefa.
3. **Manifesto do webhook por tipo de evento:** confirmar que o `data.id` (lowercase) bate com o que o
   MP assina em **todos** os tipos (`subscription_preapproval` usa `id` na query vs `data.id` no corpo).

## Recomendação de priorização (para o Fabio decidir)
1. **M1** (suspensão nas ações Livewire) — é o único gap de enforcement real; vira fatia com
   auditoria-primeiro + teste.
2. **M2** (checklist de produção: `APP_DEBUG=false`/`APP_ENV=production`/`config:cache`).
3. Baixos (B2 frescor do `ts`, B4 throttle do webhook) como hardening quando for ligar a cobrança real.
