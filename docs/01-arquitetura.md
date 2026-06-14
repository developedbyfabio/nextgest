# 01 — Arquitetura e operação

## Decisões macro (resumo)

| # | Decisão |
|---|---|
| D01 | Multi-tenancy: **um banco por tenant** (`stancl/tenancy`). Isolamento físico. |
| D02 | Identificação **por caminho**: `nextgest.com.br/{slug}`. Um domínio, um SSL. |
| D03 | Guards separados: `web` (equipe), `cliente` (cliente final), `admin` (super-admin central). |
| D04 | RBAC flexível (`spatie/laravel-permission`). `ver_financeiro` só Dono por padrão. |
| D05 | Multi-unidade por tenant. |

(Lista completa D01–D23 no documento de modelagem do projeto.)

## Camadas de banco

- **Central** (`nextgest_central`): `tenants` (id=slug, nome, slug, ativo),
  `domains` (domínio próprio futuro), `admins` (super-admins do SaaS).
- **Tenant** (`tenant_{slug}`): todo o operacional (Apêndices/Blocos 02–07) +
  tabelas do spatie (`roles`, `permissions`, ...).

## Identificação por caminho

- `App\Models\Tenant`: `id` = slug usado na URL; `$incrementing = false`,
  `keyType = string`; colunas reais via `getCustomColumns()`.
- `config/tenancy.php`: `id_generator => null` (id fornecido = slug),
  prefixo de banco `tenant_`, `central_domains` inclui `nextgest.com.br`.
- **Grupo de middleware `tenant`** (`bootstrap/app.php`): a ordem coloca
  `InitializeTenancyByPath` **antes** de `StartSession`, e
  `ScopeSessionToTenant` logo a seguir, para que a sessão já nasça escopada ao
  tenant. Por isso as rotas de tenant usam o grupo `tenant` (não o `web`).
- **Sessão escopada** (`App\Http\Middleware\ScopeSessionToTenant`): cada tenant
  recebe um cookie de sessão próprio (`nextgest_tenant_{id}_session`) e `path`
  restrito a `/{id}` — login de um estabelecimento não vaza para outro.

## Rotas

- **Centrais** (`routes/web.php`): registradas primeiro, têm precedência sobre o
  catch-all `/{tenant}`. Hoje: `/` (landing), `/admin` (super-admin),
  `/webhooks/pagamentos/{gateway}`.
- **Tenant** (`routes/tenant.php`): `prefix('{tenant}')` + grupo `tenant`. O
  parâmetro `{tenant}` tem regex que **exclui slugs reservados**
  (`config/nextgest.php`): admin, api, login, logout, register, webhooks,
  assets, storage, vendor, livewire, nextgest, app, painel, super-admin, up.

## Ciclo de criação de tenant

`App\Models\Tenant::create([...])` dispara o evento `TenantCreated`, cuja
pipeline (em `App\Providers\TenancyServiceProvider`) executa:

1. `CreateDatabase` — cria `tenant_{slug}`.
2. `MigrateDatabase` — roda `database/migrations/tenant`.
3. `SeedDatabase` — roda `Database\Seeders\TenantDatabaseSeeder` (papéis,
   permissões e `configuracao` inicial).

## Provisionamento do servidor (já feito)

- Pacotes: PHP 8.4 (+ extensões), Composer, Node 22, MySQL 8, Nginx, Redis,
  Supervisor, ufw.
- MySQL: senha de root gerada em `/root/.nextgest_db` (600); usuário de app
  `nextgest_app` com grant no central e em `tenant\_%`.
- `ufw`: OpenSSH, 80, 443 liberados.
- Serviços habilitados no boot.

## Filas e cache

- `CACHE_STORE`, `QUEUE_CONNECTION`, `SESSION_DRIVER` em **Redis**.
- A pipeline de criação de tenant roda **síncrona** por ora
  (`shouldBeQueued(false)`). Para produção com muitos tenants, considerar fila +
  worker Supervisor.

## Pendências de infraestrutura

- DNS de `nextgest.com.br` apontando para o servidor.
- SSL (certbot) após o DNS propagar.
- Server block de produção do Nginx (deixar pronto; só ativar com domínio
  resolvendo).
