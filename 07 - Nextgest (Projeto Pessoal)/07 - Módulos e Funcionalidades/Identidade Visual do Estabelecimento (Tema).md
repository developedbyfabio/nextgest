# Identidade Visual do Estabelecimento (Tema)

> Projeto: [[Nextgest - Visão Geral]] · Decisões: [[Decisões de Arquitetura]]
> (D28 tema, D30 templates, D35 arquivos, **D36 modo claro/escuro**, D90 favicon,
> D91 botão secundário, D92 marca do portal) · Atualizado: 2026-07-01.

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
  `gerir_aparencia`) para escolher template, ajustar a marca e enviar
  logo/cabeçalho/fundo, com **prévia ao vivo** ao lado.
- **Campos da tela (alinhados ao D36 — auditoria de 2026-06-21):**
  - **Cores da marca:** só **Principal (acento)** e **Secundária (realces)**. As 4
    cores de **superfície** (fundo/superfície/texto/texto-suave) foram **removidas**
    da tela — não controlavam o app real (as superfícies seguem claro/escuro), eram
    "campos que mentiam". A prévia tem um **alternador claro/escuro próprio** (ver
    abaixo) para o dono ver os dois modos.
  - **Tipografia:** **fonte** (catálogo de 16 — `Aparencia::FONTES`, sistema +
    Google Fonts: Inter, Poppins, Montserrat, Roboto, Open Sans, Lato, Nunito,
    Raleway, Work Sans, Space Grotesk, Playfair Display, Merriweather, Georgia,
    JetBrains Mono) e **tamanho base** (14–18px). A **fonte** vai no `<body>`
    (`cssVarsAcento`); o **tamanho base** vai no **`<html>`** (font-size) — os
    utilitários do Tailwind são `rem`, então só no `<body>` não escalava (bug
    corrigido). Aplicam no portal e no painel.
  - **Imagens:** logo, cabeçalho, fundo — `image|mimes:png,jpg,jpeg,webp|max:2048`
    (**2 MB**), gravadas no disco `public` do tenant e referenciadas por
    `urlArquivo()`. A prévia só usa `temporaryUrl()` se o arquivo for previewável
    (não quebra ao escolher um não-imagem antes da validação).
  - **Layout (posição do menu / estilo de ícone):** **removidos** — eram ganchos no
    JSON sem nenhum consumo na UI (controles mortos). Idem no onboarding.
- **Carregamento das fontes Google (sob demanda):** `Aparencia::linkFonteGoogle()`
  emite o `<link>` da fonte escolhida no `<head>` dos layouts (portal/painel/auth);
  `linksFontesGoogle()` carrega **todas** só nas telas de edição/onboarding, para a
  prévia ao vivo refletir qualquer seleção antes de salvar. Lista fechada (sem
  entrada do usuário → sem injeção; a `href` ainda é escapada).

## Prévia = portal REAL (componentes compartilhados) + carrossel (2026-06-21)
Antes a prévia era uma **maquete genérica** divergente do portal (por isso a imagem de
cabeçalho/fundo "não apareciam"). Agora a prévia renderiza os **mesmos componentes** do
portal do cliente — **fonte de verdade única**.

- **Componentes Blade compartilhados** (`resources/views/components/portal/`):
  - `x-portal.cabecalho` — barra (logo + nome + ações via slot). Usado pelo **layout do
    portal real** e pela prévia.
  - `x-portal.capa` — hero/capa que mostra a **imagem de cabeçalho** como banner (véu na
    cor da marca p/ legibilidade). Usado pela **home do visitante (portal real)** e pela
    prévia. É o que faz a imagem de cabeçalho **aparecer de verdade** no portal.
  - `x-portal.como-funciona` — passos. `x-portal.servico` — linha de serviço.
  - `x-portal.tela-inicio` — composição da tela do visitante (capa + como-funciona +
    chamadas). **A home real do visitante e a tela 1 do carrossel usam ESTE mesmo
    componente** (sem markup paralelo). As duas chamadas: **"Criar conta e agendar"**
    (primária, cor principal) e **"Já tenho conta"** (secundária, **botão sólido na cor
    secundária** — D91): reusa o botão primário do Flux com `--color-accent` sobrescrito
    localmente p/ `--cor-secundaria` + `--cor-sobre-secundaria` (mesma altura/raio/estados;
    sólido dispensa a `.ng-leitura` sobre a foto).
  - O **fundo** (`fundo_imagem`) entra no `<body>` pelo layout do portal (e na moldura da
    prévia). Imagens servidas por `Aparencia::urlArquivo()` (rota por caminho, anti
    path-traversal).
  - > [!warning] Diretiva Blade dentro de tag de componente Flux quebra a compilação
    > `<flux:heading @if(...) style=... @endif>` dá `ParseError`. Em `x-portal.capa` o
    > estilo condicional é resolvido numa variável PHP e o título usa `<div>` simples.

### Fundo no PORTAL REAL + legibilidade (camada de leitura) — 2026-06-21
- **Causa do "fundo só na prévia":** o `<body>` do portal recebia o `background-image` do
  fundo, mas a **coluna de conteúdo** (`max-w-md`) tinha `background-color:
  var(--cor-superficie)` **sólido** — no mobile (coluna = largura total) ela cobria a foto.
- **Correção (mesma mecânica no portal real e na prévia):** quando há fundo, marca-se o
  container com **`.ng-com-fundo`** — um **scrim fosco** (`--cor-superficie` a ~82% + blur)
  **sobre a foto**: a foto aparece (~18%) atrás de tudo e o texto fica legível. Sem fundo,
  a coluna é a superfície sólida de sempre. No portal real o scrim vai na **coluna**; na
  prévia, em **cada tela** (sobre a foto da moldura).
- **`.ng-leitura`:** blocos que sobre a foto ficariam ofuscados (passos do "como funciona",
  botão fantasma "Já tenho conta") ganham uma superfície de leitura **só quando há fundo**
  (`--ng-leitura` definido por `.ng-com-fundo`); sem fundo ficam transparentes (visual
  limpo). Vale nos dois modos (a cor vem de `--cor-superficie`, token de claro/escuro).
  > [!note] Camada: `background-image` pinta SOBRE `background-color`
  > Por isso o scrim **não** pode ser `background-color` no mesmo elemento da foto (a foto
  > o cobriria). No portal real a foto está no `<body>` e o scrim na coluna; na prévia a
  > foto está na moldura e o scrim em cada tela (elementos distintos, scrim sobre a foto).

- **Carrossel das telas do cliente** (estilo Instagram, Alpine, somente leitura): 4 telas
  — **Início** (deslogado/capa, **mesmo `x-portal.tela-inicio` da home real**), **Login**,
  **Cliente** (home logado) e **Agendar** (fluxo de serviços) — com setas + dots, dentro de
  uma **moldura de celular** (mais alta). Dados de exemplo; reusa os componentes acima. Não
  toca no `Agendador`.
  > [!warning] Carrossel: deslocar 100% por tela (não `100/total`)
  > Cada tela é `w-full shrink-0`; o `translateX` do trilho desloca **`-tela * 100%`** (a %
  > é relativa à largura do trilho = 1 moldura). Usar `100/total` (25%) deslocava só ¼ →
  > sintoma "só 2 telas aparecem". (Bug corrigido.)
- **Alternador claro/escuro PRÓPRIO:** toggle Alpine aplica `is-dark` **só na moldura** da
  prévia (todas as telas). Superfícies por `.ng-previa` / `.ng-previa.is-dark` (no
  `app.css`), **independentes** do modo do painel. Acento/secundária/tipografia inline por
  `cssVarsAcento()`. Badge/CTA usam `--cor-sobre-principal` (contraste em acento claro).
- **Live preview:** a prévia é re-renderizada pelo componente `Editar` a cada
  `wire:model.live` (valores ainda **não salvos**); o estado do carrossel/toggle (Alpine)
  sobrevive ao morph (root com `wire:key`). Por isso usamos reuso de componentes Blade, não
  um iframe do portal real (que não mostraria valores não-salvos).
- **Template aceso:** o template selecionado fica destacado (anel na cor de acento +
  marca de selecionado); `Editar::$template` guarda a escolha, persiste em `aparencia` e é
  refletido ao reabrir.
- **Salvar recarrega:** após persistir, `Editar::salvar()` faz `redirect(..., navigate:
  false)` (reload completo) para o novo tema aplicar no próprio painel; a mensagem de
  sucesso aparece após o reload (flash → `Flux::toast` no `mount`).

## Favicon por tenant (D90)
Cada tenant tem seu **favicon** (ícone da aba do navegador), na MESMA tela de
Aparência (junto de logo/cabeçalho/fundo). Ao contrário das outras imagens (guardadas
CRUAS), o favicon é **processado no upload**.

- **Campo:** `favicon` no JSON `configuracoes.aparencia` (aditivo no `PADRAO`, **sem
  migration**). `null` → fallback pro padrão do Nextgest.
- **Processamento (`App\Support\Favicon`):** decodifica a imagem enviada e gera um
  **PNG 32×32** "contain" (proporção preservada, centralizado em fundo transparente),
  via **GD** — extensão já disponível no servidor (sem pacote novo/download no build).
  Grava no MESMO disco/pasta do logo (`storage/tenant{id}/app/public/aparencia`) com
  **nome único** por upload.
- **Injeção no `<head>` (`Aparencia::linkFavicon()`):** emite
  `<link rel="icon" type="image/png">` no MESMO ponto onde a tipografia/acento já
  entram, nos layouts **portal**, **painel** e **auth do tenant** (`portal-auth` e
  `auth` quando há tenant). Fora do tenant (ou tenant sem favicon), cai no padrão
  `asset('nextgest-logo.png')` (o mesmo de landing/admin) — o head nunca quebra.
- **Cache-busting:** herdado do nome único → **URL nova a cada troca** → o navegador
  (que cacheia favicon agressivamente) reflete a mudança. Servido por
  `TenantArquivoController` com `Cache-Control: immutable` (seguro porque a URL muda).
- **Validação:** mesma das outras imagens (`image|mimes:png,jpg,jpeg,webp|max:5120`).
  Falha de processamento vira erro de validação no campo (não 500) e aborta o salvar.
- **UI:** campo "Favicon (ícone da aba)" na seção Imagens, com preview e a nota de que
  é reduzido para 32×32/PNG automaticamente.
- **Não** injetado em `landing`/`admin` (centrais, não-tenant): seguem o padrão fixo.

## Marca do portal — ícone ou logo (D92)
O dono escolhe, na Aparência, a **fonte do brand-mark do portal**: um **ícone
predefinido** (catálogo curado) OU o **logo enviado** (respeitando transparência).

- **Persistência (aditiva, sem migração):** `marca_tipo` (`icone|logo`) e `marca_icone`
  (chave do ícone) no JSON `configuracoes.aparencia` (ganchos no `PADRAO`). Default
  `icone`/`scissors` — mantém a tesoura de hoje para quem não configurar.
- **Catálogo `Aparencia::ICONES_MARCA`** (chave Flux/Heroicons → rótulo pt-BR, 12):
  `scissors` (Tesoura), `sparkles` (Brilho), `star` (Estrela), `heart` (Coração),
  `fire` (Chama), `building-storefront` (Loja), `calendar-days` (Calendário),
  `swatch` (Paleta), `paint-brush` (Pincel), `face-smile` (Sorriso), `gift` (Presente),
  `sun` (Sol). Lista fechada → `Rule::in(array_keys(...))` no salvar; `Aparencia::marcaIcone()`
  valida no render e cai em `scissors` se vier algo fora do catálogo (o `flux:icon`
  nunca recebe nome inválido, que abortaria).
- **PARTIAL ÚNICO `x-portal.marca`** (novo): renderiza a marca conforme a escolha e é
  usado no **hero** (`x-portal.capa`, `contexto="hero"` — quadrado de acento com o ícone,
  ou o logo `object-contain`) e no **topo** (`x-portal.cabecalho`, `contexto="topo"` —
  ícone tingido ou logo). Como capa/cabecalho são compartilhados portal↔prévia, a
  **prévia da Aparência reflete a escolha** sem markup paralelo.
  - > [!warning] Não nomear a prop de contexto como `variant`
    > O `<flux:icon>` aninhado tem `@props(['variant' => 'outline'])`; uma prop `variant`
    > no `x-portal.marca` VAZA para o ícone e um `hero`/`topo` estoura o `match` do Flux
    > (`UnhandledMatchError`). Por isso a prop chama-se **`contexto`**.
- **Logo respeita transparência:** no modo `logo`, a imagem entra **sem** quadrado de cor
  atrás (que anularia a transparência). **Fallback:** `logo` sem imagem enviada cai no
  ícone padrão. Constante em claro/escuro; sólido/legível com ou sem imagem de fundo.
- **UI (Aparência):** segmented control **Ícone | Logo** + **seletor visual** (grade de
  ícones renderizados, com prévia) quando `icone`; quando `logo`, aponta para o upload de
  logo (na seção Imagens) e avisa que pode ser transparente. Reusa o **upload de logo
  existente** (disco do tenant, `urlArquivo`) — sem novo caminho de upload.

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
  `--cor-secundaria`, `--cor-sobre-principal`, **`--cor-sobre-secundaria`**,
  `--color-accent*` e a **tipografia** (`font-family`/`font-size`). Constante nos dois
  modos. `--cor-sobre-secundaria` = contraste WCAG (`corDeContraste`) sobre a secundária,
  para texto legível em botões sólidos na cor secundária (D91).
- As **superfícies** (`--cor-fundo`, `--cor-superficie`, `--cor-texto`,
  `--cor-texto-suave`) são **tokens** definidos em `app.css` (`:root` claro / `.dark`
  escuro). Como as views e as classes `.ng-*` já usavam essas vars, elas seguem o
  **modo** automaticamente. Superfície e texto trocam **juntos** (sem texto invisível).
- O modo (claro/escuro/sistema) é do **Flux** (`@fluxAppearance` + `.dark`), aplicado
  no cliente. Ver [[Decisões de Arquitetura]] D36.
- **Seletor de tema** (`x-ng.seletor-tema` no portal; menu de perfil no painel/admin):
  liga o `x-model` **direto** em `$flux.appearance` (`x-data x-model="$flux.appearance"`).
  Mutar esse objeto reativo dispara o `Alpine.effect` do Flux que aplica `.dark` e
  persiste no `localStorage`. **Nunca** usar cópia local (`x-data="{ appearance: … }"`),
  que torna o seletor inerte — ver
  [[Bug - Seletor de tema nao alterna (x-model local)]].

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
- **`menu_posicao`/`icone_estilo`: resolvido (2026-06-21).** Nunca tiveram consumo na
  UI — eram controles mortos. **Removidos** da tela de aparência e do onboarding (e
  dos componentes Livewire). As chaves seguem no PADRÃO/JSON com valor default
  (`topo`/`outline`), inertes, até que (se um dia) o painel suporte de fato menu
  lateral / ícones sólidos. Não reintroduzir o controle sem o efeito.
- Possível melhoria: override de modo claro/escuro por usuário da equipe (hoje o
  painel segue a marca do tenant).
- Possível melhoria: permitir imagens > 2 MB em produção (hoje limitado pelo
  `upload_max_filesize=2M` do PHP — ver o bug abaixo).

## Relacionado
- Templates de aparência (presets): [[Decisões de Arquitetura]] (D30) — ✅ feito.
- Onboarding com prévia ao vivo (D29): [[Onboarding Guiado de Estabelecimento]] —
  ✅ feito (reusa a prévia da edição de tema).
- Arquivos por tenant: `Aparencia::urlArquivo()` / D35 (NÃO `tenant_asset()`) — ver
  [[Gotchas e Aprendizados do Projeto]].
