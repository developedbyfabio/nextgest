# Bug — Livewire `/livewire/update` dava 404 em todo o sistema

> Projeto: [[Nextgest - Visão Geral]] · Resolvido · Ver [[Gotchas e Aprendizados do Projeto]]

## Problema
Tudo parecia quebrado: nenhum login funcionava (caía em 404), modais mostravam
"404 Não encontrado", e o cliente cadastrava conta mas ao logar voltava para a
tela de login. No console, em **todas** as páginas (central e tenant):
`POST /livewire/update` → **404 (Not Found)**.

## Sintoma
O endpoint que o Livewire usa a cada clique/login/modal/formulário estava 404, o
que derruba toda a interatividade do sistema de uma vez.

## Causa (confirmada)
Numa correção anterior, a rota de update do Livewire foi registrada como
`/{tenant?}/livewire/update` (parâmetro de tenant **opcional no início**). O
Laravel não casa esse padrão para o caso central — `/livewire/update` exigiria
`//livewire/update`. Logo, o endpoint dava 404 em qualquer página.

## Correção
No `App\Providers\AppServiceProvider`:
- Voltar ao **endpoint padrão único** do Livewire (`/livewire-xxxx/update`, que
  sempre casa) — **não** sobrescrever com rota custom.
- Registrar `InitializeTenancyByPath` como **persistent middleware**: no update, o
  Livewire reexecuta esse middleware usando o caminho original da página (que
  contém o `{tenant}`), reinicializando o tenant.
- Sessão passa a usar **cookie único compartilhado** (resolve o CSRF/419); o
  isolamento de login entre tenants fica por `EscoparAutenticacaoPorTenant`.
- Remover middlewares obsoletos (`ScopeSessionToTenant`,
  `InitializeTenancyByPathQuandoPresente`).
- Estado limpo: matar serve antigo, `optimize:clear`, `composer dump-autoload`,
  `npm run build`, subir um único processo.

## Como testar / evitar no futuro
> [!note] Endpoint real usa hash (confirmado por HTTP em 21/06/2026)
> O endpoint que o Livewire de fato usa é **`/livewire-<hash>/update`** (ex.:
> `/livewire-c7530b77/update`) — o `livewire.js` posta nessa URL automaticamente.
> O `/livewire/update` **literal dá 404 por design** (não é a rota). O teste correto
> é bater no endpoint com hash: deve responder **419** (sem token CSRF) ou **200**,
> **nunca 404** — verificado em contexto central e de tenant. A rota fica visível em
> `php artisan route:list | grep livewire` (`default-livewire.update`).
- `POST` no endpoint do Livewire deve responder **200** (ou 419 sem token), **nunca
  404**, em página central e de tenant.
- **Testes de fumaça HTTP** (`tests/Feature/Smoke/HttpSmokeTest.php`): GET em todas
  as rotas (200/302), login real seguindo redirect, e verificação do endpoint do
  Livewire. O `Livewire::test()` sozinho **não** pega isso (chama o componente
  isolado, sem o HTTP real).
- Cuidado: **sobrescrever `setUpdateRoute` quebra o `Livewire::test`** — preferir a
  solução por persistent middleware, que funciona no HTTP real e nos testes.
