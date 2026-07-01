# Login com Google (Socialite)

> Projeto: [[Nextgest - Visão Geral]] · Decisão: [[Decisões de Arquitetura#D95 — Login/cadastro do cliente via Google (Socialite, callback central + slug via sessão)|D95]] ·
> Relacionados: [[Clientes (CRM)]] (CPF/gate D94), [[Segurança e Isolamento]] · Atualizado: 2026-07-01.

## Ideia
Login e cadastro do **cliente** (guard `cliente`) via **Google** com `laravel/socialite`,
seguindo o tema. Só a parte que **roda em dev** (atrás de gate de config); o registro do app
no Google e o teste real ponta a ponta ficam para **depois do deploy** (exigem https).

## Decisão central: callback ÚNICO + slug via sessão
O Google **não aceita wildcard de path** na redirect URI — então NÃO dá para ter
`/{tenant}/auth/google/callback`. Registra-se **uma** URI central:
`https://nextgest.com.br/auth/google/callback`. Como o cliente vive no banco do TENANT,
o slug precisa viajar no round-trip:
- Botão em `/{tenant}/login|registrar` → `GET /auth/google/redirect?tenant={slug}` (central).
- `redirect` valida o slug (tenant conhecido) e **guarda na sessão** (domínio central, sessão
  compartilhada entre os paths). NÃO mexe no `state` de CSRF do Socialite.
- `callback` **lê o slug da sessão**, revalida (senão aborta p/ landing), **inicializa o
  tenancy** e só então consulta/grava o cliente.

Rotas em `routes/web.php` (centrais, precedência sobre `/{tenant}`), nomes
`auth.google.redirect` / `auth.google.callback`. Slug `auth` adicionado aos
`reserved_slugs` (nenhum tenant pode se chamar `auth`).

## Vínculo de conta (find-or-create, no banco do tenant)
`App\Http\Controllers\Auth\GoogleController::encontrarOuCriar()`:
1. por `google_id` → usa;
2. senão por **e-mail** (o Google entrega e-mail verificado) → **vincula** o `google_id` à
   conta existente (não duplica);
3. senão **cria** (`nome`/`email`/`google_id`; `telefone` vazio — Google não dá telefone, a
   coluna é NOT NULL; sem senha; sem CPF).

Migração aditiva por tenant: `clientes.google_id` nullable + unique (tolera NULL). Guardamos
só o identificador — **nunca** tokens.

## Reuso do gate de CPF (D94)
O novo usuário do Google entra **sem CPF** → o middleware `cpf.cliente` (já em `home`/`agendar`)
o leva a **"Completar cadastro (CPF)"** antes de liberar o portal. **Sem duplicar** a lógica —
é o mesmo gate da Fatia de CPF.

## Botão "Continuar com Google"
`x-portal.botao-google` (incluído em login/registro, dentro do shell `x-portal.auth`):
- **Gate de config:** só renderiza quando `config('services.google.client_id')` está setado
  (credenciais só via `.env`; sem segredo no código). Em dev sem `.env` → oculto.
- **Diretrizes de marca do Google:** botão oficial (logo colorido + texto), variações
  **clara/escura** (`dark:`). O acento do tenant tematiza o **entorno**, **NÃO** o botão
  (recolorir fere as diretrizes e pode reprovar o app).

## Erros tratados (mensagem amigável, volta ao login do tenant)
tenant inválido/ausente → landing; usuário cancela / erro de OAuth → login do tenant; e-mail
não verificado → login do tenant. **Sem logar tokens.**

## Config (`config/services.php`)
`google.client_id` / `client_secret` / `redirect` — todos via `.env`
(`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`).

## ✅ Checklist para PRODUÇÃO (você faz — precisa de https)
- [ ] Google Cloud Console: criar projeto + **tela de consentimento** (OAuth) + credenciais OAuth 2.0.
- [ ] **Redirect URI autorizada:** `https://nextgest.com.br/auth/google/callback` (exatamente).
- [ ] Origem JavaScript autorizada (se aplicável): `https://nextgest.com.br`.
- [ ] `.env` de produção: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`,
      `GOOGLE_REDIRECT_URI=https://nextgest.com.br/auth/google/callback`; `config:cache`.
- [ ] Teste real ponta a ponta (login novo, vínculo por e-mail, gate de CPF) no domínio https.
- [ ] Publicar a tela de consentimento (sair de "Testing") quando for abrir ao público.

## Testes (Socialite mockado — sem chamar o Google)
`tests/Feature/Auth/GoogleAuthTest.php` (10): redirect guarda slug/ vai ao Google; tenant
inválido aborta; callback sem slug aborta; cria novo com `google_id`; vincula por e-mail sem
duplicar; gate de CPF (sem CPF → completar; com CPF → direto); e-mail não verificado e
cancelamento voltam ao login; botão só com `client_id`.
