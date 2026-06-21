---
projeto: Nextgest
tipo: bug-e-correcao
modulo: multi-tenancy
status: resolvido
criado: 2026-06-21
tags: [nextgest, bug, stancl, tenancy, cache, sessao, fila]
---

# Bug — cache/sessão/fila em `database` quebram dentro do tenant

> Projeto: [[Nextgest - Visão Geral]] · Decisão: [[Decisões de Arquitetura]] (D32) ·
> Resolvido · ver [[Gotchas e Aprendizados do Projeto]]

## Problema
Em um clone novo no servidor `192.168.11.210`, rodar
`php artisan nextgest:demo barbeariateste` falhava com:

```
SQLSTATE[42S02]: Base table or view not found: 1146
Table 'tenant_barbeariateste.cache' doesn't exist
```

## Sintoma
Qualquer operação que toque cache/sessão/fila **dentro do contexto de um tenant**
estoura procurando uma tabela (`cache`, `sessions`, `jobs`) que só existiria no banco
central, não no banco do tenant.

## Causa (confirmada)
A multi-tenancy do stancl **troca a conexão padrão** para o banco do tenant durante a
requisição/execução. Com o `.env` em:

```
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
```

o cache (inclusive o do `spatie/laravel-permission`), a sessão e a fila passam a
resolver suas tabelas **na conexão atual = banco do tenant**, que não as tem.

> [!danger] Efeito colateral grave
> O mesmo erro **abortou a criação do tenant no meio** (o seed dispara cache do
> spatie). Como o MySQL faz **commit implícito de DDL**, as tabelas criadas antes do
> erro permaneceram, mas o ledger `migrations` e o seed não — deixando o tenant
> meio-migrado. Ver [[Bug - Tenant meio-migrado (ledger)]].

## Correção
No `.env` de **desenvolvimento** (D32):

```
CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
```

Depois: `php artisan config:clear`.

> [!note] Produção (VPS)
> Em produção o ideal é voltar a **Redis** (store central, fora do banco do tenant),
> não `database`. `file`/`sync` é a escolha de dev (sem serviço novo).

## Como testar / evitar no futuro
- Após o ajuste, `php artisan nextgest:demo {slug}` conclui sem erro e é idempotente.
- O `.env` **não é versionado** (segredos) → reaplicar esta config em **todo clone**
  ou ambiente novo. Documentado em D32 e na [[Nextgest - Visão Geral]].
- Regra geral: nada que dependa de tabela só do central (cache/sessão/fila) deve usar
  `database` enquanto a conexão padrão for trocada para o tenant.
