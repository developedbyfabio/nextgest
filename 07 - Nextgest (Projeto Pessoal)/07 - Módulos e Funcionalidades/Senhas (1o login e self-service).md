---
projeto: Nextgest
tipo: módulo
status: implementado
criado: 2026-06-22
tags: [nextgest, auth, senha, painel, onboarding, seguranca]
---

# Senhas — troca obrigatória no 1º login + alteração self-service

> Projeto: [[Nextgest - Visão Geral]] · Onboarding: [[Onboarding Guiado de Estabelecimento]]
> Vale só no **painel** (guard `web`, usuário vive no banco do tenant). O **portal do
> cliente** (guard `cliente`) não é afetado.

## Troca OBRIGATÓRIA no 1º login (Dono criado pelo admin)
- O onboarding admin cria o **Dono** com a **senha inicial** definida pelo super-admin
  (sempre **hasheada** — cast `hashed`) e marca **`deve_trocar_senha = true`** (coluna
  booleana aditiva em `users`, default false; não documentar a senha real → `[senha inicial]`).
- **Middleware `App\Http\Middleware\ForcarTrocaSenha`** (registrado **só** no grupo
  `auth:web` das rotas de tenant): se o usuário autenticado do painel tem
  `deve_trocar_senha = true`, é redirecionado para **`painel.senha`** e bloqueado no resto
  do painel — **exceto** a própria rota de troca, o **logout** e o **sair do suporte** (para
  não criar loop e permitir sair).
- **Tela** `App\Livewire\Auth\TrocarSenha` (layout `auth`, sem navegação): nova senha +
  confirmação (o usuário já entrou com a senha inicial). Ao salvar: grava o **hash**, limpa
  `deve_trocar_senha` e volta ao painel. Se a flag já estiver limpa, a tela redireciona ao
  painel (uso único).
- **Escopo/ordem:** o middleware roda DEPOIS da tenancy/sessão (grupo `tenant`) e do
  `auth:web`, sem conflitar com `InitializeTenancyByPath`/`EscoparAutenticacaoPorTenant`.
  Como verifica `auth('web')`, o portal do cliente nunca é afetado.

## Alteração self-service (todos os papéis)
- No **menu de perfil** do painel (canto inferior esquerdo) há **"Alterar senha"**, que abre
  um **modal** (`App\Livewire\Painel\AlterarSenha`, embutido no layout do painel).
- Pede **senha atual** (validada por `Hash::check`, recusa se errada) + **nova** +
  **confirmação**. Disponível a **Dono, Gerente, Recepção, Profissional** (basta estar
  logado no painel — sem permissão extra).

## Regras de senha (fonte única)
`App\Support\Senhas::regrasNova()` → `['required','string','confirmed', Password::min(8)]`.
Usada pela troca obrigatória **e** pela alteração self-service (sem duplicar regra). Senha
sempre via cast `hashed`; nunca logada/exposta.

## Testes
`tests/Feature/Auth/SenhaTest.php` (8): middleware redireciona com a flag e **não** após
trocar (HTTP autenticado por tenant, não só Livewire); troca forçada atualiza hash + limpa
flag; rejeita senha fraca/sem confirmação; tela redireciona se a flag já está limpa;
self-service recusa senha atual errada e senha fraca, e troca com sucesso **nos 4 papéis**;
o **portal não é afetado**. `OnboardingTest` confere que o Dono nasce com `deve_trocar_senha`.

## 2FA opcional no 1º login do Dono
Após a troca forçada, o `TrocarSenha::salvar()` leva o **Dono** (quem tem `gerenciar_2fa_proprio`)
que ainda não tem 2FA ao passo **opcional/skippável** de ativação (`painel.2fa.onboarding`); os
demais papéis vão direto ao painel. Ver [[2FA (TOTP) do Dono]].

## Relacionado
- [[Onboarding Guiado de Estabelecimento]] · [[Decisões de Arquitetura]] (guards/tenancy) ·
  [[2FA (TOTP) do Dono]] · `EscoparAutenticacaoPorTenant` (isolamento de sessão por tenant).
