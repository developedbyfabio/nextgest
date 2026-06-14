# Nextgest

SaaS de agendamento multi-tenant para negócios que atendem por horário marcado
(barbearias, salões, autônomos). Cada estabelecimento é um **tenant** isolado,
acessado por `nextgest.com.br/{slug}`.

## Stack

- Ubuntu 24.04 LTS, Nginx, PHP 8.4 (FPM), MySQL 8, Redis 7
- Laravel 13, Livewire 4 + Alpine.js, Tailwind v4 + Vite, Node 22
- Multi-tenancy: `stancl/tenancy` (um banco por tenant, identificação por caminho)
- Permissões: `spatie/laravel-permission`
- Pagamentos: arquitetura plugável (adapter); primeiro provedor Mercado Pago (stub)

## Arquitetura em uma frase

Banco **central** (`nextgest_central`) guarda `tenants`, `domains` e `admins`
(super-admin). Cada estabelecimento tem o seu **banco de tenant** (`tenant_{slug}`)
com todo o operacional. Três guards: `admin` (central), `web` (equipe do tenant),
`cliente` (cliente final do portal). Ver [docs/01-arquitetura.md](docs/01-arquitetura.md).

## Como rodar (desenvolvimento)

Pré-requisitos já provisionados no servidor (ver `docs/01-arquitetura.md`).

```bash
cd /srv/www/nextgest

# Dependências
composer install
npm install

# Ambiente: .env já configurado (MySQL central + Redis). Para um novo ambiente:
#   cp .env.example .env && php artisan key:generate
# Credenciais do banco ficam em /root/.nextgest_db (fora do git).

# Migrations do banco CENTRAL (cria tenants, domains, admins)
php artisan migrate

# Build do front
npm run build      # produção
# npm run dev      # desenvolvimento (Vite)
```

### Criar um tenant

A criação dispara automaticamente: cria o banco `tenant_{slug}`, roda as
migrations de tenant e semeia papéis/permissões + `configuracao` inicial.

```bash
php artisan tinker
>>> $t = App\Models\Tenant::create(['id' => 'barbeariadojorge', 'nome' => 'Barbearia do Jorge', 'slug' => 'barbeariadojorge', 'ativo' => true]);
```

Acesso do tenant: `https://nextgest.com.br/barbeariadojorge`.

### Migrations de tenant (todos os tenants)

```bash
php artisan tenants:migrate            # roda migrations pendentes em todos os tenants
php artisan tenants:migrate --tenants=barbeariadojorge
```

> NUNCA usar `migrate:fresh`, `migrate:reset`, `db:wipe` ou rollback em
> produção. Operações destrutivas exigem revisão humana.

## Estrutura de rotas

- **Central** (`routes/web.php`): `/` (landing), `/admin/login` + `/admin`
  (super-admin, guard `admin`), `/webhooks/...`. Não passam pela resolução de
  tenant.
- **Tenant** (`routes/tenant.php`): tudo sob `/{tenant}`, com o grupo de
  middleware `tenant` (identificação por caminho + sessão escopada). Portal do
  cliente em `/{tenant}` (guard `cliente`) e painel da equipe em
  `/{tenant}/painel` (guard `web`). Slugs reservados em `config/nextgest.php`.

Detalhes de autenticação, comandos e telas: [docs/08-autenticacao.md](docs/08-autenticacao.md).

## Acesso (bootstrap)

```bash
php artisan nextgest:criar-admin                 # super-admin central (/admin)
php artisan nextgest:criar-dono barbeariateste   # Dono no tenant (painel)
```
A senha é definida no prompt (nunca em código/git).

## Documentação por módulo

| Bloco | Arquivo |
|---|---|
| Arquitetura e operação | [docs/01-arquitetura.md](docs/01-arquitetura.md) |
| Agendamento | [docs/02-agendamento.md](docs/02-agendamento.md) |
| Produtos e Vendas | [docs/03-produtos-vendas.md](docs/03-produtos-vendas.md) |
| Clube de Assinatura | [docs/04-clube.md](docs/04-clube.md) |
| Pagamentos | [docs/05-pagamentos.md](docs/05-pagamentos.md) |
| Kanban | [docs/06-kanban.md](docs/06-kanban.md) |
| WhatsApp | [docs/07-whatsapp.md](docs/07-whatsapp.md) |
| Autenticação + layout (1A) | [docs/08-autenticacao.md](docs/08-autenticacao.md) |
| Cadastros do dono (1B) | [docs/09-cadastros.md](docs/09-cadastros.md) |
| Portal de agendamento (1C) | [docs/10-agendamento.md](docs/10-agendamento.md) |
| Agenda da equipe (1D) | [docs/11-agenda-equipe.md](docs/11-agenda-equipe.md) |
| Design System (1E) | [docs/12-design-system.md](docs/12-design-system.md) |

## Segurança (resumo)

- `.env` permissão 600, fora do git. Credenciais do banco em `/root/.nextgest_db` (600).
- Pagamentos: nunca armazenar dados de cartão (só token); credenciais de gateway
  com cast `encrypted`; confirmação por webhook.
- PHP-FPM e Nginx rodam como `www-data` (app não roda como root).
