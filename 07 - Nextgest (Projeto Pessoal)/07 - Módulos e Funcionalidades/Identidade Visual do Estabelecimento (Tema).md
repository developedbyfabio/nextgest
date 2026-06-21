# Identidade Visual do Estabelecimento (Tema)

> Projeto: [[Nextgest - Visão Geral]] · Decisões: [[Decisões de Arquitetura]]
> (D28 tema, D30 templates, D35 arquivos) · Etapas 1–2 da evolução visual.
> Atualizado: 2026-06-21.

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

## Como é aplicado
O layout do portal injeta, num `style` do `<body>`:
`--cor-principal`, `--cor-secundaria`, `--cor-fundo`, `--cor-superficie`,
`--cor-texto`, `--cor-texto-suave`, `--cor-sobre-principal` e o `--color-accent` do
Flux (faz botões primários/foco usarem a cor da marca). Nada de CSS por tenant
compilado. No **painel**, aplica-se só o acento (`cssVarsAcento()`).

## Defaults
Todo tenant nasce com tema finalizado mesmo sem registro salvo; o `nextgest:demo`
grava o tema padrão como base editável.

## Componentes compartilhados temáticos (portal)
Os componentes `x-ng.option-card` e `x-ng.empty` têm a prop **`themed`**:
- **com `themed`** (portal): seguem a identidade do tenant via CSS vars
  (`--cor-superficie`/`--cor-texto`/`--cor-principal`/`--color-accent`) — classe
  CSS `.ng-card-portal` (e `.ng-skeleton-portal` para skeleton). Funciona inclusive
  com **superfície custom escura**.
- **sem `themed`** (painel/admin): superfícies neutras do Flux (zinc/dark), como antes.

> [!warning] Não use as classes neutras (`.ng-card-interactive`, `bg-white`/`zinc`) no portal
> Elas têm cor fixa e variantes `dark:` que **não disparam** no portal (ele não
> aplica `.dark`). No portal, sempre passar `themed`. Ver
> [[Auditoria de UI (Portal e Painel)]].

## Portal do cliente elevado (2026-06-21)
UI do portal levada ao nível "de ponta": cartões/empties temáticos, transições
suaves entre passos do wizard (`.ng-fade-in`), grade de horários e resumo com a cor
da marca, e **cancelamento por `flux:modal`** (substitui o `wire:confirm` nativo).
Ver [[Auditoria de UI (Portal e Painel)]] e [[Padrao de UI-UX (Design System)]].

## Pontos em aberto / próximos (Etapa 6 — polimento)
- Aplicar a identidade também no **painel** e nas telas de **auth** do tenant (hoje o
  acento já entra no painel via `cssVarsAcento()`; falta o polimento amplo).
- Login/registrar ainda usam o layout "split" da marca Nextgest, não o tema do
  estabelecimento.
- **A confirmar:** quanto dos ganchos `menu_posicao`/`icone_estilo` já têm efeito
  visual real (estão nos presets/JSON, mas o consumo na UI pode ser parcial).

## Relacionado
- Templates de aparência (presets): [[Decisões de Arquitetura]] (D30) — ✅ feito.
- Onboarding com prévia ao vivo (D29): [[Onboarding Guiado de Estabelecimento]] —
  ✅ feito (reusa a prévia da edição de tema).
- Arquivos por tenant: `Aparencia::urlArquivo()` / D35 (NÃO `tenant_asset()`) — ver
  [[Gotchas e Aprendizados do Projeto]].
