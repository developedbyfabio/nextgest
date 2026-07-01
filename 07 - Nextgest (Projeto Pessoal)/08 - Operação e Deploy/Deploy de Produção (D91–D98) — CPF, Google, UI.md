# Deploy de Produção (D91–D98) — CPF, Google (Socialite) e UI do portal

> **Data:** 2026-07-01. **Ambiente:** produção (VPS Hostinger, `nextgest.com.br`,
> `187.127.24.165`). **Só dados de teste** em produção (sem cliente real). Seguiu o
> "Roteiro de Deploy Seguro" com backup obrigatório.

## O que subiu (10 commits: `182003c` → `546960c`)
- **D91** Portal: "Já tenho conta" vira botão sólido na cor secundária.
- **D92** Portal: marca configurável (ícone predefinido ou logo).
- **D93** Portal: rodapé + Política de Privacidade + Termos de Uso.
- **D94** Cliente: **CPF** obrigatório e único por tenant, com gate.
- **D95** **Login com Google** (Socialite) — callback central + slug via sessão.
- **D96** Perfil: telefone obrigatório no "Completar cadastro" (gate generalizado).
- **D97/D98** Telefone padronizado com CelularBr (autocadastro + walk-in).

## Método e backup
- Deploy direto na VPS (sessão roda na própria máquina), git `origin` (GitHub).
- **Backup ANTES de tudo:** `/root/backups/nextgest-20260701-215908/` — dumps gzip de
  `nextgest_central` + `tenant_teste` + cópia do `.env` (600). Todos > 0.

## Passos executados
`backup` → `git pull --no-rebase` (`546960c`) → `composer install --no-dev -o`
(entrou `laravel/socialite v5.28`) → `npm ci && npm run build` → `tenants:migrate --force`
→ `optimize:clear` + `config/route/view/event:cache` → `storage:link` (já existia) →
`queue:restart`. Sem `down/up` (sem cliente real).

## Migrações (todas ADITIVAS — drops só em `down()`)
- Central: **nenhuma** pendente.
- Tenant (`tenant_teste`): `2026_07_01_000001_add_cpf_to_clientes` (`cpf` VARCHAR(11)
  nullable + unique), `2026_07_01_000002_add_google_id_to_clientes` (`google_id` nullable
  + unique). Unique tolera múltiplos NULL → clientes antigos coexistem; obrigatoriedade é
  na aplicação/gate.

## Smoke (pós-deploy) — tudo OK
- HTTP 200: landing, `/teste`, `/teste/login`, `/teste/politica-de-privacidade`,
  `/teste/termos-de-uso`, `/teste/completar-cadastro`.
- Rodapé com **Política/Termos**; botão **"Já tenho conta"** renderiza no portal.
- Colunas `cpf` + `google_id` presentes no `tenant_teste` (nullable, unique).
- **Gate CPF = ON** (`exigir_cpf_cliente` default true; sem override em prod).
- **Botão "Continuar com Google" OCULTO** — esperado, sem credenciais no `.env`.
- **Fila viva:** Redis ping ok, fila=0, worker (supervisor, 2) consumindo. `LOG_LEVEL`
  default filtra `Log::info` — usar arquivo/nível `warning+` para observabilidade.
- `storage/logs` sem erros novos (só ruído de bots).

## Rollback (se preciso)
- Colunas novas são aditivas/nulas → **não** desfazer no banco.
- App quebrou: `git checkout 182003c` → `composer install` → `npm run build` → caches →
  `queue:restart`. Pior caso: restaurar dumps de `/root/backups/nextgest-20260701-215908/`.

## Checklist de ATIVAÇÃO do Google em produção (o Fabio faz — segredo NÃO vai no git)
1. No **Google Cloud Console** → credenciais OAuth 2.0 (Client ID web). **Redirect URI
   autorizado:** `https://nextgest.com.br/auth/google/callback`.
2. No `.env` de **produção** adicionar (nunca no git, nunca em chat/log):
   `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`,
   `GOOGLE_REDIRECT_URI=https://nextgest.com.br/auth/google/callback`.
3. `php artisan config:cache` → o botão **"Continuar com Google"** passa a aparecer.
4. Teste real no HTTPS (login pelo Google cria/vincula cliente por `google_id`; o gate de
   perfil pede CPF/telefone se faltar).
5. Ao abrir ao público: no Google Cloud, **Publish** o app no *Audience* (sair de Testing).
- Rotas já no ar: `auth/google/redirect` e `auth/google/callback` (central; slug via sessão).
