# 08 — Autenticação e layout base (fatia 1A)

Login/logout dos três guards, UI com Flux, portal do cliente mobile-first.
Sem regras de agendamento ainda.

## Guards e áreas

| Área | Guard | Banco | Login | Pós-login |
|---|---|---|---|---|
| Super-admin (SaaS) | `admin` | central | `/admin/login` | `/admin` |
| Equipe do estabelecimento | `web` | tenant | `/{tenant}/painel/login` | `/{tenant}/painel` |
| Cliente final | `cliente` | tenant | `/{tenant}/login` | `/{tenant}` |

Sessões isoladas: o central usa o cookie de sessão padrão; cada tenant usa um
cookie próprio (`ScopeSessionToTenant`), então login de um estabelecimento não
vale em outro nem no admin.

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

## Suposições (não bloqueiam)

- Login do cliente por e-mail (telefone/OTP fica para o futuro).
- Landing central é um placeholder por ora.
