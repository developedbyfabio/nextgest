# Mapeamento Central x Tenant — auditoria pré-planos/onboarding/faturamento

> **Auditoria de leitura** (2026-06-25). Fotografia do estado atual para decidir, com fato na
> mão, onde criar as estruturas das próximas fases: **onboarding ampliado**, **plano + features
> com gating**, **faturamento da assinatura** e **suspensão por pagamento**. Nada foi alterado
> (nenhuma migration/seeder; só `SHOW`/`SELECT`). Sem Dxx novo — decisões virão nas fases de
> implementação. Ambiente: dev (`192.168.11.210`).

> **Atualização (D56–D60):** o central deixou de ter só `tenants`. Hoje tem **`estabelecimentos`**
> (1:1, cadastro/cobrança — D56, onboarding 7 etapas) e a **cobrança SaaS**: **`assinaturas`** +
> **`faturas`** (D58), tela de **Faturamento** (D59) e **suspensão por pagamento** (D60). Plano nomeado
> = D55. Veja [[Cadastro Central do Estabelecimento]] e [[Cobrança da Assinatura SaaS]].
>
> **Estados de bloqueio do tenant (dois, distintos):**
> 1. **Inativo** (`tenants.ativo=false`): `GarantirTenantAtivo` → **404 cego** em todo o tenant
>    (portal + painel). Bloqueio administrativo do super-admin.
> 2. **Suspenso por pagamento** (`Assinatura::situacaoAcesso()` = `suspensa`/`cancelada`):
>    `GarantirAssinaturaAtiva` → **só o painel** redireciona p/ tela amigável; **portal segue no ar**.
>    Ao vivo, reversível ao pagar.
>
> O que segue era o retrato ANTES dessas fases — mantido como histórico.

## TL;DR (o que decide as próximas fases)
- **Central é mínimo:** só `tenants` (+ `domains`, `tenant_user_impersonation_tokens`, `admins`,
  `failed_jobs`, `migrations`). **Não há tabela de plano, assinatura SaaS, cobrança ou
  faturamento do estabelecimento.** → tudo isso **não existe ainda**.
- **Já existe gating de feature por estabelecimento** (Fase 0a): enum `App\Enums\Recurso`
  (`clube`/`whatsapp`/`gateway`), flag no JSON `data.recursos` do tenant central, **middleware de
  rota `recurso:{slug}`** + **diretiva Blade `@recurso(...)`**. Default = tudo desligado. É o
  alicerce pronto para "plano libera features".
- **"Plano" hoje = só features à la carte** (toggles no Detalhe do tenant). **Não há** nível de
  plano nomeado (Básico/Pro), preço, ciclo nem vínculo plano→features.
- **Suspensão por pagamento não existe.** Existe só o liga/desliga `tenants.ativo` (booleano),
  aplicado pelo middleware `GarantirTenantAtivo` (→ 404 em todo o tenant). Não há um estado
  separado "suspenso por falta de pagamento".
- **CUIDADO com nomes:** o módulo "Clube/Pagamentos/Gateway/Assinatura" existente é a **assinatura
  do CLIENTE ao estabelecimento** (recorrência do salão), **não** a assinatura do estabelecimento
  ao Nextgest. São coisas diferentes; o faturamento do SaaS é um território novo (central).
- **Dono mora no banco do TENANT** (`users` + papel `Dono` via spatie), **não no central**.
- **Não há validação de CPF/CNPJ/celular** em lugar nenhum (telefone é `string` livre `max:30`).

---

## 1. Tenancy e central

### 1.1 Estrutura de `tenants` (`nextgest_central`)
`SHOW CREATE TABLE tenants`:
```sql
CREATE TABLE `tenants` (
  `id`         varchar(255) NOT NULL,          -- = slug (PK string, não-incremental)
  `nome`       varchar(255) NOT NULL,
  `slug`       varchar(255) NOT NULL,          -- UNIQUE
  `ativo`      tinyint(1)   NOT NULL DEFAULT 1,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL,
  `data`       json     DEFAULT NULL,          -- VirtualColumn do stancl
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenants_slug_unique` (`slug`)
)
```
- Colunas físicas ("custom columns" do stancl): `id`, `nome`, `slug`, `ativo`
  (`App\Models\Tenant::getCustomColumns()`). Migration:
  `database/migrations/2019_09_15_000010_create_tenants_table.php`.
- **Tudo o mais cai no JSON `data`.** Conteúdo real observado nos tenants demo:
  - `barbeariateste` → `{"recursos":["clube","whatsapp","gateway"],"segmento":"barbearia","tenancy_db_name":"tenant_barbeariateste",...}`
  - `salaoteste` → `{"recursos":[],"tenancy_db_name":"tenant_salaoteste",...}`
  - `volumeteste` → `{"tenancy_db_name":"tenant_volumeteste",...}` (sem `recursos`/`segmento` → ambos default off)
  - Chaves de `data` em uso: **`segmento`**, **`recursos`**, mais as do stancl (`tenancy_db_name`,
    `created_at`, `updated_at`).

### 1.2 Outras tabelas do central ligadas a estabelecimento/dono/plano/cobrança
`SHOW TABLES` (central): `admins`, `domains`, `failed_jobs`, `migrations`,
`tenant_user_impersonation_tokens`, `tenants`.
- `admins` → super-admins (guard `admin`); migration `2026_06_14_180632_create_admins_table.php`.
- `domains`, `tenant_user_impersonation_tokens` → do stancl (não usamos domínio; impersonação de
  suporte).
- **Plano/cobrança/faturamento/dono no central: NÃO EXISTE NENHUMA.**

### 1.3 Resolução do tenant e model
- **Por caminho** `/{tenant}` (slug) — `Stancl\Tenancy\Middleware\InitializeTenancyByPath`, grupo
  `tenant` (`routes/tenant.php`). Slug protegido por regex contra `reserved_slugs`
  (`config/nextgest.php`).
- Model: **`App\Models\Tenant`** (estende `Stancl…\Tenant`, implementa `TenantWithDatabase`;
  traits `HasDatabase`, `HasDomains`). PK string não-incremental (id = slug). Cast `ativo=boolean`.
- **Coluna `data` (VirtualColumn):** atributos virtuais `segmento` e `recursos`. Helpers do model:
  `recursosAtivos()` (normaliza contra o enum) e `temRecurso(slug)`. Banco do tenant:
  `tenant_{id}` (prefixo em `config/tenancy.php`).
- **Regra de ouro (já documentada no model):** ao salvar, **nunca** reatribuir `$tenant->data`
  inteiro — gravar só o atributo virtual (`$tenant->recursos = [...]; $tenant->save();`), senão
  apaga `segmento`.

---

## 2. Dono e onboarding

### 2.1 Campos coletados hoje + onde gravam
**Onboarding (wizard, 5 etapas)** — `App\Livewire\Admin\OnboardingEstabelecimento`:
| Etapa | Campos | Persistência |
|---|---|---|
| 1 Identidade | `nome`, `descricao`, `segmento`, `slug` | `nome`/`slug` → colunas de `tenants`; **`segmento`** → `data` (central); **`descricao`** → `configuracoes` do tenant |
| 2 Responsável (Dono) | `donoNome`, `donoEmail`, `donoSenha` | **`users` do TENANT** (`assignRole('Dono')`, `deve_trocar_senha=true`, `e_profissional=false`, `ativo=true`) |
| 3 Funcionamento | grade semanal seg–dom | `configuracoes['horario_funcionamento']` (JSON) do tenant |
| 4 Aparência | cores, fonte, tamanho, uploads logo/header/fundo | `Aparencia::salvar(...)` → `configuracoes` do tenant + arquivos no disco do tenant |
| 5 Revisão | — | `Tenant::create(...)` dispara CreateDatabase + Migrate + Seed; depois `$tenant->run(...)` grava Dono/aparência/descrição/horário |

- **O Dono é um `users` no banco do TENANT** (papel `Dono` via spatie). **Não há registro de dono
  no central.** Segmento é o único metadado de negócio que sobe ao central (`data.segmento`).
- **Não se coleta hoje:** nome fantasia/razão social, **CNPJ/CPF**, endereço, cidade/UF,
  **celular/telefone do dono**, dados de faturamento. → **não existe ainda.**

### 2.2 Componentes Livewire (caminhos exatos)
- Wizard: **`app/Livewire/Admin/OnboardingEstabelecimento.php`** (view
  `resources/views/livewire/admin/onboarding-estabelecimento.blade.php`), rota
  `admin.tenants.novo` (`/admin/estabelecimentos/novo`).
- "Criar dono" rápido (modal na lista) + "Criação rápida" (só nome+slug): **`app/Livewire/Admin/Tenants.php`**
  (`criarDono()` / `criar()`), view `resources/views/livewire/admin/tenants.blade.php`.
- Detalhe do tenant (toggles de recurso, impersonar, reset 2FA): **`app/Livewire/Admin/TenantDetalhe.php`**.

### 2.3 Onde moram os dados do estabelecimento
- **Nome do negócio:** `tenants.nome` (central). **Segmento:** `data.segmento` (central).
- **Descrição/horário/aparência:** tabela **`configuracoes`** do tenant (chave/valor).
- **Endereço/telefone:** existem **por UNIDADE** dentro do tenant — `unidades.endereco`,
  `unidades.telefone` (migration de core; model `App\Models\Unidade`). **Não há endereço/telefone
  no nível do estabelecimento no central**, nem CNPJ/CPF/faturamento em lugar nenhum.

### 2.4 Validação de CPF/CNPJ/celular reaproveitável
- **Não existe.** `grep` por cpf/cnpj/celular/telefone: só campos `telefone` como **string livre**
  (`['nullable'|'required','string','max:30'|'max:30']`) em Unidade, ClienteRegistrar,
  NovoAgendamento, WhatsappConfig. Sem regra `cpf`/`cnpj`, sem cast, sem helper, sem máscara
  validada. → se as próximas fases exigirem CPF/CNPJ/celular válidos, **é tudo novo**.

---

## 3. Menu, permissões e gating

### 3.1 Como a sidebar do tenant decide cada item
`resources/views/components/layouts/painel.blade.php`. Três grupos (`flux:sidebar.group`,
acordeão D47 — só um aberto). **Padrão: cada item é gated por permissão (`@can`/`@canany`),
nunca por papel.** Itens e condição:

| Grupo | Item | Rota | Condição de exibição |
|---|---|---|---|
| (topo) | Início | `painel.dashboard` | sempre |
| Operação | Agendamentos | `painel.agenda` | `@canany(ver_agenda, ver_agenda_propria)` |
| Operação | Últimos serviços | `painel.avaliacoes` | `@canany(ver_avaliacoes, ver_avaliacoes_proprias)` |
| Operação | Serviços | `painel.servicos` | `@can(editar_servico)` |
| Operação | Produtos | `painel.produtos` | `@canany(editar_produto, gerir_estoque)` |
| Operação | Comandas | `painel.vendas` | `@can(criar_venda)` |
| Operação | Bloqueios / Funcionamento | `painel.bloqueios`/`.funcionamento` | `@can(gerir_agenda)` |
| Operação | Kanban | `painel.kanban` | `@can(ver_kanban_atendimento)` |
| Gestão | Unidades | `painel.unidades` | `@can(gerir_unidades)` |
| Gestão | Equipe | `painel.equipe` | `@can(editar_usuario)` |
| Gestão | Comissões | `painel.comissoes` | `@can(ver_financeiro)` |
| Gestão | Indicadores | `painel.indicadores` | `@can(ver_indicadores)` |
| Gestão | **Clube de Assinatura** | `painel.clube` | **`@recurso('clube')` + `@can(gerenciar_clube)`** |
| Gestão | Papéis e permissões | `painel.papeis` | `@can(editar_permissoes)` |
| Gestão | Aparência | `painel.aparencia` | `@can(gerir_aparencia)` |
| Gestão | **Integrações** | `painel.integracoes` | `hasAnyPermission(Integracao::permissoes())` (gerenciar_pagamentos/gerenciar_whatsapp) |
| Financeiro | Visão financeira | `painel.financeiro` | `@can(ver_financeiro)` |

→ **O único item já com gating de FEATURE (não só permissão) é o Clube** (`@recurso('clube')`).
Integrações usa permissão; cada **editor** de integração é que tem o gating de feature por rota
(ver 3.3).

### 3.2 "Clube de assinatura" e "WhatsApp/Integrações" no menu
- **Clube:** existe → item "Clube de Assinatura", rota `painel.clube`
  (`App\Livewire\Painel\Clube\Index`), gated por `@recurso('clube')` + `@can(gerenciar_clube)`.
- **Integrações (inclui WhatsApp):** existe → item "Integrações", rota `painel.integracoes`
  (`App\Livewire\Painel\Integracoes\Index`), com editores `integracoes/mercadopago` e
  `integracoes/whatsapp`. **Não há item de menu "WhatsApp" isolado** — WhatsApp é um card dentro de
  Integrações.

### 3.3 Telas de clube/WhatsApp: rotas + middleware/policy
Em `routes/tenant.php`, todas sob `['auth:web', ForcarTrocaSenha]`:
- `painel.clube` → `middleware(['recurso:clube', 'can:gerenciar_clube'])`.
- `painel.integracoes` (índice) → sem middleware extra (filtra os cards por flag+permissão dentro
  do componente).
- `painel.integracoes.mercadopago` → `middleware(['can:gerenciar_pagamentos', 'recurso:gateway'])`.
- `painel.integracoes.whatsapp` → `middleware(['can:gerenciar_whatsapp', 'recurso:whatsapp'])`.

→ **Gating já é DUPLO: rota (middleware `recurso:` + `can:`) E menu (`@recurso` + `@can`).** É
exatamente o padrão que a fase de "plano libera features" deve seguir.

### 3.4 Permissões spatie atuais + papéis + seeder
`SELECT name FROM permissions` (tenant demo `tenant_barbeariateste`) — **30 permissões**, guard
`web`:
`criar_agendamento, criar_produto, criar_servico, criar_usuario, criar_venda, editar_agendamento,
editar_permissoes, editar_produto, editar_servico, editar_usuario, finalizar_atendimento_proprio,
gerenciar_2fa_proprio, gerenciar_clube, gerenciar_pagamentos, gerenciar_whatsapp, gerir_agenda,
gerir_aparencia, gerir_estoque, gerir_kanban, gerir_unidades, usar_kanban, ver_agenda,
ver_agenda_propria, ver_avaliacoes, ver_avaliacoes_proprias, ver_clientes, ver_dashboard,
ver_financeiro, ver_indicadores, ver_kanban_atendimento`.

**Papéis:** `Dono`, `Gerente`, `Profissional`, `Recepção` (guard `web`).

**Seeder:** `database/seeders/TenantDatabaseSeeder.php` (roda por tenant, pós-migration).
- **ADITIVO/IDEMPOTENTE (D53):** `Permission::findOrCreate` por permissão; `Role::findOrCreate`
  por papel; concede com **`givePermissionTo`** (= syncWithoutDetaching, **nunca revoga** extras
  do Dono); configs iniciais (`confirmacao_automatica`, `intervalo_slots_minutos`,
  `cancelamento_antecedencia_horas`) via **`insertOrIgnore`** (nunca sobrescreve). Kanban via
  `firstOrCreate`. Rodar 2× não muda nada. **Não reseta papéis/permissões.**
- A constante `PERMISSOES` do seeder bate 1:1 com as 30 do banco demo.

### 3.5 Padrão de gating confirmado
**Sim:** gating por **`can('permissao')`** / `@can` / `canAny` em rotas e views; **nunca
`hasRole`**. Feature por estabelecimento: `recurso:{slug}` (rota) + `@recurso('{slug}')` (view),
fonte única `App\Enums\Recurso` + `tenant_tem_recurso()`. (Exceções de papel só onde a regra é "só
Dono", via permissão dedicada, p.ex. `gerenciar_2fa_proprio`.)

---

## 4. Faturamento e suspensão (preparação)

### 4.1 Estrutura de assinatura/cobrança SaaS hoje
- **Do SaaS (estabelecimento → Nextgest): NÃO EXISTE.** Nenhuma tabela/model/coluna de plano,
  preço, ciclo, fatura, pagamento da mensalidade do salão, nem no central nem no tenant.
- **CUIDADO — o que existe é de OUTRO domínio (cliente → estabelecimento):** no banco do tenant há
  `planos_clube`, `assinaturas_clube`, `beneficiarios_assinatura`, `eventos_assinatura_clube`,
  `usos_clube`, `pagamentos`, `gateways_pagamento`, `webhooks_pagamento`, `cartoes_tokenizados`,
  `plano_beneficios`, `plano_descontos` (models homônimos). Isso é o **Clube de Assinatura**: o
  cliente final assina um plano recorrente do salão. **Não confundir com a mensalidade do SaaS.**
- No central há a rota-stub `POST /webhooks/pagamentos/{gateway}` (`webhooks.pagamentos`,
  `routes/web.php`) — hoje só devolve `{received:true}`; pensada para a futura fase de pagamentos
  (do Clube). Não é faturamento do SaaS.

### 4.2 Como funciona Inativar/Ativar hoje
- **Flag:** `tenants.ativo` (boolean, default `true`). Toggla em `App\Livewire\Admin\Tenants`
  (`inativar()`/`ativar()` → `Tenant::whereKey($id)->update(['ativo' => ...])`). Reversível, não
  apaga dado.
- **O que bloqueia:** **tudo do tenant** — middleware **`App\Http\Middleware\GarantirTenantAtivo`**
  (alias `tenant`, roda no grupo de tenant **após** `InitializeTenancyByPath`): se `ativo===false`
  → **`abort(404)`**. Atinge **portal (guard `cliente`) E painel (guard `web`), inclusive os
  logins** (`painel.login`, `cliente.login`). **Não atinge `/admin`** (central não passa pelo grupo
  tenant). Reativação só pelo super-admin.
- **Importante para a fase de suspensão:** hoje só há UM estado de bloqueio (`ativo=false` → 404
  cego). **Não há** um estado "suspenso por pagamento" distinto de "inativo administrativo". A fase
  de faturamento terá de introduzir esse novo estado (e provavelmente uma tela amigável "assinatura
  suspensa", em vez do 404 atual) sem confundir com o inativar manual.

### 4.3 Ponto de entrada do login do painel do tenant
- **`painel.login`** → `App\Livewire\Auth\PainelLogin` (`{tenant}/painel/login`), em
  `routes/tenant.php` sob `middleware('guest:web')`, dentro do grupo `['tenant']` (logo **já passa
  por `GarantirTenantAtivo`**). É o ponto natural para, no futuro, barrar/avisar um tenant
  "suspenso por pagamento" (de preferência via um novo middleware/estado, **sem** reusar o 404 do
  inativo). Logout: `painel.logout`. Portal do cliente: `cliente.login` (mesmo grupo/tratamento).

---

## Caminhos-chave (referência rápida)
- Central: `routes/web.php` · `app/Models/Tenant.php` · `database/migrations/2019_09_15_000010_create_tenants_table.php`
- Onboarding/admin: `app/Livewire/Admin/{OnboardingEstabelecimento,Tenants,TenantDetalhe,Dashboard}.php`
- Tenant routes/menu: `routes/tenant.php` · `resources/views/components/layouts/painel.blade.php`
- Feature flags: `app/Enums/Recurso.php` · `app/Enums/Integracao.php` ·
  `app/Http/Middleware/VerificaRecurso.php` · `@recurso` em `app/Providers/AppServiceProvider.php` ·
  helper `tenant_tem_recurso()`
- Ativo/suspensão: `app/Http/Middleware/GarantirTenantAtivo.php` (alias `tenant`)
- Permissões: `database/seeders/TenantDatabaseSeeder.php`
- Migrations de tenant: `database/migrations/tenant/` (core, permission, clube, vendas/pagamentos,
  kanban/whatsapp, avaliacoes, …)

## Lacunas para as próximas fases (tudo "não existe ainda")
1. **Onboarding ampliado:** campos de dono (celular) e de estabelecimento (nome fantasia/razão,
   CNPJ/CPF, endereço/cidade/UF, contato de faturamento) — definir se moram no **central** (metadado
   de negócio/cobrança, consultável sem entrar no tenant) ou no tenant. Hoje: só `nome`, `slug`,
   `segmento` no central. Sem validador de CPF/CNPJ/celular (criar).
2. **Plano + features:** existe o mecanismo de feature à la carte (`Recurso` + `recurso:` +
   `@recurso`), mas **não** o conceito de **plano nomeado** (Básico/Pro…), preço, ciclo, nem o
   vínculo **plano → conjunto de recursos**. Decidir se plano vira coluna em `data`/tabela central.
   O gating de rota+menu já está pronto para "plano liga/desliga recurso".
3. **Faturamento da assinatura (SaaS):** zero estrutura no central. Domínio totalmente novo (não
   reusar o Clube, que é cliente→salão). Provável: tabelas centrais de assinatura/fatura/pagamento
   por tenant + integração de cobrança.
4. **Suspensão por pagamento:** hoje só `ativo` → 404 cego (`GarantirTenantAtivo`). Falta um estado
   "suspenso por inadimplência" separado do "inativo manual", com tela amigável e ponto de bloqueio
   no login do painel/portal.
