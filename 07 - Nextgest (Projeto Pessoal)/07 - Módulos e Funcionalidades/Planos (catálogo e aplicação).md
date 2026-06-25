---
projeto: Nextgest
tipo: módulo
status: implementado
criado: 2026-06-25
tags: [nextgest, planos, recursos, tenant, admin]
---

# Planos (catálogo e aplicação)

> Projeto: [[Nextgest - Visão Geral]] · Decisão: [[Decisões de Arquitetura]] (**D55**) ·
> Camada de NOME por cima das feature flags ([[Recursos por Tenant (Feature Flags)]], D37).

## O que é
Um **plano** é só um NOME que liga um conjunto de **recursos** (módulos à la carte) de uma vez.
Não há migração/tabela/seeder/permissão nova: reusa o `data` central do tenant e o gating já
existente (`recurso:{slug}` + `@recurso(...)`).

## Catálogo (fonte única)
`config/planos.php` — chave = slug persistido no tenant.

| Plano | slug | `preco_mes`* | recursos |
|---|---|---|---|
| Básico | `basico` | 49,90 | `[]` |
| Profissional | `profissional` | 99,90 | `clube`, `gateway` |
| Nextgest | `nextgest` | 199,90 | `clube`, `gateway`, `whatsapp` |

\* `preco_mes` é **referência interna do admin** (orientar a troca). A **landing segue
independente** por ora — unificação de preço é fase posterior; isto **não** é fonte única de preço.

## Onde mora / como aplica
- **Atributo virtual `plano`** no `App\Models\Tenant` (JSON `data` central, junto de
  `segmento`/`recursos`). `planoAtual()` normaliza → `null` se não definido ou fora do catálogo.
- **`Tenant::aplicarPlano($chave)`**: valida a chave no catálogo, seta `plano` + redefine
  `recursos` para o padrão do plano e salva. **Só atributos virtuais** (regra de ouro do `data`:
  nunca reatribuir `$this->data` inteiro → `segmento` sobrevive). Chave fora do catálogo →
  `InvalidArgumentException`.
- Como o gating lê `recursos` **ao vivo**, trocar o plano reflete no painel do tenant na hora
  (menu via `@recurso`, rotas via `recurso:`).

## Onboarding (wizard de 6 etapas)
Identidade → Responsável → Funcionamento → Aparência → **Plano** → Revisão.
- Nova etapa **Plano** (cards lendo o catálogo); **seleção obrigatória** (sem default silencioso).
- **Revisão** mostra o plano e os recursos inclusos.
- Ao confirmar, `aplicarPlano()` é chamado no tenant recém-criado (preserva o `segmento`).
- Componente: `App\Livewire\Admin\OnboardingEstabelecimento` (`regrasEtapa(5)` = `plano` obrigatório).

## Troca de plano (Detalhe do tenant)
`App\Livewire\Admin\TenantDetalhe` (`/admin/estabelecimentos/{tenantId}`):
- Seção **Plano** (cards Básico/Profissional/Nextgest) + botão **"Aplicar plano"** (com
  `wire:confirm`). `trocarPlano()` recarrega o tenant completo, reaplica recursos e re-sincroniza
  os toggles.
- Os `flux:switch` viraram **"Ajuste fino de recursos"** (independentes do plano), com aviso:
  **trocar o plano redefine os recursos para o padrão do plano**.
- **Tenant sem plano** (os atuais): mostra "Plano: **não definido** (recursos personalizados)";
  nada é mutado automaticamente — só ao aplicar.

## Rebaixar plano
Ex.: Nextgest→Básico **esconde o acesso** aos módulos retirados; os **dados** no banco do tenant
(ex.: clube) **permanecem**. A UI da troca avisa isso explicitamente.

## Testes
`tests/Feature/Admin/PlanoTenantTest.php`:
- `aplicarPlano` seta plano+recursos e **preserva o segmento**; chave inválida **lança**;
  `planoAtual` normaliza (null p/ lixo); **rebaixar esconde** os recursos.
- Onboarding: não avança da etapa Plano sem selecionar; revisão mostra plano + recursos.
- Detalhe: troca re-sincroniza os toggles e preserva segmento; tenant **sem plano** mostra "não
  definido" e **não muta**.
- **Gating real por HTTP** autenticado por tenant: plano **Nextgest** libera `/{tenant}/painel/clube`
  (200); plano **Básico** → **404** (middleware `recurso:clube`).

## Limites / pendências
- **Dev apenas — sem deploy.** Em produção, o tenant real precisará ter o `plano` definido
  manualmente (com backup), fora desta fatia.
- **Não é faturamento.** Plano aqui = recursos liberados; cobrança/assinatura do SaaS e
  **suspensão por pagamento** são fases posteriores (ver
  [[Mapeamento Central x Tenant (auditoria pré-planos)]]).
