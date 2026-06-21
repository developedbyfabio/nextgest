# Nextgest — Prompt: revisão profunda e correção total (sistema acessível sem erros)

> Cole para o Claude root em `/srv/www/nextgest`. Ambiente local (VM, http; `.env`
> já em `APP_ENV=local`, `APP_URL=http://192.168.3.100:8000`,
> `SESSION_SECURE_COOKIE=false`). **Trabalhe com calma e a fundo**: varra o sistema
> inteiro, página por página e ação por ação, no navegador/HTTP real — não só com
> testes unitários. Sem comandos destrutivos autônomos. Commits pequenos.
> Ver [[Gotchas e Aprendizados do Projeto]].

---

## Contexto (importante)
O sistema **não está incompleto** — a arquitetura é Livewire (lógica em
componentes, não em controllers). Há componentes de admin, auth, painel e portal,
layouts, rotas. O que está derrubando tudo é **um defeito único**:

> No console do navegador, em **todas** as páginas (central e tenant):
> `POST http://192.168.3.100:8000/livewire/update` → **404 (Not Found)**.

Esse endpoint é o que o Livewire usa a cada clique/login/modal/formulário. Com ele
em 404, nenhum login funciona, modais mostram "404 Não encontrado" e tudo parece
quebrado. **Essa é a causa raiz da maioria dos sintomas.**

## Prioridade 0 — Consertar o endpoint do Livewire (`/livewire/update`)
1. Garanta um estado limpo: **mate qualquer `php artisan serve` antigo** ainda na
   porta 8000 (`ss -ltnp | grep :8000` → `pkill -f "php artisan serve"`),
   `php artisan optimize:clear` (config, route, view, cache, compiled),
   `composer dump-autoload`, `npm run build`, e suba **um único** processo limpo.
2. Confirme que **não há route cache** com closures atrapalhando e que a rota do
   Livewire está registrada. Descubra por que `/livewire/update` dá 404 (registro
   da rota do Livewire, interação com o middleware/grupo de tenancy, ordem de
   rotas, ou processo estagnado).
3. **Verifique de verdade**: `POST /livewire/update` deve responder 200 numa página
   central (ex.: `/admin/login`) e numa de tenant (ex.: `/{slug}/painel/login`).

## Prioridade 1 — Logins funcionando ponta a ponta (4 acessos)
Sintomas relatados: criar admin e ao entrar dá 404; dono dá 404; cliente cria
conta, loga e **volta para a tela de login** sem entrar. Corrija o ciclo completo
de cada guard (sessão, redirect pós-login, "intended URL", guard correto em
contexto de tenant):
- **super-admin** (`/admin/login`) → cai em `/admin` sem 404.
- **dono/equipe** (`/{slug}/painel/login`) → cai no painel.
- **cliente** (`/{slug}/registrar` e `/{slug}/login`) → entra e **permanece**
  logado no portal (não volta para o login).

## Prioridade 2 — Auditoria de TODAS as rotas (sem 404, sem 500)
Liste todas as rotas e exercite cada uma no navegador/HTTP real; corrija o que
falhar. No mínimo:
- Central: `/` (landing), `/admin/login`, `/admin`, `/admin/estabelecimentos`
  (listar, criar, **detalhe**, criar dono, ativar/inativar, **impersonar/entrar no
  painel**), logout.
- Painel do tenant: login, dashboard, unidades, serviços, equipe, horários,
  papéis, bloqueios, agenda (dia/semana, novo manual, mudar status, remarcar),
  logout.
- Portal do tenant: home, registrar, login, **wizard de agendar** (serviço →
  profissional → dia → horário → confirmar), próximos/cancelar, logout.
Para cada rota: carrega (200), e as ações Livewire funcionam de fato.

## Prioridade 3 — Seeders robustos com dados fictícios funcionando
Entregue um caminho de demonstração que funcione 100%:
- Comando único que prepara tudo: cria/garante o super-admin (ou instrui como
  criar), cria **1–2 tenants já populados** (unidades, serviços, equipe com
  horários, clientes, agendamentos variados) e o **Dono** de cada um, com
  credenciais de demonstração documentadas. Idempotente.
- Meta: rodar o seed e conseguir logar em **todos os perfis** e navegar tudo sem
  nenhum erro.

## Prioridade 4 — Fechar a lacuna de testes
Os testes usam `Livewire::test()` (componente isolado), por isso ficaram verdes
com o endpoint HTTP quebrado. Adicione **testes de fumaça HTTP** que pegariam isso:
- GET em todas as rotas esperando 200/302 (nunca 404/500).
- POST **real** de login de cada guard, seguindo o redirect até a área certa.
- Uma chamada ao endpoint do Livewire (resposta válida, não 404).
Mantenha toda a suíte verde.

## Método
Metódico e exaustivo: percorra tudo, reproduza cada sintoma, corrija, e **re-teste
no navegador/HTTP real**. Pode levar o tempo que precisar. Não conclua sem ter
clicado/validado cada fluxo dos 4 perfis.

## Relatório final
- Causa raiz do `/livewire/update` 404 e a correção aplicada.
- Tabela de todas as rotas com status final (todas OK).
- Confirmação dos 4 logins ponta a ponta (com o passo exato para reproduzir).
- O comando de seed e as credenciais de demonstração.
- Testes de fumaça adicionados e suíte verde.
