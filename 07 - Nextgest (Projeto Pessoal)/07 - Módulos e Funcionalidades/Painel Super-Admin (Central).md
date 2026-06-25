# Painel Super-Admin (Central)

> Painel do **super-admin** do SaaS (guard `admin`, rotas `/admin/*`, domínio central —
> NÃO passa por tenancy). Gerencia os estabelecimentos (tenants). Ver decisões
> [[Decisões de Arquitetura]] (D24 auth por guard, **D54 identidade visual**).

## Telas
- **Início / dashboard** (`App\Livewire\Admin\Dashboard` → `livewire/admin/dashboard.blade.php`):
  card com o total de **Estabelecimentos** + banner "Em construção" (planos/cobrança do SaaS chegam
  depois).
- **Estabelecimentos** (`App\Livewire\Admin\Tenants` → `livewire/admin/tenants.blade.php`): tabela de
  tenants (nome/slug/status/criado) + ações **Editar** (→ `admin.tenant.detalhe`), **Abrir** (portal
  do tenant em nova aba), **Criar dono** (modal), **Inativar/Ativar**, busca, "Criação rápida" e
  "Novo estabelecimento" (wizard de onboarding — ver [[Onboarding Guiado de Estabelecimento]]).
- **Detalhe/Editar do tenant** (`App\Livewire\Admin\TenantDetalhe`): resumo de alto nível,
  impersonação de suporte, reset de 2FA do Dono, **Plano** (Básico/Profissional/Nextgest — D55) e
  **Ajuste fino de recursos** (switches). Ver [[Planos (catálogo e aplicação)]] e
  [[Recursos por Tenant (Feature Flags)]].
- **Onboarding** (`App\Livewire\Admin\OnboardingEstabelecimento`): wizard de **7 etapas** (Identidade →
  Responsável → **Estabelecimento** → Funcionamento → Aparência → Plano → Revisão; D55/D56). Grava o
  cadastro central (`estabelecimentos`). Ver [[Onboarding Guiado de Estabelecimento]] e
  [[Cadastro Central do Estabelecimento]].

## Identidade visual (Fase 0 — D54)
Alinhada à **landing** (mesma fonte de verdade; ver [[Landing (Site Institucional)]]):
- **Layout** `resources/views/components/layouts/admin.blade.php`: header **glassmorphism**
  (`bg-white/80 backdrop-blur-md dark:bg-[#0B1120]/80`), **logo** `public/nextgest-logo.png` +
  wordmark "Nextgest" + **pill "ADMIN"** em degradê de marca; fundo claro/`#0B1120` no escuro.
- **Dark/light:** mecanismo padrão (`@fluxAppearance` + `$flux.appearance`, persistido pelo Flux);
  toggle sol/lua reusando **`x-landing.tema-toggle`** no header (+ radio no dropdown de perfil).
- **Paleta:** classes Tailwind de marca (`from-violet-600 via-indigo-600 to-blue-600` + slate). Não
  emite `--cor-*` (central, sem tema de tenant).

## Limites (o que NÃO é deste painel)
- Não mexe em `MotorDisponibilidade`, agenda, portal nem no painel do tenant (guard `web`/`cliente`).
- **Plano nomeado** (recursos liberados) já existe (D55). Pendente (próximas fatias):
  **faturamento/cobrança** da assinatura do SaaS e **suspensão por pagamento** (estado distinto do
  "inativo" atual) — ver [[Mapeamento Central x Tenant (auditoria pré-planos)]].
