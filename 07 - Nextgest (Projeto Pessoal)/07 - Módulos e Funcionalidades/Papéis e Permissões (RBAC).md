# Papéis e Permissões (RBAC)

> Projeto: [[Nextgest - Visão Geral]] · Ver [[Decisões de Arquitetura#D39]]

Controle de acesso da equipe do estabelecimento (guard `web`), com **spatie/laravel-permission**
no banco de cada tenant. Modelo pensado para equipes reais variadas (dono que também atende,
dois donos, salão sem gerente) — **sem** refatoração especulativa.

## Princípios-norte
- **Gate por permissão, nunca por papel.** Acesso sensível checa `can('permissão')` (rota
  `can:` ou `abort_unless(...->can())`), **nunca** `hasRole('Dono')`. Papel = pacote de permissões.
- **Multi-papel:** um membro pode ter **vários** papéis (ex.: Dono + Profissional).
- **"Atende/agendável" é atributo, não papel:** depende de `users.e_profissional`, não do papel
  `Profissional`. Assim um Dono pode atender (marca o switch).
- **Dono é sempre superset** de todas as permissões.

## Provisionamento (por tenant)
- `database/seeders/TenantDatabaseSeeder.php`: cria as permissões (`Permission::findOrCreate(.., 'web')`)
  e atribui aos papéis com `Role::findOrCreate` + `syncPermissions` (**idempotente**).
- Roda no `Tenant::create()` (job `SeedDatabase`) e, para tenants existentes, com
  **`php artisan tenants:seed`** (re-sync sem wipe; `config/tenancy.php` → `--class` aponta
  o `TenantDatabaseSeeder`).

## Mapa papel → permissão (resumo)
- **Dono:** TODAS (superset) — inclui `gerenciar_pagamentos` e `gerenciar_whatsapp`.
- **Gerente:** todas **menos** `ver_financeiro`, `editar_permissoes` e **`gerenciar_pagamentos`**
  (D39). Mantém `gerenciar_whatsapp`.
- **Recepção:** agenda, clientes, vendas, estoque, kanban de atendimento.
- **Profissional:** `ver_agenda_propria`, `finalizar_atendimento_proprio`.

## Credenciais de integração (D39)
- `gerenciar_pagamentos` → **só Dono**. `gerenciar_whatsapp` → **Dono + Gerente**.
- A tela de [[Integrações por Tenant (Credenciais)|Integrações]] usa essas permissões: card/rota
  de Mercado Pago por `gerenciar_pagamentos`; de WhatsApp por `gerenciar_whatsapp`.

## Gestão de equipe (`App\Livewire\Painel\Equipe\Index`)
- **Multi-papel:** propriedade `array $papeis` ligada a `flux:checkbox.group`; salva com
  `syncRoles($papeis)`. `editar()` carrega todos os papéis atuais (equipe existente não regride).
- **"É profissional (aparece na agenda)":** switch `e_profissional` — independente dos papéis;
  controla agenda/portal/comissão e a query de agendáveis (`where('e_profissional', true)`).
- **Travas de integridade:**
  - membro **não** pode ficar sem papel (validação `papeis: required|array|min:1`);
  - **não** se pode retirar o papel Dono nem inativar o **último Dono ativo** do tenant
    (bloqueado em `salvar()`/`inativar()` via `donosAtivosExceto()`).

## Agendável independe do papel (núcleo intocado)
`App\Services\Agendamento\MotorDisponibilidade` filtra profissionais por
`where('e_profissional', true)` — **não** pelo papel. Por isso "dono que também atende" já
funcionava e **não foi necessário** mexer no motor (zero regressão).

## Testes
- `tests/Feature/Painel/CredenciaisPermissaoTest.php`: matriz de acesso a credenciais
  (Dono pag+wpp 200; Gerente wpp 200 / pag 403 + índice sem card de pagamento; Recepção e
  Profissional puro 403; **Dono+Profissional** pag+wpp 200 **e** agendável).
- `tests/Feature/Painel/EquipeTest.php`: multi-papel salvo; exige ≥1 papel; `editar` carrega
  papéis atuais; **não remove/inativa o último Dono**; remove Dono quando há outro Dono.
