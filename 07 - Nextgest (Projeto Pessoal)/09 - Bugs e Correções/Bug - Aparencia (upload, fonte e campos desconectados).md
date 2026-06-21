---
projeto: Nextgest
tipo: bug-e-correcao
modulo: aparencia-tema
status: resolvido
criado: 2026-06-21
tags: [nextgest, bug, aparencia, tema, upload, livewire, d36, tipografia]
---

# Bug — Aparência: upload quebrado, fonte/tamanho "sem efeito" e campos que mentem

> Projeto: [[Nextgest - Visão Geral]] · Resolvido · ver
> [[Identidade Visual do Estabelecimento (Tema)]] e [[Decisões de Arquitetura]] (D36)

## Sintomas (reportados pelo Fabio no navegador)
Na tela `painel.aparencia`, só **cores** e **template** funcionavam. **Não**
funcionavam: **tipografia (fonte)**, **tamanho base**, **upload de imagens** (com erro
visível **"Falha no upload do arquivo headerUpload"**) e **layout** (posição do menu,
estilo de ícone).

## Auditoria (campo a campo)
| Campo | Estado antes | Causa |
|---|---|---|
| Cor Principal / Secundária | **Funciona** (acento real) | — |
| Cor Fundo/Superfície/Texto/Texto-suave | **Na tela, mas desconectado** | D36 fez as superfícies seguirem claro/escuro; esses campos só afetavam a prévia. Campos que **mentem**. |
| Fonte | Ligada, mas "parecia não mudar" | As 4 fontes ofertadas eram quase idênticas (sistema/Instrument). Aplicava via `cssVarsAcento` no `<body>`, mas sem variedade perceptível. |
| Tamanho base | Igual à fonte | Aplicava (font-size no `<body>`), pouco perceptível no painel (Flux fixa muitos tamanhos). |
| Uploads (logo/cabeçalho/fundo) | **Quebrado** | Ver causa-raiz abaixo. |
| Layout (menu_posicao / icone_estilo) | **Na tela, mas desconectado** | Salvavam no JSON, **nenhum** código consumia → controles mortos. |

> [!warning] Correção posterior (2026-06-21): a causa real do 500 era outra
> O 500 que persistiu **não** era o limite de 2 MB do PHP (hipótese abaixo,
> incompleta). A causa real é o **disco temporário do Livewire suffixado por tenant**
> (gravado central no upload, lido no disco do tenant no `/update`). Ver
> [[Bug - Upload 500 (disco temp do Livewire x tenancy)]]. O limite de 2 MB segue
> válido como restrição de tamanho, mas não era o motivo do erro 500.

## Causa-raiz do upload ("Falha no upload do arquivo headerUpload")
O PHP do servidor tem **`upload_max_filesize = 2M`** (e `post_max_size = 8M`), mas a
tela **prometia 4 MB** para cabeçalho e fundo. Arquivos acima de 2 MB são barrados pelo
**próprio PHP** antes de chegar ao Livewire → o endpoint de upload temporário falha e o
front exibe "Falha no upload do arquivo …". O `php artisan serve` (CLI) usa o mesmo
limite de 2 MB. A lógica de gravação em si (`->store('aparencia','public')` no disco do
tenant) estava **correta** — um teste de upload em contexto de tenant passou.
Adicional: a regra `image` aceitava **SVG** (que pode carregar script).

## Correção (alinhada ao D36 — não só "consertar", mas alinhar a tela ao modelo)
- **Cores:** mantidas só **Principal (acento)** e **Secundária (realces)**. As 4 cores
  de **superfície** foram **removidas** da tela (e do onboarding) — não mentir.
- **Tipografia:** catálogo `Aparencia::FONTES` com **16 fontes** (sistema + Google
  Fonts). A fonte Google escolhida é carregada **sob demanda** por
  `Aparencia::linkFonteGoogle()` no `<head>` dos layouts (portal/painel/auth); o editor
  carrega **todas** (`linksFontesGoogle()`) para a prévia ao vivo. `fonte` validada por
  `Rule::in(array_keys(FONTES))`. Tamanho base inalterado (já aplicava).
- **Uploads:** limite alinhado a **2 MB** (`max:2048`) para caber no `upload_max_filesize`
  padrão do PHP — assim funciona sem mexer em config de sistema. Tipos restritos a
  `mimes:png,jpg,jpeg,webp` (sem SVG). `accept` do input idem. A prévia só chama
  `temporaryUrl()` se o arquivo for **previewável** (escolher um não-imagem não quebra a
  tela). Mensagens de erro amigáveis.
- **Layout (menu/ícone):** **removidos** da tela e do onboarding (controles mortos). As
  chaves seguem no PADRÃO/JSON, inertes.
- **Prévia fiel:** força superfícies para o **padrão claro** (o app real usa tokens de
  claro/escuro, não cores de superfície do tenant) + acento + fonte + imagens.

> [!note] Produção: para aceitar imagens > 2 MB
> Aumentar `upload_max_filesize`/`post_max_size` no **php-fpm** da VPS (e, se usar
> Nginx, `client_max_body_size`). Em dev, dá para servir com
> `php -d upload_max_filesize=8M -d post_max_size=16M artisan serve`.

## Verificação
- **Testes (efeito, não existência):** `tests/Feature/Painel/AparenciaFonteUploadTest.php`
  — salva fonte/tamanho e confere no `cssVarsAcento`; rejeita fonte fora do catálogo;
  `linkFonteGoogle()` emite o `<link>` da fonte Google e **vazio** para fonte do sistema;
  upload válido grava no disco do tenant e passa a ser referenciado; upload de tipo
  inválido (PDF) e acima de 2 MB são rejeitados; a tela **não** expõe cor de superfície
  nem controles de layout. Suíte: **225 verde**.
- **Navegador / cadeia real:** servido em porta alta, com o tenant de demo usando
  **Poppins / 17px / acento `#9333ea`** — o `<head>` do portal traz o `<link>` da
  Poppins, o `<body>` traz `font-family: 'Poppins'…` e `font-size: 17px` (escapados pelo
  Blade; o navegador decodifica), e **não** há `--cor-fundo` inline (superfícies via
  tokens). Independe de tema → vale claro e escuro.

## Lição
- **Não deixar controle que mente.** Após D36, manter as 6 cores e os controles de
  layout dava a impressão de configurar o app, mas não mudava nada. Alinhar a tela ao
  modelo é parte da correção.
- Upload "falhando" pode ser **limite do PHP** (`upload_max_filesize`/`post_max_size`),
  não o código — a regra `max:` da app precisa caber no limite do servidor.
