# Segurança e Isolamento (verificação)

> Projeto: [[Nextgest - Visão Geral]] · Auditoria + suíte de abuso permanente · Ver [[Decisões de Arquitetura#D38]] e [[Papéis e Permissões (RBAC)]]

Verificação de (a) isolamento entre tenants, (b) autorização interna, (c) proteção de
dados sensíveis (dono e clientes). Testes em `tests/Feature/Security/`. **Fixes não
aplicados** — este doc registra achados + plano priorizado (aguardando aprovação).

## Princípio do verificador (registrado p/ futuras tarefas)
Teste de segurança que passa pelo motivo errado é pior que não ter teste. Dois artefatos
que produzem falso resultado neste projeto:
1. **Redirect mascara status** — o cliente HTTP de teste do Laravel NÃO segue redirect
   (bom); em curl/requests, usar `allow_redirects=False`. Asserir status CRU.
2. **`actingAs()` em cross-tenant é INFIEL** — injeta o usuário em memória no guard e
   sobrevive entre requests do mesmo processo, burlando a resolução sessão→banco. Para
   cross-tenant, montar a SESSÃO real (`login_{guard}_{sha1(SessionGuard)}`) e deixar o
   guard resolver contra o banco do tenant alvo (como em produção). Ver
   `tests/Feature/Security/IsolamentoTenantTest.php`.

## Tabela de achados

| ID | Área | Severidade | Evidência (status CRU) | Teste |
|----|------|-----------|------------------------|-------|
| — | Isolamento DB-per-tenant | OK (protegido) | id de B é `null` no contexto de A | `IsolamentoTenantTest` (find null) |
| — | Sessão cross-tenant (web/cliente) | OK | sessão de A não autentica em B; **sem vazamento** de dado de B (live: 302/500, leak=False) | `IsolamentoTenantTest` |
| **VULN-001** ✅ CORRIGIDA | Robustez cross-tenant (painel) | baixa | Era: `/{outro}/painel` → **500**. Fix: `EscoparAutenticacaoPorTenant` reordenado ANTES do `Authenticate` (+ guarda de usuário nulo no `Dashboard`). Agora → **302 limpo**, sem vazamento (confirmado live) | `IsolamentoTenantTest` (exige 302) |
| **VULN-002** ✅ CORRIGIDA | Tenant inativo | média | Era: tenant inativo servia **200**. Fix: `App\Http\Middleware\GarantirTenantAtivo` no grupo de tenant (após o init) → **404** em painel e portal (inclusive login do tenant). Admin/central intactos | `IsolamentoTenantTest` (404 painel+portal; ativo 200) |
| — | Autorização por permissão (`can`) | OK | Recepção→comissões 403; Profissional→equipe 403; Gerente→papéis 403; **camada dupla** (rota `can:` + `authorize`/`abort_unless` no `mount`) | `AutorizacaoTest` |
| — | IDOR de comanda (`VendaPolicy`) | OK | Profissional só gere a própria comanda (com `agendamento_id`); avulsa/de outro → bloqueado | `AutorizacaoTest` |
| — | Livewire server-side + snapshot | OK | Gerente barrado no editor de pagamento no servidor; snapshot sem o segredo | `AutorizacaoTest` |
| — | Throttle de login (3 guards) | OK | 6ª tentativa → "Muitas tentativas" (RateLimiter 5/min em `AutenticaPorGuard`) | `AutenticacaoTest` |
| — | Enumeração de usuário | OK | mensagem de login genérica; **sem** fluxo de reset/esqueci senha (sem superfície) | `AutenticacaoTest` |
| — | Hash de senha | OK | coluna `password` cifrada (cast `hashed`); `Hash::check` ok | `AutenticacaoTest` |
| — | Mass assignment | OK | `role`/`is_admin` ignorados no `create`; papel só via `syncRoles` em tela gated; `e_profissional` só pela equipe (gated) | `AutorizacaoTest` |
| — | XSS armazenado | OK | nome com `<script>` é escapado (`{{ }}`) na lista de equipe | `ExposicaoTest` |
| — | Upload (aparência) | OK | `image\|mimes:png,jpg,jpeg,webp\|max:5120` (sem SVG/script); path traversal mitigado em `TenantArquivoController` (realpath + prefixo) | `ExposicaoTest` + auditoria |
| — | SQL raw | OK | `DB::raw`/`havingRaw` em `Metricas` são strings ESTÁTICAS (sem interpolação de input) | auditoria |
| — | Segredos no repo/histórico | OK | `.env` não versionado (e no `.gitignore`); nada óbvio rastreado; `.env` nunca commitado | scan git + `ExposicaoTest` (token cifrado, nunca em HTML/snapshot) |

## "Test the test" (os críticos têm dente)
- **T2** (Recepção→comissões): removendo `can:ver_financeiro` (rota) **e** `authorize` (mount) → teste foi a **200** (vermelho). Revertido.
- **T3** (Gerente→pagamento): removendo `can:gerenciar_pagamentos` (rota) **e** `abort_unless` (mount) → teste foi a **vermelho** (404≠403). Revertido.
- **T1**: dente inerente — DB-per-tenant (a asserção `find()===null` fica vermelha se os tenants compartilhassem banco); a fronteira cross-tenant foi confirmada FIELMENTE no servidor vivo (cookies reais, processos separados): **nenhum vazamento** de dados de B.
- Camada dupla observada (rota + componente): remover só UMA camada não abre acesso — bom sinal.

## Resumo
**Comprovadamente isolado/seguro:** DB-per-tenant; sessão escopada; `can()` em tudo que é
sensível (camada dupla); IDOR de comanda; throttle nos 3 guards; hash de senha; sem
enumeração; mass assignment contido; XSS escapado; upload restrito (sem SVG) + anti path
traversal; SQL sem interpolação; cofre de credenciais cifrado e nunca exposto; sem segredo
no repo/histórico.
**Gaps reais:** VULN-002 (tenant inativo não bloqueado — média); VULN-001 (cross-tenant no
dashboard → 500 em vez de 302 limpo — baixa/robustez, sem vazamento).

## Correções aplicadas (ambas fechadas — ver [[Decisões de Arquitetura#D40]])
1. **VULN-002 (média) → fechada:** `App\Http\Middleware\GarantirTenantAtivo` adicionado ao
   grupo `tenant` (logo após `InitializeTenancyByPath`): `tenant.ativo === false` → `abort(404)`.
   Vale para painel (`web`) e portal (`cliente`), inclusive o login do tenant. Rotas
   centrais/`admin` não passam pelo grupo → intactas. Inativar continua reversível (não apaga).
2. **VULN-001 (baixa) → fechada:** `EscoparAutenticacaoPorTenant` reordenado ANTES do
   `Authenticate` via `prependToPriorityList(before: Authenticate::class, ...)` em
   `bootstrap/app.php` — a sessão cross-tenant é descartada antes do `Authenticate`, que
   redireciona limpo (302) ao login do tenant. Defesa em profundidade: guarda de usuário nulo
   no `Dashboard::mount`. Confirmado live: cross-tenant → 302 (não 500), `leak=False`;
   mesmo-tenant → 200 (sem regressão).

## Checklist de PRODUÇÃO (Fase 1 — não verificável no dev)
- `APP_DEBUG=false`, `APP_ENV=production` (senão 500 vaza stack trace — ver VULN-001).
- HTTPS forçado; cookies `secure`+`httponly`(já)+`samesite`; HSTS.
- Headers: `X-Frame-Options`/CSP/`X-Content-Type-Options`.
- Sessão/cache/fila em Redis com chaves **isoladas por tenant** (hoje `database`; **não há
  migração de `sessions`/`cache` no repo** — provisionar). Sessão hoje é cookie único +
  `EscoparAutenticacaoPorTenant`.
- `APP_KEY` estável (credenciais cifradas dependem dele).
- Limites do `php.ini` (upload).
- Backups por tenant.

## Auditoria — separação portal × painel + login alcançável × obscuridade (2026-06-22)
> Auditoria a pedido do Fabio (login da equipe é visível em `/{slug}/painel`). **Nenhuma
> mudança aplicada** — mapeamento + plano. Testes novos só comprovam o estado atual
> (`tests/Feature/Security/SeparacaoPortalPainelTest.php`). Suíte: **352 verde**.

### Premissa (registrada)
Login **alcançável** não é falha. A proteção é **auth + authz** (provado em D40), não
esconder a URL. Esconder o painel seria **segurança por obscuridade** (fraca: vaza por
histórico/print/e-mail/referer; e dá falsa sensação de segurança). Foco real: portal sem
links para o painel + reforço de defesa do login.

### Mapa portal (`cliente`) × painel (`web`)
Tudo sob `/{tenant}/…` (grupo `tenant` em `bootstrap/app.php`: `InitializeTenancyByPath`
→ `GarantirTenantAtivo` → sessão → CSRF → `EscoparAutenticacaoPorTenant`). Slug barrado
por regex contra `reserved_slugs` (`/admin`, `/login` etc. vão para o app central).
- **Portal (guard `cliente`)** — `routes/tenant.php`: `GET /` → `Portal\Home`
  (`tenant.home`); `GET login` → `Auth\ClienteLogin` (`guest:cliente`); `GET registrar`;
  `GET agendar` (`auth:cliente`). Layout `components/layouts/portal.blade.php`.
- **Painel (guard `web`)** — prefixo `painel`: `GET login` → `Auth\PainelLogin`
  (`guest:web`, nome `painel.login`); resto sob `auth:web` + `ForcarTrocaSenha` + `can:`
  por página. Layout `components/layouts/painel.blade.php`.
- **Separação:** mesma tenancy path-based, **guards distintos** (`cliente` × `web`,
  providers `clientes` × `users`) + sessão escopada por tenant. `redirectGuestsTo`
  (bootstrap) manda anônimo ao login da **área certa** (admin/painel/portal).
- **Evidência (recap D40):** anônimo em `/{slug}/painel` → **302** `painel.login` (sem
  dado); cross-tenant → 302 limpo; tenant inativo → 404. (novo teste cobre o 302/200.)

### Vazamento de link portal→painel: **NÃO HÁ** (varrido)
`grep -rniE "painel" resources/views/` → **zero** ocorrências em `livewire/portal/*`,
`components/portal/*`, `components/layouts/portal.blade.php`, `livewire/auth/cliente-*` e
no `auth.blade.php` (layout compartilhado dos logins). Todas as menções a `painel.*`
vivem **dentro** do próprio painel (layout/telas) ou nos fluxos admin/troca-senha.
- Rodapé do portal: só `Powered by Nextgest` (texto, sem link).
- `robots.txt`: `Disallow:` vazio (libera tudo) — **não** referencia `/painel`; **sem**
  sitemap; sem comentário HTML/asset citando a área da equipe. Landing central cita
  "equipe e permissões" como feature, sem link para login de tenant.
- **Conclusão:** o portal já está limpo. Nada a remover (item 1 do pedido: OK por construção).

### Defesa do login da equipe — estado atual × plano (NÃO aplicado)
Trait único `Auth\Concerns\AutenticaPorGuard` (admin/web/cliente):
- **Rate limit:** `RateLimiter` 5/min, chave = `lower(email)|IP` → por **(e-mail+IP)**.
  Não há lockout progressivo nem distinção conta-só × IP-só. Dispara evento `Lockout`,
  **mas sem listener** (não vira log).
- **Enumeração:** mensagem genérica única; sem reset/"esqueci senha" → sem superfície. OK.
- **Sessão:** `session()->regenerate()` no sucesso (anti-fixation). OK.
- **Log de acesso:** **inexistente** — não há registro de login OK/falha (só `Log::info`
  de impersonação de suporte e `Log::warning` de recurso desconhecido).
- **2FA:** inexistente.

**Plano priorizado (proposta — aguarda ok):**
1. **dev-agora (barato):** listener para `Lockout` + `Failed`/`Login` gravando
   `tenant, guard, email_mascarado, ip, ua, resultado` (sem senha/sem PII sensível) →
   canal de log dedicado/tabela `login_attempts` por tenant. Auditoria + base p/ lockout.
2. **dev-agora:** lockout **progressivo** (backoff: bloqueio cresce a cada janela) e
   chave **dupla** (e-mail global + IP) para conter brute force distribuído e password
   spraying sem travar um usuário legítimo por culpa de um IP barulhento.
3. **pós-VPS (depende de canal e-mail/SMS):** **2FA opcional do Dono** (TOTP como 1ª
   escolha — não exige canal; SMS/e-mail como fallback) — a defesa real da conta que mexe
   em dinheiro/credenciais. Aqui só o plano; não construir.
4. **produção (já no checklist):** cookies `secure`+`samesite`+HSTS, headers de segurança.

### "Slug secreto" para o painel — **NÃO compensa** (obscuridade, não segurança)
Trocar `/{slug}/painel` por URL secreta quebraria, no mínimo:
- `redirectGuestsTo`/`redirectUsersTo` (casam `*/painel`), `LogoutController` →
  `painel.login`, `ForcarTrocaSenha` → `painel.senha`, `Dashboard::mount` → `painel.login`.
- ~20 `route('painel.*')` no layout/telas do painel + redirects dos componentes; o nome
  de rota `painel.*` e o prefixo de tenancy path-based; **bookmarks** da equipe; e os
  testes que assumem `/painel` (Auth/Painel/Smoke).
- **Veredito (custo × risco × ganho):** alto custo de ref+regressão; ganho nulo —
  classifica-se como **obscuridade**, não segurança (o segredo vaza por histórico/print/
  referer e mascara a ausência de defesa real). **Não fazer.**

### Recomendação consolidada (1 frase)
Manter a URL `/{slug}/painel` e o portal já limpo de links; investir em **defesa real**
(log de tentativas + lockout progressivo agora; 2FA do Dono pós-VPS) — **não** trocar a URL.
