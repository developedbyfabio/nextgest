---
projeto: Nextgest
tipo: operação-deploy
status: vivo
criado: 2026-06-24
tags: [nextgest, deploy, produção, infraestrutura, ssl, tenancy]
---

# Deploy de Produção — Fase 1 (D48)

Sistema no ar em **https://nextgest.com.br** a partir do código do GitHub (`main`), com stack
completa de produção. Ver decisão resumida em [[Decisões de Arquitetura]] (D48). **Sem segredos
neste documento** — credenciais vivem só no servidor (`.env` e cofres `/root/*.cred`, modo 600).

## Servidor

- **KVM**, Ubuntu **24.04.4 LTS** (noble), kernel 6.8, x86_64. Hostname `srv1780620`.
- IP público `187.127.24.165` (+ IPv6). RAM 8 GB, disco 96 GB.
- Já vinha com: Node 22 (NodeSource), `monarx-agent` (security scanner).

## Stack e versões

| Componente | Versão | Origem |
|---|---|---|
| PHP-FPM | 8.5.7 | PPA `ondrej/php` (Ubuntu só tem 8.3) |
| Nginx | 1.24.0 | repo Ubuntu |
| MySQL | 8.0.46 | repo Ubuntu |
| Redis | 7.0.15 | repo Ubuntu |
| Composer | 2.10.x | instalador oficial (hash conferido) |
| Node / npm | 22 / 10 | pré-existente |
| Certbot | 2.9 + dns-cloudflare | repo Ubuntu |

Extensões PHP: cli, fpm, mysql, mbstring, xml, curl, zip, gd, bcmath, intl, redis.

## Caminhos

- **App**: `/srv/www/nextgest` (clone SSH do `main`). Dono `root:www-data`; `storage/` e
  `bootstrap/cache/` graváveis por `www-data` (775). Sem 777.
- **.env**: `/srv/www/nextgest/.env` (modo 640, `root:www-data`). Gerado no servidor.
- **Nginx vhost**: `/etc/nginx/sites-available/nextgest` (curinga `nextgest.com.br` +
  `*.nextgest.com.br`, root `public/`, FPM via socket `php8.5-fpm.sock`).
- **Snippet real-IP Cloudflare**: `/etc/nginx/snippets/cloudflare-realip.conf`.
- **Supervisor**: `/etc/supervisor/conf.d/nextgest-worker.conf`.
- **Cron**: `/etc/cron.d/nextgest` (`schedule:run` por minuto, usuário `www-data`).
- **SSL**: `/etc/letsencrypt/live/nextgest.com.br/`; hook de reload em
  `/etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh`.
- **Cofres (só-root, 600)**: `/root/nextgest-db.cred`, `/root/nextgest-admin.cred`,
  `/root/nextgest-dono-teste.cred`, `/root/.secrets/cloudflare.ini`.

## Serviços (ativos + habilitados no boot)

`nginx`, `php8.5-fpm`, `mysql`, `redis-server`, `supervisor` (worker), `cron`, `certbot.timer`.

## Banco de dados (DB-per-tenant)

- **Central**: `nextgest_central` (utf8mb4). Usuário dedicado **`nextgest`** (não usa root).
- Grants: `ALL ON nextgest_central.*` + ``ALL ON `tenant\_%`.*`` (cria/migra bancos de tenant).
- **Tenants**: um banco `tenant_{slug}` por estabelecimento (prefixo `tenant_` em `config/tenancy.php`).
- Tuning RAM (8 GB) em `/etc/mysql/mysql.conf.d/zz-nextgest.cnf`:
  `innodb_buffer_pool_size=1536M`, `max_connections=100`, `flush_log_at_trx_commit=1`,
  `innodb_redo_log_capacity=256M`, `open_files_limit=65535` (via override systemd `LimitNOFILE`).
- **Swap**: `/swapfile` 2 GB, `vm.swappiness=10` (colchão anti-OOM; não há swap nativo).

## Redis

- `maxmemory 512mb`, `maxmemory-policy volatile-lru` — cache/sessão (com TTL) podem ser despejados;
  **jobs de fila (sem TTL) ficam protegidos** de despejo.

## Aplicação

- `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://nextgest.com.br`,
  `APP_KEY` estável (gerada no servidor — **nunca trocar**, credenciais cifradas de tenant dependem dela).
- **Cache/Sessão/Fila = Redis** (seguro: bootstrappers `CacheTenancyBootstrapper` e
  `QueueTenancyBootstrapper` isolam por tenant; `RedisTenancyBootstrapper` permanece comentado).
- Caches de produção gerados: `config`, `route`, `view`, `event`.
- **Worker**: supervisor, 2 processos `queue:work redis`, `www-data`, autorestart,
  `stopwaitsecs=3600`.
- **PHP-FPM pool**: `pm=dynamic`, `max_children=20`, `start=4`, `min/max spare=2/6`.

## Multi-tenancy (path-based)

- Tenant identificado por **caminho**: `nextgest.com.br/{slug}` (`InitializeTenancyByPath`).
- `central_domains` inclui `nextgest.com.br` / `www.`. Pipeline em `TenancyServiceProvider`
  (síncrono, `shouldBeQueued(false)`): **CreateDatabase → MigrateDatabase → SeedDatabase**
  (papéis/permissões via `TenantDatabaseSeeder`).
- Criar tenant: `App\Models\Tenant::create(['id'=>slug,'nome'=>...,'slug'=>...,'ativo'=>true])`.
  Criar Dono: comando `nextgest:criar-dono {slug}`.

## SSL / Cloudflare

- Certificado **wildcard** `nextgest.com.br` + `*.nextgest.com.br` via **DNS-01** (plugin
  `dns-cloudflare`, token só no servidor). Renovação automática (testada com `--dry-run`) + hook
  que recarrega o Nginx.
- Nginx: 443 + redirect 80→443, **HSTS** (`includeSubDomains`), headers de segurança.
- **HTTPS atrás do Cloudflare**: `fastcgi_param HTTPS on` + **TrustProxies escopado às faixas do
  Cloudflare** (em `bootstrap/app.php`) + snippet real-IP (`CF-Connecting-IP`).
- Cloudflare: **Proxied (laranja)** ligado + SSL/TLS **Full (strict)**.

## Estado inicial (produção limpa)

- Super-admin único (tabela central `admins`), criado por `nextgest:criar-admin`. Login:
  `fabio9384@gmail.com`. **Trocar a senha no 1º acesso** (a tabela `admins` não tem flag de
  troca forçada — só `users` de tenant têm `deve_trocar_senha`).
- **Sem tenants demo / sem seeders de volume.** Único tenant é o **`teste`** (Salão Teste),
  mantido para validação.

## Gotchas do deploy (aprendizados)

- **PHP 8.5 exige PPA `ondrej/php`** no Ubuntu 24.04 (repo padrão só tem 8.3). `composer.json`
  pede `^8.3`, então 8.5 satisfaz.
- **Nginx 1.24 não aceita `http2 on;`** (sintaxe nova do 1.25.1+): usar `listen 443 ssl http2;`.
- **`failed_jobs` não existia** (driver `database-uuids`): criada migration canônica
  `…create_failed_jobs_table` — sem ela, falha de job na fila redis quebrava ao registrar.
- **`open_files_limit` do MySQL** é limitado pelo `LimitNOFILE` do systemd → precisa de override.
- **Permissões de deploy** mexem no bit de executável (git `core.fileMode`): no clone de produção
  ele foi desligado para o `git status` não acusar mudança de modo em massa.
- Log: `TenantCouldNotBeIdentified` (caminho/tenant inexistente, incluindo bots sondando
  `/wordpress`) é logado como ERROR mas vira **404 amigável** — ruído esperado, não é falha.

## Pendências / próximas fases (fora do escopo da Fase 1)

- E-mail transacional real (hoje `MAIL_MAILER=log`).
- Hardening opcional: restringir `:80/:443` da origem às faixas do Cloudflare (evita bypass/spoof).
- Fase 2: Gateway/Checkout Pro + webhook (agora viável com HTTPS público). Fase 3: clube recorrente.
  Fase 4: WhatsApp.
