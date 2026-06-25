---
projeto: Nextgest
tipo: módulo
status: implementado
criado: 2026-06-21
tags: [nextgest, onboarding, tenant, admin, tema]
---

# Onboarding Guiado de Estabelecimento

> Projeto: [[Nextgest - Visão Geral]] · Decisão: [[Decisões de Arquitetura]] (D29) ·
> Etapa 3 da evolução visual. Reusa [[Identidade Visual do Estabelecimento (Tema)]].

## O que é
Criar um estabelecimento (tenant) deixou de ser "nome + slug" e virou um **wizard**
no super-admin, com **prévia ao vivo** do portal do cliente ao lado.

- Componente: `App\Livewire\Admin\OnboardingEstabelecimento`.
- Rota: `GET /admin/estabelecimentos/novo` (slug `novo` é reservado em
  `config/nextgest.php`).

## Etapas do wizard (6)
1. **Identidade do negócio** — nome, descrição, **segmento** e slug.
2. **Responsável (Dono)** — nome, e-mail, senha (mín. 8).
3. **Horário de funcionamento** — por dia da semana (aberto/fechado, início/fim). Usa o
   **mesmo** editor da tela do painel ([[Funcionamento e Excecoes (horario)]]).
4. **Aparência** — template + ajuste de cores/tipografia + **logo, imagem de cabeçalho e
   imagem de fundo** (PNG/JPG/WebP, até 5 MB), com prévia. As 3 imagens seguem o mesmo
   tratamento da aba de Aparência (store no disco do tenant, `urlArquivo`) e aparecem no
   portal (capa no hero, fundo no `<body>`). Ver [[Identidade Visual do Estabelecimento (Tema)]].
5. **Plano** (D55) — escolhe Básico/Profissional/Nextgest (cards lendo `config/planos.php`);
   **seleção obrigatória**. Define os **recursos** liberados. Ver [[Planos (catálogo e aplicação)]].
6. **Revisão** — mostra plano + recursos inclusos; confirma e provisiona.

## Segmentos e sugestão de template
`barbearia`, `salao_feminino`, `salao_masculino`, `estetica`, `outro`. O segmento
**sugere** um template de [[Identidade Visual do Estabelecimento (Tema)]] (D30) sem
travar a escolha do operador:

| Segmento | Template sugerido |
|---|---|
| barbearia | barbearia |
| salao_feminino | salao_feminino |
| salao_masculino | salao_masculino |
| estetica | premium |
| outro | neutro |

## O que acontece ao confirmar
- **Provisiona o tenant** (dispara CreateDatabase → MigrateDatabase → SeedDatabase).
- **Aplica o plano** escolhido (`Tenant::aplicarPlano()`) → grava `plano` + `recursos` no `data`
  central (preserva o `segmento`). Ver [[Planos (catálogo e aplicação)]].
- **Cria o Dono** (guard `web`, papel Dono) com a **senha inicial** (hasheada) e
  **`deve_trocar_senha = true`** → no 1º login é forçado a definir uma senha própria. Ver
  [[Senhas (1o login e self-service)]].
- **Aplica a aparência** escolhida (`configuracoes.aparencia`).
- **Semeia o horário** de funcionamento.

## Onde cada dado é gravado
- **Segmento** e **plano**/`recursos` → coluna JSON `data` da tabela `tenants` (banco **central**) —
  metadados consultáveis no `/admin` sem inicializar o tenant (D33, D55).
- **Descrição** e **horário de funcionamento** → `configuracoes` do **tenant** (chaves
  `descricao` e `horario_funcionamento`, D34).
- **Aparência** → `configuracoes.aparencia` do tenant (D28).

## Relacionado
- [[Identidade Visual do Estabelecimento (Tema)]] (templates + prévia `x-ng.previa-portal`)
- [[Decisões de Arquitetura]] (D29, D30, D33, D34)
