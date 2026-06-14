# 08 — Autenticação e layout base (fatia 1A)

Login/logout dos três guards, UI com Flux, portal do cliente mobile-first.
Sem regras de agendamento ainda.

## Guards e áreas

| Área | Guard | Banco | Login | Pós-login |
|---|---|---|---|---|
| Super-admin (SaaS) | `admin` | central | `/admin/login` | `/admin` |
| Equipe do estabelecimento | `web` | tenant | `/{tenant}/painel/login` | `/{tenant}/painel` |
| Cliente final | `cliente` | tenant | `/{tenant}/login` | `/{tenant}` |

Isolamento de login: a sessão usa um cookie único, mas o login de um tenant não
vale em outro — `EscoparAutenticacaoPorTenant` encerra os guards de tenant ao
trocar de estabelecimento (ver seção "Livewire + multi-tenancy" abaixo).

## Rotas

Central (`routes/web.php`):
- `GET /` — landing (`landing`)
- `GET /admin/login` — `admin.login` (middleware `guest:admin`)
- `GET /admin` — `admin.dashboard` (`auth:admin`)
- `POST /admin/sair` — `admin.logout` (`auth:admin`)

Tenant (`routes/tenant.php`, grupo `tenant`, prefixo `{tenant}`):
- `GET /{tenant}` — `tenant.home` (portal, público)
- `GET /{tenant}/login` — `cliente.login` (`guest:cliente`)
- `GET /{tenant}/registrar` — `cliente.registrar` (`guest:cliente`)
- `POST /{tenant}/sair` — `cliente.logout` (`auth:cliente`)
- `GET /{tenant}/painel/login` — `painel.login` (`guest:web`)
- `GET /{tenant}/painel` — `painel.dashboard` (`auth:web`)
- `POST /{tenant}/painel/sair` — `painel.logout` (`auth:web`)

Redirecionamento de acesso negado (em `bootstrap/app.php`):
- não autenticado → login da área (admin / painel / portal), via `redirectGuestsTo`;
- já autenticado em tela de login → área correspondente, via `redirectUsersTo`.

## Componentes (Livewire 4, class-based)

- `App\Livewire\Auth\AdminLogin`, `PainelLogin`, `ClienteLogin`, `ClienteRegistrar`
- `App\Livewire\Admin\Dashboard`, `Painel\Dashboard`, `Portal\Home`
- Trait `App\Livewire\Auth\Concerns\AutenticaPorGuard`: validação, throttle e
  `session()->regenerate()` compartilhados.
- Logout: `App\Http\Controllers\Auth\LogoutController` (encerra guard, invalida
  sessão, regenera token CSRF).

## Layouts (Flux + Tailwind v4)

- `components/layouts/admin.blade.php` — central (topbar + menu do admin).
- `components/layouts/painel.blade.php` — equipe (sidebar Flux com placeholders:
  Agendamentos, Serviços, Clientes, Vendas, Equipe, Clube, Kanban, Configurações).
- `components/layouts/portal.blade.php` — cliente, **mobile-first** (coluna
  estreita, header simples).
- `components/layouts/auth.blade.php` — cartão centralizado das telas de acesso.

CSS: `resources/css/app.css` importa `flux.css`; diretivas `@fluxAppearance` /
`@fluxScripts` nos layouts. Mensagens de validação em pt-BR (`lang/pt_BR`).

## Segurança

- Throttle de 5 tentativas/min por (e-mail + IP); evento `Lockout`.
- Mensagens genéricas (não revelam se o e-mail existe).
- `session()->regenerate()` no login; CSRF ativo (webhooks isentos).
- Apenas usuários/admins com `ativo = true` autenticam (`web` e `admin`).
- Senhas com hash (cast `hashed`).

## Comandos de bootstrap (senha só no prompt, nunca em código)

```bash
# Super-admin central
php artisan nextgest:criar-admin
php artisan nextgest:criar-admin --name="Fulano" --email="fulano@nextgest.com.br"

# Dono de um tenant (cria usuário com papel Dono no banco do tenant)
php artisan nextgest:criar-dono barbeariateste
php artisan nextgest:criar-dono barbeariateste --name="Jorge" --email="jorge@barbearia.com"
```

## Como testar no tenant `barbeariateste`

```bash
php artisan nextgest:criar-dono barbeariateste   # defina nome/e-mail/senha no prompt
# acesse /barbeariateste/painel/login e entre com essas credenciais
# cliente: acesse /barbeariateste/registrar para criar conta e logar no portal
```

## Testes

`tests/Feature/Auth/` (Pest): login ok/erro por guard, admin inativo, throttle,
registro + login do cliente (telefone/e-mail obrigatórios, e-mail único),
isolamento (cliente não acessa painel; login de um tenant não vale em outro;
admin separado). Central em sqlite `:memory:`; tenants em arquivos sqlite
próprios (isolação real, sem tocar MySQL).

```bash
php artisan test
```

## Fora de escopo (próximas fatias)

- Recuperação de senha por e-mail — **requer configurar mail** (MAIL_* no `.env`).
- Verificação de e-mail.
- CRUDs de cadastro (1B) e fluxo de agendamento (1C).

## Livewire + multi-tenancy por caminho (e o 404/419)

O endpoint de update do Livewire é **único e central** (`/livewire-xxxx/update`)
e é o que todas as páginas usam a cada clique/login/modal. **Não** crie uma rota
de update por tenant: uma rota com parâmetro opcional no início
(`/{tenant?}/livewire/update`) **não casa** o caso central (`/livewire/update`
viraria `//livewire/update`) → **404 em todas as páginas**. Foi essa a causa do
"tudo quebrado".

Solução (`App\Providers\AppServiceProvider`): mantém-se o endpoint padrão e
registra-se o `InitializeTenancyByPath` como **persistent middleware** do
Livewire. No update, o Livewire reexecuta esse middleware usando o **caminho
original** da página (que contém o `{tenant}`), reinicializando o tenancy — então
o componente roda no contexto certo sem rota por tenant.

Sessão: **cookie único compartilhado** (resolve o CSRF/419, pois o update e a
página usam a mesma sessão). O isolamento de login entre tenants é feito por
`App\Http\Middleware\EscoparAutenticacaoPorTenant`: ao detectar que a sessão é de
**outro** tenant, encerra os guards `web`/`cliente` (cada estabelecimento exige
login próprio). O guard `admin` (central) não é afetado.

Cookie `Secure`: em produção (https) o cookie é `Secure`; para testar em http use
`SESSION_SECURE_COOKIE=false` e `APP_URL` http (ver `docs/GUIA-DE-TESTES.md`).

> Lacuna de testes coberta: `Livewire::test()` não passa pela rota HTTP, por isso
> não pegava o 404. Há testes de fumaça em `tests/Feature/Smoke/` que validam as
> rotas (200/302) e que a página aponta para uma rota de update **registrada**.

## Suporte do super-admin (impersonação)

No `/admin/estabelecimentos/{slug}`, o botão **Entrar no painel (suporte)** usa o
recurso `UserImpersonation` do stancl: gera um token de uso único (tabela central
`tenant_user_impersonation_tokens`), redireciona para `/{slug}/suporte/{token}`,
que loga o super-admin como o **Dono** no contexto do tenant. A sessão marca
`suporte_ativo` (faixa "Modo suporte" no painel) e o acesso é registrado no log.
Sair: `POST /{slug}/painel/suporte/sair`.

## Suposições (não bloqueiam)

- Login do cliente por e-mail (telefone/OTP fica para o futuro).
- Landing central é uma página simples da marca.
