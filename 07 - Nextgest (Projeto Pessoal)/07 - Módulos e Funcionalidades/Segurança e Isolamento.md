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
| **VULN-001** | Robustez cross-tenant (painel) | **baixa** | `/{outro}/painel` (dashboard) → **500** em vez de 302 (usuário nulo após logout do EscoparAutenticacaoPorTenant, que roda DEPOIS do Authenticate; dashboard não tem `can:`). **Sem vazamento** de dados. Stack trace só vazaria com `APP_DEBUG=true` (proibido em prod) | doc (live) |
| **VULN-002** | Tenant inativo | **média** | `tenant.ativo=false` ainda serve portal/painel → **200**. Não há middleware checando `tenant('ativo')`; só o `ativo` do USUÁRIO é checado no login | `IsolamentoTenantTest` (`skip` VULN-002) |
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

## Plano de correção priorizado (aguarda aprovação — NÃO aplicado)
1. **VULN-002 (média):** middleware (ou check no grupo `tenant`) que aborta 404/503 quando
   `tenant('ativo') === false`, isentando rotas de suporte/login do admin. Teste: remover o
   `skip` e exigir bloqueio. (Decidir: portal e painel bloqueiam; o que mostrar — 404 ou
   página "estabelecimento suspenso".)
2. **VULN-001 (baixa):** ordenar `EscoparAutenticacaoPorTenant` ANTES do `Authenticate`
   (ou fazê-lo redirecionar/abortar limpo) para que cross-tenant dê 302, não 500; e/ou
   guarda de usuário nulo no `Dashboard`. Teste: cross-tenant no dashboard → 302.

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
