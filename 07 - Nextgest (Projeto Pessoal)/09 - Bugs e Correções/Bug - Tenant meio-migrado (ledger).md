---
projeto: Nextgest
tipo: bug-e-correcao
modulo: multi-tenancy
status: resolvido
criado: 2026-06-21
tags: [nextgest, bug, stancl, tenancy, migrations, mysql]
---

# Bug — tenant meio-migrado (commit implícito de DDL) e reparo do ledger

> Projeto: [[Nextgest - Visão Geral]] · Resolvido · consequência de
> [[Bug - Drivers database dentro do tenant]]

## Problema
O tenant `barbeariateste` (criado num clone novo) ficou em estado **meio-migrado**:
papéis/permissões zerados e tabelas faltando (`kanban_quadros` inexistente), mesmo o
banco já existindo.

## Sintoma
- `nextgest:demo` falhava depois do fix de cache com:
  `Spatie\Permission\Exceptions\RoleDoesNotExist: There is no role named 'Profissional'`.
- Inspeção do tenant: 18 tabelas físicas (migrations `190001` + `190002`), mas o ledger
  `migrations` só registrava a `190001`; `190003/190004/190005` não tinham rodado;
  `roles`/`permissions` existiam porém **vazias**.

## Causa (confirmada)
A criação do tenant abortou no meio por causa de
[[Bug - Drivers database dentro do tenant]] (o seed toca o cache do spatie). Como o
**MySQL faz commit implícito em DDL** (`CREATE TABLE` não volta atrás), as tabelas
criadas antes do erro **permaneceram**, mas o que era DML (registro no ledger
`migrations`, `INSERT`s do seed) foi revertido. Resultado: schema parcial + ledger
desalinhado.

> [!warning] Por que não basta rodar `tenants:migrate`
> Com o ledger só na `190001`, o migrator tentaria recriar as tabelas da `190002`
> (que **já existem** fisicamente) → erro "table already exists".

## Correção (aditiva, sem DROP/`migrate:fresh`)
Reparo **não-destrutivo** — respeita o CLAUDE.md (nada de DROP/TRUNCATE/fresh):

1. Registrar no ledger a migration cujas tabelas já existiam fisicamente:
   `INSERT` de `2026_06_14_190002_create_permission_tables` (batch 1) na tabela
   `migrations` do tenant.
2. `php artisan tenants:migrate --tenants=barbeariateste` → roda só as pendentes
   (`190003/190004/190005`).
3. `php artisan tenants:seed --tenants=barbeariateste` → cria papéis/permissões/kanban
   (seeder idempotente).
4. `php artisan nextgest:demo barbeariateste` → agora conclui.

> [!note] Alternativa limpa
> Com o cache já corrigido, um tenant **criado do zero** nasce íntegro (testado com
> `salaoteste`). A recriação limpa do `barbeariateste` exigiria `DROP DATABASE`
> (destrutivo) — só com um humano.

## Como evitar no futuro
- Corrigir os drivers (D32) **antes** de criar tenants, para o pipeline
  (CreateDatabase → MigrateDatabase → SeedDatabase) rodar inteiro.
- Ao ver tenant "meio-criado", comparar **tabelas físicas** (`SHOW TABLES`) com o
  ledger `migrations` antes de migrar; o descompasso indica DDL commitado fora do
  ledger.
