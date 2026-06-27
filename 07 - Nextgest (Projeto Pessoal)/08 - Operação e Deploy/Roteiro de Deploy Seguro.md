# Roteiro de Deploy Seguro — Nextgest

> Como publicar uma atualização do **dev** (`192.168.11.210`) em **produção**
> (`187.127.24.165` / `https://nextgest.com.br`). A **fonte da verdade é o `main` do GitHub**:
> o dev faz push, a produção faz pull. **Nunca editar código direto na produção.**

## Princípios (por que cada passo existe)

- **Git é a fonte única.** Dev → push → `main`. Produção → pull do `main`. Os dois sempre no mesmo
  commit.
- **Backup antes de mexer.** Produção tem dados reais (centrais + de tenants). Backup do banco antes
  de qualquer migration. **Sem backup, não há deploy.**
- **Ordem importa:** pull → dependências → **build** → migrate → **cache**. Pular o build = tela
  nova não aparece. Pular o cache = config nova não pega.
- **Migration em produção é sagrada:** **aditiva**, nunca `migrate:fresh`/`wipe`/`rollback` cego.
  Conferir o que vai rodar **antes** (`migrate:status`).
- **Janela de risco:** prefira publicar em horário de baixo movimento. Mudança que mexe em fluxo de
  agendamento/comanda merece mais cuidado que tela.

---

## ANTES de publicar (no DEV)

1. Tudo commitado e no `main`: `git status` limpo; `git push`.
2. Suíte **verde sequencial** no dev: `php artisan test` (≥ baseline atual). `--parallel` dá
   falso-vermelho (SQLite) — sempre sequencial.
3. Se houver **migration nova**, saber o que ela faz e confirmar que é **aditiva** (não dropa
   coluna/tabela com dado).

---

## DEPLOY (na PRODUÇÃO) — passo a passo

> Rodar como o usuário do deploy, em `/srv/www/nextgest`. Acompanhar **cada passo** (não rodar tudo
> de enfiada). Em erro: **parar**, não forçar.

### 1. Backup do banco (OBRIGATÓRIO, antes de tudo)

- Dump do banco **central** e dos bancos de **tenant** (DB-per-tenant) para um diretório de backup
  com data/hora. (Há `mysqldump`; os tenants seguem o padrão `tenant_{slug}`.)
- Confirmar que o arquivo de backup existe e tem tamanho > 0 **antes** de prosseguir. Sem backup
  válido → **parar**.

### 2. (Opcional) Modo manutenção

- Para mudanças maiores (migration pesada, refactor), considerar `php artisan down`
  (`--secret=...` pra você acessar durante). Para uma mudança só de tela, geralmente dispensável.
- Se ligar, lembrar de `php artisan up` no fim.

### 3. Puxar o código

- `git status` (produção deve estar limpa — **se houver edição local na produção, PARAR e
  reportar**: isso não deveria existir; o código vem do Git).
- `git pull --no-rebase origin main`. Conferir o commit novo. Conflito → **parar e reportar**.

### 4. Dependências

- `composer install --no-dev --optimize-autoloader` (só se o `composer.lock` mudou — mas rodar é
  seguro).
- `npm ci` (se `package-lock.json` mudou).

### 5. Build dos assets (NÃO pular)

- `npm run build` (Vite, **foreground**). Sem isto, CSS/JS novos **não aparecem** — a tela continua
  velha. É o erro mais comum de deploy.

### 6. Migrations (com cuidado)

- `php artisan migrate:status` — **ver o que está pendente antes de aplicar.** Confirmar que é
  aditivo e esperado.
- **Central:** `php artisan migrate --force`.
- **Tenants (se a migration nova for de tenant):** `php artisan tenants:migrate` (roda em todos os
  bancos de tenant). Conferir se a mudança é de tenant ou central antes.
- **NUNCA** `migrate:fresh`, `migrate:reset`, `db:wipe`, nem `rollback` cego em produção.

### 7. Limpar e regerar cache (NÃO pular)

- `php artisan config:cache`
- `php artisan route:cache`
- `php artisan view:cache`
- `php artisan event:cache` (se aplicável)
- (Se usar cache de aplicação que possa ficar stale: `php artisan cache:clear`.)

### 8. Reiniciar o worker da fila

- O `queue:work` (supervisor) roda **código em memória** — ele **não** pega o código novo sozinho.
  Reiniciar: `php artisan queue:restart` (faz os workers reiniciarem ao terminar o job atual) — ou
  `supervisorctl restart` do programa. Sem isto, a fila roda código velho.

### 9. Sair do modo manutenção (se ligou)

- `php artisan up`.

---

## DEPOIS (validar — não pular)

1. Abrir `https://nextgest.com.br` (aba anônima) → carrega, **cadeado OK**, sem erro.
2. Acessar um **tenant** (`https://nextgest.com.br/{slug}/painel`) → sobe, assets novos aparecem
   (conferir que o build pegou).
3. Se a mudança era específica (ex.: tela nova, regra nova) → **testar essa mudança** de fato.
4. `tail` no `storage/logs/laravel.log` → sem erro novo. (Bots sondando `/wordpress` etc. geram
   404/`TenantCouldNotBeIdentified` — ruído conhecido, ignorar.)
5. Worker processando (`supervisorctl status`) e cron ativo.

---

## SE DER ERRADO (rollback)

- **App quebrou após o pull** (sem migration nova): voltar ao commit anterior
  (`git checkout <commit_anterior>` no `/srv/www/nextgest`), refazer passos 4-8, validar. **Não**
  usar `reset --hard` (destrutivo); checkout do commit é suficiente para servir a versão anterior.
- **Migration quebrou / dado inconsistente:** é por isso que o **passo 1 (backup)** existe.
  Restaurar o banco do dump da etapa 1. **Parar** e investigar a migration no dev antes de tentar de
  novo. **Nunca** improvisar `migrate:rollback` em produção sem entender o impacto.
- **"too many redirects" / SSL:** checar o modo do Cloudflare (**Full strict**) e o TrustProxies —
  não é deploy de código, é config de borda.
- Em qualquer caso: **preservar o backup**, anotar o erro real (saída do comando), e tratar no dev.

---

## Cenário: migration central acumulada + provisionar assinaturas + ativação de gateway

Quando a leva acumula **várias migrations centrais** (ex.: D54–D62: `estabelecimentos`,
`assinaturas`, `faturas`, colunas MP, `webhook_eventos`) e sobe cobrança que **mexe no login real**.

### Migration central acumulada (checkpoint duro)
- Depois de `pull → composer → build`, rodar **`php artisan migrate:status`** e **PARAR**: listar as
  pendentes, confirmar que são **todas centrais e aditivas** (`Schema::create`/`Schema::table` add
  coluna; `cascadeOnDelete` em FK **não** é destrutivo) e que **nenhuma é de tenant**.
- Só rodar **`php artisan migrate --force`** após o OK. **Nunca** `tenants:migrate` se a leva não tem
  migration de tenant; **nunca** `fresh/reset`.
- Conferir o resultado com `SHOW CREATE TABLE` (uniques/FKs) nas tabelas novas.

### Provisionar assinaturas (idempotente)
- `php artisan nextgest:provisionar-assinaturas` (**dry-run**) → **PARAR** e reportar quem será
  provisionado → após OK, `--apply`. Cria 1 assinatura por tenant sem assinatura; **idempotente**
  (rodar de novo não recria). Tenant em trial nasce **`em_teste`** → **não bloqueia** o dono.
- Enforcement é **ao vivo** via `Assinatura::situacaoAcesso()` (sem cron); o middleware
  `GarantirAssinaturaAtiva` só barra `suspensa`/`cancelada`. **Validar sempre** que o dono real
  redireciona para o **login** (não para a tela de suspensão) e que o **portal do cliente** segue 200.

### Ativação de gateway (Mercado Pago) — passo SEPARADO, opcional
- O app sobe **inerte** sem as vars do MP (não quebra no boot) — deployar sem elas é seguro.
- Para ligar: **o Fabio** põe `MERCADOPAGO_ACCESS_TOKEN` (produção, não `TEST-`) e
  `MERCADOPAGO_WEBHOOK_SECRET` no `.env` (**nunca no chat/log/relatório**) → `php artisan
  config:cache` → cadastrar a **URL do webhook** `https://nextgest.com.br/webhooks/pagamentos/mercadopago`
  no painel MP.
- Validar: "simular notificação" do MP chega/valida/processa; **POST sem assinatura é rejeitado
  (401/403)**; então 1ª adesão real (cartão) → `preapproval` vai a `authorized` → 1ª cobrança vira
  fatura paga pelo webhook.

---

## Cenário: deploy só-código (sem migration)

Incremento backend/testes/doc, sem mudança de schema (ex.: D64 — logs do webhook + guard de testes).
Baixo risco, mas os trilhos continuam:
- **Backup central mesmo assim** (registra o caminho do dump) — barato e é a regra.
- **`migrate:status` deve mostrar ZERO pendente.** Se aparecer qualquer migration pendente, **PARAR e
  reportar** — é inesperado num deploy só-código. **Não** rodar `migrate`.
- **Build é opcional** (rodar é inofensivo) se o frontend não mudou — confirmar no diff que não há
  `resources/`/assets antes de pular.
- Seguir mesmo assim: `pull → composer → cache → queue:restart → validar` (matriz de regressão
  completa: site, admin, **dono real loga**, **portal no ar**, endpoint sensível de pé, log limpo).
- **Logs novos não podem vazar segredo** — revisar no diff que só logam fatos (ids/tipos/status),
  nunca token/secret/assinatura/corpo. Lembrar que `LOG_LEVEL=warning` em produção **filtra os
  `Log::info`** (baixar para `info` + `config:cache` só quando precisar de observabilidade fina).

---

## Checklist rápido (cola mental)

`push (dev)` → `backup (prod)` → `pull` → `composer/npm` → **`build`** → `migrate:status` →
`migrate --force` (+ `tenants:migrate` se for de tenant) → **cache** → `queue:restart` → **validar**
→ (rollback = checkout commit anterior + restaurar dump).

---

## Evolução futura (quando virar rotina)

Depois de rodar este roteiro algumas vezes com confiança, dá para encapsular os passos 3-8 num
script `deploy.sh` (com o backup do passo 1 embutido e `set -e` para abortar em erro). **Não fazer
agora** — script só depois que o processo for rotina e você reconhecer cada etapa, para não
automatizar um erro. Quando quiser, peça o `deploy.sh` baseado neste roteiro.

---

*(Fim do conteúdo da nota.)*
