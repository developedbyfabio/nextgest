# Identidade Visual do Estabelecimento (Tema)

> Projeto: [[Nextgest - Visão Geral]] · Decisões: [[Decisões de Arquitetura]]
> (D28 tema, D30 templates, D35 arquivos, **D36 modo claro/escuro**) ·
> Atualizado: 2026-06-21.

> [!important] Modelo ATUAL (Etapa D, D36) — substitui parte das Etapas A/B
> - **Marca do tenant = ACENTO** (`--cor-principal` / `--color-accent`) **+ logo +
>   tipografia**, constante nos dois modos (emitido inline por
>   `Aparencia::cssVarsAcento()`).
> - **Superfícies** (fundo/superfície/texto/divisores) = **tokens de claro/escuro**
>   (`resources/css/app.css` `:root` e `.dark`), controlados pelo **modo
>   claro/escuro/sistema** do Flux (`@fluxAppearance`).
> - **Revogado:** pintar o fundo com a cor da marca e forçar `.dark` por luminância
>   (era o modelo A/B). As seções abaixo que falam em "superfície da marca" valem
>   agora **só para a prévia do editor**.

## Ideia
Cada estabelecimento (tenant) tem sua própria aparência (cores, fonte, logo,
imagens…). Em vez de CSS compilado por tenant (não escala), a aparência é um
conjunto de valores aplicados em runtime como **CSS custom properties**. Assim:
templates são presets desses valores e a prévia ao vivo só reescreve as variáveis.

## Onde fica guardado
- Banco **do tenant**, na tabela `configuracoes`, sob a chave **`aparencia`**
  (JSON). Escolhido em vez de tabela nova: já existe, é chave/valor flexível e
  acomoda ganchos futuros (logo, imagens, posição de menu, estilo de ícone) sem
  migration.

## Serviço `App\Support\Aparencia`
- `PADRAO` — tema padrão (indigo, fundo claro, texto escuro, Instrument Sans).
- `TEMPLATES` — **7 presets** em código (D30): `neutro`, `barbearia`,
  `salao_feminino`, `salao_masculino`, `premium`, `moderno`, `minimalista`.
- `template($chave)` — campos visuais de um preset (sem rótulo/descrição), prontos
  para o form.
- `doTenant()` — lê o `configuracoes.aparencia` e **mescla com os defaults**.
- `salvar()` — persiste a aparência do tenant (merge sobre o atual).
- `cssVars()` — **todas** as custom properties (portal/auth do tenant, onde a
  identidade domina a tela).
- `cssVarsAcento()` — **só o acento** da marca (painel: mantém superfícies neutras do
  Flux e usa a cor só como realce, pela legibilidade da ferramenta de gestão).
- `corDeContraste()`/`luminancia()` — cor de frente legível (preto/branco) sobre a
  cor principal, por luminância WCAG (texto em botões primários etc.).
- `urlArquivo($path)` — URL pública de arquivo do tenant (logo/cabeçalho/fundo) pela
  rota própria `tenant.arquivo` (D35).

## Templates (presets, D30)
Cada template é só um conjunto dos campos da D28 (cores, fonte, posição de menu,
estilo de ícone). Aplicar um template = copiar os valores para o tenant; **segue
100% editável** depois. Usados na **edição de tema** e no **onboarding** (o segmento
sugere um template de partida).

## Edição de tema do dono (`painel.aparencia`)
- `App\Livewire\Painel\Aparencia\Editar` — formulário do dono (permissão
  `gerir_aparencia`) para escolher template, ajustar cores/fonte e enviar
  logo/cabeçalho/fundo, com **prévia ao vivo** ao lado.

## Prévia ao vivo (`x-ng.previa-portal`)
- Componente reutilizável que renderiza um recorte do portal do cliente aplicando as
  CSS vars correntes — como a identidade é só variáveis, a prévia reage em tempo real
  às escolhas do form. Reutilizado pela edição e pelo onboarding
  ([[Onboarding Guiado de Estabelecimento]]).

## Uploads por tenant (Etapa 2, D35)
- Logo/cabeçalho/fundo gravados no disco `public` isolado por tenant
  (`storage/tenant{id}/app/public`) e servidos por **rota própria**
  `GET /{tenant}/arquivo/{path}` (`tenant.arquivo`,
  `App\Http\Controllers\TenantArquivoController`, com anti path-traversal).
- > [!warning] Não use `tenant_asset()` aqui
  > O helper/rota de assets do stancl identifica o tenant por **domínio**;
  > este projeto é por **caminho**. Para imagens do tenant use
  > `Aparencia::urlArquivo($path)`.

## Como é aplicado (modelo atual — Etapa D)
- Os layouts (portal, painel, auth) injetam no `<body>`, via
  **`Aparencia::cssVarsAcento()`**, só a **marca**: `--cor-principal`,
  `--cor-secundaria`, `--cor-sobre-principal`, `--color-accent*` e a **tipografia**
  (`font-family`/`font-size`). Constante nos dois modos.
- As **superfícies** (`--cor-fundo`, `--cor-superficie`, `--cor-texto`,
  `--cor-texto-suave`) são **tokens** definidos em `app.css` (`:root` claro / `.dark`
  escuro). Como as views e as classes `.ng-*` já usavam essas vars, elas seguem o
  **modo** automaticamente. Superfície e texto trocam **juntos** (sem texto invisível).
- O modo (claro/escuro/sistema) é do **Flux** (`@fluxAppearance` + `.dark`), aplicado
  no cliente. Ver [[Decisões de Arquitetura]] D36.

> [!info] `cssVars()` (superfícies completas) → só na PRÉVIA do editor
> `cssVars()` ainda emite fundo/superfície/texto a partir do preset; é usado apenas
> no `x-ng.previa-portal` (mostra um recorte em modo claro). O app de verdade usa
> `cssVarsAcento()` + tokens.

## Defaults
Todo tenant nasce com tema finalizado mesmo sem registro salvo; o `nextgest:demo`
grava o tema padrão como base editável.

## Componentes compartilhados (`.ng-*`)
`.ng-surface`, `.ng-surface-muted`, `.ng-divider`, `.ng-card-portal`,
`.ng-skeleton-portal` etc. usam as vars de superfície (`--cor-superficie`/
`--cor-texto`…) que **agora são tokens de claro/escuro** — portanto seguem o modo
sem alteração. `x-ng.option-card`/`x-ng.empty` mantêm a prop `themed` (no portal usam
`.ng-card-portal`/empty temático; no painel/admin, as neutras do Flux).

## Histórico da evolução visual (Etapas A–C)
- **Etapa A** (portal), **B** (painel + dashboard), **C** (kanban): elevaram a UI e
  introduziram as classes `.ng-*`.
- **Mudança da Etapa D:** o que essas etapas faziam de "pintar superfície com a cor da
  marca" e "forçar `.dark` por luminância" foi **substituído** pelo modo
  claro/escuro/sistema (D36). As classes `.ng-*` permaneceram, agora derivando dos
  tokens de modo. `Aparencia::superficieEscura()` foi **removido**.

## Pontos em aberto / próximos (Etapa 6 — polimento)
- **Telas internas do painel** (agenda, cadastros) e o **kanban** ainda usam
  superfícies do Flux; herdam o `.dark` correto, mas o polimento temático fino fica
  para a Etapa C (kanban) e seguintes.
- **A confirmar:** quanto dos ganchos `menu_posicao`/`icone_estilo` já têm efeito
  visual real (estão nos presets/JSON, mas o consumo na UI pode ser parcial).
- Possível melhoria: override de modo claro/escuro por usuário da equipe (hoje o
  painel segue a marca do tenant).

## Relacionado
- Templates de aparência (presets): [[Decisões de Arquitetura]] (D30) — ✅ feito.
- Onboarding com prévia ao vivo (D29): [[Onboarding Guiado de Estabelecimento]] —
  ✅ feito (reusa a prévia da edição de tema).
- Arquivos por tenant: `Aparencia::urlArquivo()` / D35 (NÃO `tenant_asset()`) — ver
  [[Gotchas e Aprendizados do Projeto]].
