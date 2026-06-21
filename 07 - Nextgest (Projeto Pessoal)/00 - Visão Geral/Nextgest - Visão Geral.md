---
projeto: Nextgest
tipo: visão-geral
status: fatia-1-completa + evolução-visual (etapas 1–5)
criado: 2026-06-14
atualizado: 2026-06-21
tags: [nextgest, saas, agendamento]
---

# Nextgest — Visão Geral

> [!note] Estado atual (22/06/2026)
> - **Suíte verde: 213 testes** (Pest) + testes de fumaça HTTP.
> - **Polimento concluído:** **agenda** (Polimento 1) e **cadastros** (Polimento 2 —
>   unidades/serviços/equipe/horários/papéis/bloqueios) elevados ao padrão de ponta
>   (tokens, estados, `flux:modal` sem confirm nativo, busca onde cabe). **Todas as
>   telas internas agora no nível do resto.** Regras/permissões intactas.
> - **Fatia 2 completa:** 2A produtos/estoque, 2B venda/comanda, 2C comissões, 2D
>   dashboard com faturamento REAL. **Pagamentos etapa 1 (presencial)** entregue:
>   fechar comanda registra pagamento(s) (dinheiro/cartão/pix/maquininha, dividido),
>   estorno ao cancelar. Ver [[Produtos e Estoque]], [[Vendas e Comanda]],
>   [[Comissões]], [[Dashboard do Dono]] e [[Pagamentos (Presencial)]].
> - **Finalizar atendimento → comanda** (✅ 2026-06-22): o profissional, na própria
>   agenda, finaliza o atendimento (1 clique: conclui + gera a comanda, idempotente) com
>   **cliente e profissional travados**; nas avulsas há campo **"quem vendeu"** que
>   pré-preenche os itens. Permissão `finalizar_atendimento_proprio` + `VendaPolicy`
>   (profissional só os próprios). Ver [[Vendas e Comanda]].
> - Evolução visual A/B/C (portal, painel+dashboard, kanban) + **Etapa D: modo
>   claro/escuro/sistema** (Flux) no painel e no portal.
> - **Modelo de tema (D36, substitui parte de A/B):** marca do tenant = **acento +
>   logo + tipografia** (constante nos dois modos); **superfícies = tokens de
>   claro/escuro**. Ver [[Decisões de Arquitetura]] D36 e
>   [[Identidade Visual do Estabelecimento (Tema)]].
> - **Próxima frente:** **Pagamentos etapa 2** (gateway Mercado Pago — exige direção do
>   Fabio: conta/credenciais/VPS) e/ou **Clube de assinatura**. Pendentes menores:
>   desconto por item; rótulos amigáveis de permissão na tela de papéis.
> - **Migrou de servidor:** saiu da VM VirtualBox (`192.168.3.100`, Ubuntu 24.04,
>   PHP 8.4) para o **servidor de dev compartilhado `192.168.11.210`** (Ubuntu 22.04,
>   PHP 8.5.7), com repositório no **GitHub**.
> - **Drivers em dev:** cache/sessão/fila = `file`/`file`/`sync` (a tenancy troca a
>   conexão padrão para o banco do tenant; `database` quebraria — ver
>   [[Decisões de Arquitetura]] D32 e [[Bug - Drivers database dentro do tenant]]).
> - **Pronto:** Fatia 1 (agendamento ponta a ponta) + evolução visual etapas 1–5
>   (tema por tenant, edição de aparência + prévia, uploads, onboarding guiado,
>   dashboard do dono, kanban).
> - **Falta:** etapa 6 (polimento do tema no painel/auth) e a Fatia 2 em diante
>   (produtos/vendas, clube, pagamentos, WhatsApp).

## O que é
SaaS de agendamento multi-tenant para negócios que atendem por horário marcado
(barbearias, salões, profissionais autônomos e afins). Cada cliente que compra o
sistema (um estabelecimento) é um **tenant** isolado, acessado por
`nextgest.com.br/{slug}` (ex.: `/barbeariadojorge`).

## Objetivo
Vender o software para diversos estabelecimentos, cada um com dados isolados,
oferecendo agenda, autoagendamento pelo cliente final, venda de produtos e
serviços, clube de assinatura, dashboard de gestão, kanban e automações de
WhatsApp.

## Stack
- Servidor de dev atual: **`192.168.11.210`** (Ubuntu 22.04, PHP **8.5.7**),
  compartilhado com outros projetos. Repositório no GitHub.
- Backend: Laravel **13.15**, PHP 8.5
- Frontend: Livewire **4.3** + **Flux 2.14**, Tailwind v4 (Vite), Alpine.js
- Banco: MySQL (central `nextgest_central` + `tenant_{slug}` por estabelecimento)
- Multi-tenancy: `stancl/tenancy` **3.10** (banco por tenant, por caminho)
- Permissões: `spatie/laravel-permission` **8.0**
- Node 22; charts via Chart.js; drag-and-drop via SortableJS

> [!info] Versões verificadas no servidor novo (21/06/2026)
> Laravel 13.15.0 · Livewire 4.3 · Flux 2.14 · stancl/tenancy 3.10 ·
> spatie/laravel-permission 8.0 · Node 22.22.3 · PHP 8.5.7. Nenhum downgrade.

## Status do build (14/06/2026)

Scaffold concluído no servidor (11/11 fases). Versões reais: Ubuntu 24.04.4,
PHP 8.4.22, Laravel 13.15, stancl/tenancy 3.10, spatie/laravel-permission 8.0,
Livewire 4.3, MySQL 8.0.46, Nginx 1.24, Redis 7.0.15, Node 22, Tailwind v4.
Nenhum pacote precisou de downgrade. Central migrado (tenants, domains, admins);
tenant com 41 tabelas + spatie; guards web/cliente/admin; seed de papéis e
`confirmacao_automatica=1`; gateway plugável (MercadoPago stub). Bug do stancl
(`tenant_0`) corrigido — ver [[Bug - Tenant id 0 (tenant_0)]].

Pendências: dropar banco órfão `tenant_0`.

**Ambiente atual:** servidor de dev compartilhado `192.168.11.210` (Ubuntu 22.04,
PHP 8.5), **sem DNS/SSL**. (Antes: VM VirtualBox `192.168.3.100`.) DNS, SSL/Nginx de
produção e credenciais reais do Mercado Pago ficam para a **migração ao VPS** (quando
o sistema estiver pronto). Em dev, cache/sessão/fila usam `file`/`file`/`sync`; no VPS
o ideal é voltar a Redis — ver [[Decisões de Arquitetura]] D32.

**Sub-fatia 1A concluída** (autenticação + layout): 3 guards (web/cliente/admin),
Flux, portal mobile-first, throttle, isolamento de sessão por tenant, 20 testes
Pest. Comandos `nextgest:criar-admin` e `nextgest:criar-dono`. Ver
[[Prompt Dev 1A - Autenticacao e Layout]].

**Sub-fatia 1B concluída** (cadastros do dono): CRUDs de unidades, serviços,
equipe e horários de trabalho no painel, autorização reconferida no servidor,
"excluir" = inativar, gestão de papéis/permissões com anti-lockout do Dono.
42 testes Pest (1A+1B). Ver [[Prompt Dev 1B - Cadastros do Dono]].

**Sub-fatia 1C concluída** (portal de agendamento): wizard mobile-first, motor de
disponibilidade (`MotorDisponibilidade`), bloqueios, e prevenção de duplicidade
com lock pessimista no profissional (`Agendador`). 65 testes Pest. Ver
[[Prompt Dev 1C - Portal de Agendamento]] e [[Gotchas e Aprendizados do Projeto]].

**Sub-fatia 1D concluída** (agenda da equipe): visões dia/semana com filtros,
detalhe em slide-over, agendamento manual (`origem=equipe`), transições de
status e remarcação — tudo reusando `MotorDisponibilidade`/`Agendador`. Profissional
vê só a própria agenda (escopo no servidor). 74 testes Pest. Ver
[[Prompt Dev 1D - Agenda da Equipe]].

**Sub-fatia 1E concluída** (polimento de UI): design system com tokens em
`resources/css/app.css` (accent indigo), dark mode via Flux, componentes `ng`
(page-header, option-card, empty, skeleton) + componentes Flux; auth split,
wizard com progresso, agenda polida, navegação SPA. 74 testes verdes. Ver
[[Padrao de UI-UX (Design System)]].

**Correções/adições pós-1E:** bug de CSS nas rotas de tenant resolvido
(`asset_helper_tenancy=false`); painel `/admin/estabelecimentos` para criar/listar
tenants e criar Dono (versão mínima da Fatia 8); **bug crítico do Livewire
`/livewire/update` 404 resolvido** (rota custom com `{tenant?}` no início não casava
no central) — ver [[Bug - Livewire update 404 global]]. Login dos 4 acessos
funciona, testes de fumaça HTTP adicionados. **91 testes.** Sistema navegável.

**Evolução visual — etapas 1 a 5 concluídas** (a 6 é polimento, ainda pendente):

- **Etapa 1** — portal do cliente corrigido (texto invisível: dark do sistema sobre
  superfície clara) + fundação de tema por tenant via CSS vars
  ([[Identidade Visual do Estabelecimento (Tema)]]). (93 testes na época.)
- **Etapa 2** — templates de tema (7 presets em `Aparencia::TEMPLATES`, D30), tela de
  edição de aparência do dono (`painel.aparencia`, permissão `gerir_aparencia`) e
  componente de **prévia ao vivo** reutilizável `x-ng.previa-portal`. **Uploads** de
  logo/cabeçalho/fundo por tenant, servidos por rota própria `/{tenant}/arquivo/{path}`
  (o `tenant_asset()` do stancl resolve por domínio, incompatível com path) — ver
  [[Identidade Visual do Estabelecimento (Tema)]].
- **Etapa 3** — onboarding guiado do estabelecimento (D29): wizard de 5 etapas em
  `App\Livewire\Admin\OnboardingEstabelecimento` (`/admin/estabelecimentos/novo`).
  Segmento sugere o template; ao confirmar, provisiona o banco, cria o Dono, aplica a
  aparência e semeia o horário. Segmento → coluna JSON `data` do tenant central;
  descrição/horário → `configuracoes` do tenant. Ver [[Onboarding Guiado de Estabelecimento]].
- **Etapa 4** — dashboard do dono (D31): `App\Livewire\Painel\Dashboard` +
  `App\Services\Dashboard\Metricas` (permissão `ver_dashboard`). Filtros de período e
  unidade; gráficos com Chart.js (cores do tema). **Faturamento ESTIMADO** por
  `agendamento_servico` de agendamentos concluídos (ainda não há módulo de Vendas).
  Ver [[Dashboard do Dono]].
- **Etapa 5** — kanban (D22): `App\Livewire\Painel\Kanban\Index` (`painel.kanban`),
  quadros Atendimento e CRM semeados no tenant; colunas/cartões editáveis; DnD via
  SortableJS + menu "Mover para" acessível. Permissões `ver_kanban_atendimento`
  (inclui Recepção) e `gerir_kanban`. Ver [[Kanban (Atendimento e CRM)]].
- **Etapa 6 (em andamento)** — polimento, em sub-etapas:
  - **Etapa A** (✅ 2026-06-21): portal do cliente elevado (tema, modais,
    micro-interações, estados).
  - **Etapa B** (✅ 2026-06-21): shell do painel + telas de auth refletindo a
    identidade completa do tenant (dark-safe via `.dark` automático por luminância da
    superfície) e **dashboard** elevado (KPIs/gráficos em superfície da marca,
    Chart.js nas cores da marca, estados loading/vazio/erro).
  - **Etapa C** (✅ 2026-06-21): kanban (Atendimento e CRM) — superfícies da marca,
    DnD com handle/placeholder/elevação e **revert em falha**, modais (sem confirm
    nativo), "excluir" = arquivar (soft delete), estados e responsivo com snap. Ver
    [[Kanban (Atendimento e CRM)]].
  - **Etapa D** (✅ 2026-06-21): **modo claro / escuro / sistema** (Flux) no painel e
    no portal. Reformula o tema (D36): marca = acento + logo + tipografia; superfícies
    = tokens de claro/escuro. Seletor no header do portal e no menu de perfil do
    painel. **Substitui** o que A/B faziam de pintar superfície pela marca / forçar
    `.dark` por luminância.
  - Pendente: polimento temático fino das telas internas (agenda/cadastros).
  Ver [[Auditoria de UI (Portal e Painel)]].
  - **Auditoria da Aparência** (✅ 2026-06-21): a tela `painel.aparencia` foi alinhada
    ao D36 — só **Principal/Secundária** (superfícies seguem claro/escuro; as 4 cores de
    superfície e os controles de **layout** mortos foram removidos), **tipografia real**
    (16 fontes, Google Fonts sob demanda) e **uploads** corrigidos (limite 2 MB coerente
    com o PHP; `mimes:png,jpg,jpeg,webp`). Mesmo alinhamento no onboarding. Ver
    [[Bug - Aparencia (upload, fonte e campos desconectados)]].
  - **Prévia = portal real** (✅ 2026-06-21): a prévia renderiza os **mesmos componentes**
    do portal (`x-portal.cabecalho/capa/como-funciona/servico`) — fim da divergência:
    **imagem de cabeçalho e fundo agora aparecem no portal real**. Prévia em **carrossel**
    das 4 telas do cliente (Início/Login/Cliente/Agendar), moldura de celular, alternador
    claro/escuro, template aceso e Salvar com reload. Ver
    [[Identidade Visual do Estabelecimento (Tema)]].
  - **Fundo no portal real + legibilidade + carrossel completo** (✅ 2026-06-21): o
    **fundo agora aparece no portal real** (a coluna vira translúcida com `.ng-com-fundo`,
    um scrim fosco sobre a foto); blocos ofuscados ganham `.ng-leitura` (legível nos 2
    modos, com/sem fundo); o carrossel desliza corretamente as **4 telas** (era `100/total`
    → só 2) e a tela 1 usa o **mesmo** `x-portal.tela-inicio` da home real.

**Migração de servidor (junho/2026):** clone novo em `192.168.11.210` exigiu ajustes
de ambiente (drivers `file`/`sync`, IP nos `central_domains`) e reparo de um tenant
meio-migrado — ver [[Bug - Drivers database dentro do tenant]] e
[[Bug - Tenant meio-migrado (ledger)]].

Depois da etapa 6, segue a **Fatia 2** (produtos/vendas) e demais módulos.

## Estilo de trabalho

Neste projeto o Claude do servidor executa o desenvolvimento com o máximo padrão
de qualidade; sem modo tutorial. Trabalho em sub-fatias pequenas (um prompt e um
commit testável por vez) por segurança de engenharia.

## Arquitetura macro
- **Banco central:** tenants, domains, planos do SaaS (depois), super-admin.
- **Banco por tenant:** todo o operacional do estabelecimento.
- **Dois logins (guards):** equipe interna (`users`) e cliente final (`clientes`).

## Módulos planejados
1. Agendamento (em modelagem) — ver [[Modelo de Dados - Núcleo de Agendamento]]
2. Produtos e vendas
3. Clube de assinatura
4. Pagamentos (gateway plugável, tokenização)
5. Kanban
6. Automações de WhatsApp (API oficial)

## Links
- Decisões: [[Decisões de Arquitetura]]
- Modelo de dados: [[Modelo de Dados - Núcleo de Agendamento]]

## Dúvidas em aberto
- Forma de cobrança do SaaS (o quanto os estabelecimentos pagam) — adiado.
- Detalhes de cada módulo além do agendamento — a definir nos próximos blocos.
