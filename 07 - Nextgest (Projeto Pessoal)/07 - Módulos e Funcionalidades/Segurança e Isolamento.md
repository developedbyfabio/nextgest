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
