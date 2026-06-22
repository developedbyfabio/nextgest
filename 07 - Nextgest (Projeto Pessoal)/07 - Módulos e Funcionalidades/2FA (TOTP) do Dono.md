---
projeto: Nextgest
tipo: módulo
status: implementado
criado: 2026-06-22
tags: [nextgest, auth, 2fa, totp, seguranca, painel, onboarding, dono]
---

# 2FA (TOTP) do Dono — autenticação em duas etapas

> Projeto: [[Nextgest - Visão Geral]] · Decisão: [[Decisões de Arquitetura#D41]] ·
> Relacionado: [[Segurança e Isolamento]] (era o item 3 do plano), [[Senhas (1o login e self-service)]],
> [[Papéis e Permissões (RBAC)]] (D39), cofre cifrado (D38).

## O que é
Segundo fator **opcional** por **TOTP** (app autenticador — Google Authenticator/Authy),
**só para o Dono**. Local, sem SMS/e-mail/custo. Liga quem quiser; ninguém é forçado.

## Biblioteca (consagrada — nunca cripto à mão)
- **`pragmarx/google2fa`** — TOTP (RFC 6238): gerar segredo, verificar código (janela ±1).
- **`bacon/bacon-qr-code`** — QR como **SVG inline** (puro PHP, sem GD/Imagick), embutido no HTML.
- **Por que não Fortify:** assume as rotas de auth e um guard `web` padrão → conflitaria com os
  **3 guards** custom + **tenancy por caminho** + login Livewire (D24). Esse é o stack que o
  Fortify usa por baixo, sem o sequestro de rotas.
- Tudo concentrado em **`App\Support\DoisFatores`** (fonte única da cripto/QR/códigos): não toca
  banco, não loga, só gera/valida.

## Modelo de dados (aditivo, no tenant)
Migração `…_add_two_factor_to_users` em `users` (banco do tenant):
- `two_factor_secret` (text, null) — cast **`encrypted`**, `$hidden`.
- `two_factor_recovery_codes` (text, null) — cast **`encrypted:array`**, `$hidden`.
- `two_factor_confirmed_at` (timestamp, null) — marca a ativação efetiva.
- `User::temDoisFatores()` = segredo **E** confirmado (segredo sem confirmação = "em
  configuração", NÃO exige 2FA no login). `User::consumirCodigoRecuperacao()` = uso único.
- Os campos `two_factor_*` **não** estão em `$fillable` (gravados explicitamente, sem
  mass-assignment).

## Gating (só Dono) — por permissão, nunca por papel (D39)
Permissão **`gerenciar_2fa_proprio`** no `TenantDatabaseSeeder`, atribuída só ao **Dono**
(excluída do Gerente; Recepção/Profissional têm allow-list própria). Re-sync nos tenants
existentes com **`php artisan tenants:seed`** (idempotente). O menu e o modal de perfil só
aparecem/montam para quem tem a permissão (senão o gate do componente abortaria 403).

## Setup — componente ÚNICO reusado em dois lugares
`App\Livewire\Painel\Seguranca\DoisFatores` (view `livewire/painel/seguranca/dois-fatores`):
- **Perfil:** modal embutido no layout do painel (`<livewire:painel.seguranca.dois-fatores />`),
  aberto pelo item **"Autenticação em duas etapas"** do menu de perfil (mesmo menu da troca de
  senha self-service).
- **Onboarding do Dono (1º login):** rota `painel.2fa.onboarding` (`painel/2fa-inicial`), em
  layout `auth`, com botão **"Pular por enquanto"**. O `TrocarSenha::salvar()` redireciona pra
  cá quando o usuário **pode** gerir 2FA (Dono) e ainda não tem; os demais vão direto ao painel.
  > **Onboarding do Dono ≠ wizard do super-admin** (`OnboardingEstabelecimento`): lá o Dono nem
  > está logado, então o QR (que é do Dono) não poderia ser escaneado. Decisão fixa: setup é do Dono.
- **Fluxo:** Ativar → gera segredo (cifrado, confirmado=null) → mostra **QR + chave manual** →
  Dono digita o **código** → se válido, grava `two_factor_confirmed_at`, gera e **exibe os
  códigos de recuperação uma vez** → ATIVO. **Nunca ativa sem o código** (app mal sincronizado
  trancaria o Dono).
- **Gestão (ativo):** **reexibir** / **regenerar** códigos (cada um exige reconfirmar a senha,
  revelação inline) e **desativar** (exige senha; limpa segredo + códigos + confirmação).
- **Segurança da UI:** o segredo/QR/códigos são **dados locais da view**, nunca propriedade
  pública → **fora do snapshot** do Livewire (enviado ao browser). Exibir o QR/chave no setup é
  inerente (o Dono escaneia); fora disso, nada é renderizado de volta.

## Desafio no login (painel, guard `web`)
- `App\Livewire\Auth\Concerns\AutenticaPorGuard`: valida a senha; se `precisaSegundoFator()`
  (sobrescrito só no `PainelLogin` → Dono com 2FA), **desfaz o login** e devolve o usuário.
  **Caminho só-senha inalterado byte a byte** (admin/cliente/usuário sem 2FA): segue com
  `attempt()` + `session()->regenerate()` como sempre.
- `PainelLogin` guarda a pendência em sessão (`2fa.pendente` = só `id` + `remember`, **sem
  segredo**) e manda para `App\Livewire\Auth\DesafioDoisFatores` (rota `painel.2fa.desafio`,
  `painel/2fa`, sob `guest:web` — o usuário ainda **não** está logado).
- O desafio aceita **código TOTP** ou **código de recuperação** (uso único, consumido) → só
  então `Auth::guard('web')->loginUsingId()` + `regenerate()`. Até passar, **não há acesso a
  nada**. **Throttle** próprio (~5 tentativas → bloqueio curto). Sem pendência → volta ao login.
- **Ordem com `ForcarTrocaSenha`:** senha → 2FA → login → (middleware) troca de senha se
  `deve_trocar_senha`. Disjuntos na prática (quem tem 2FA já trocou a senha), mas testado.
- **Portal (`cliente`) e `admin` não mudam.** Impersonação de suporte entra por `loginUsingId`
  (fora do componente de login) → **não** passa pelo desafio, por design.

## Recuperação + reset pelo super-admin
- **Código de recuperação:** loga **uma vez** e é consumido (reuso → recusado). Após entrar por
  recuperação, o Dono pode regenerar/reconfigurar no perfil.
- **Reset (super-admin):** `/admin` → detalhe do estabelecimento → **"Resetar 2FA"** (modal de
  confirmação, D27). Último recurso quando o Dono perde o celular **E** os códigos. Limpa os
  campos no banco do tenant; **logado** (`Log::info`, sem dado sensível). Reversível (o Dono
  reativa depois). `TenantDetalhe::resetar2fa()` usa **`skipRender()`** + redirect (recarrega o
  detalhe) — evita um re-render que reabriria o banco do tenant no mesmo request.

## Gotcha (registrado em [[Gotchas e Aprendizados do Projeto]])
Em **componente CENTRAL que usa `$tenant->run()`** (TenantDetalhe), dois `run()` no **mesmo
processo** — um escrevendo e outro renderizando logo depois — podem estourar
`Database connection [tenant] not configured` no ambiente de teste (sqlite por tenant) e em
sessões longas de tinker (cache stale do spatie). **Produção é segura** (cada request faz só
um `run()`); o caminho de reset usa `skipRender()` + redirect. Nos testes, manter a tenancy
inicializada (sem `end()`) faz o `run()` interno **restaurar** o tenant (sem purge).

## Testes (22 novos; suíte 352 → 374)
- `tests/Feature/Painel/DoisFatoresSetupTest.php` (7): ativar gera segredo não confirmado + QR;
  **nunca ativa sem código válido**; ativa com código + 8 códigos exibidos uma vez; desativar
  exige senha; regenerar/reexibir com senha; **segredo fora do snapshot**; gate só-Dono (Gerente
  403 / Dono 200 na rota).
- `tests/Feature/Auth/DoisFatoresLoginTest.php` (7): Dono com 2FA não autentica (vai ao desafio,
  pendência em sessão); TOTP certo loga; código errado barrado (status cru, sem redirect);
  recuperação **uso único**; throttle; sem pendência volta ao login; ordem com `ForcarTrocaSenha`.
- `tests/Feature/Auth/LoginCaracterizacaoTest.php` (4): âncora — login só-senha de Dono sem 2FA,
  membro comum, super-admin e cliente **inalterados**.
- `tests/Feature/Admin/TenantDetalheTest.php` (+3): reset limpa os campos; confirmarReset abre o
  modal; reset exige super-admin (403). `SenhaTest` (+1) cobre o redirect do não-Dono.

## Verificação (servidor + tinker)
- Endpoints sem 500: `painel/login` 200; `painel/2fa` (sem pendência) 302→login; `painel/2fa-inicial`
  (sem auth) 302. **Cifragem:** `select` cru mostra segredo/recovery **ilegíveis**; ambos ocultos
  no `toArray()`. `laravel.log` **vazio**, sem segredo/código. Página de detalhe com Dono 2FA
  renderiza (caminho de produção confirmado em processos separados).
