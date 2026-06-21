---
projeto: Nextgest
tipo: bug-e-correcao
modulo: multi-tenancy
status: resolvido
criado: 2026-06-14
tags: [nextgest, bug, stancl, tenancy]
---

# Bug — Tenant criado com id 0 (banco `tenant_0`)

## Problema
Durante a verificação do build, a criação de tenant gerava `id = 0`, resultando
num banco chamado `tenant_0`.

## Sintoma
Banco `tenant_0` criado indevidamente; o nome do banco do tenant depende do id.

## Causa
O `getIncrementing()` do modelo de tenant (configuração do `stancl/tenancy`)
fazia o id ser tratado como não incremental, virando 0.

## Como foi corrigido
Ajuste no modelo de Tenant para o id incrementar corretamente, de modo que o
banco vire `tenant_{slug}` (e não `tenant_0`).

## Como testar
Criar um tenant pelo fluxo normal e conferir o nome do banco gerado
(`tenant_{slug}`) e a execução das migrations de tenant.

## Como evitar no futuro
Ao customizar o modelo de Tenant do stancl, validar incrementing/keyType antes de
ir para criação de bancos. Cobrir com um teste de criação de tenant.

## Pendência relacionada
Sobrou um banco órfão `tenant_0` (vazio) que precisa de `DROP DATABASE` manual —
o agente não executa comando destrutivo.

## Projeto
[[Nextgest - Visão Geral]] · ver [[Decisões de Arquitetura]] (D01, D02).
