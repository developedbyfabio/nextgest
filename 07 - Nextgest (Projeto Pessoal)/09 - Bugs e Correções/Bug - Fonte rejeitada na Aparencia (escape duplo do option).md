---
projeto: Nextgest
tipo: bug-e-correcao
modulo: aparencia-tema
status: resolvido
criado: 2026-06-21
tags: [nextgest, bug, aparencia, tema, flux, blade, escape, tipografia]
---

# Bug — fonte rejeitada na Aparência ("campo fonte não contém valor válido")

> Projeto: [[Nextgest - Visão Geral]] · Resolvido · ver
> [[Identidade Visual do Estabelecimento (Tema)]] e
> [[Bug - Aparencia (upload, fonte e campos desconectados)]]

## Sintoma (navegador, reportado pelo Fabio)
Em `painel.aparencia`, escolher uma fonte (ex.: **Open Sans**) dava **"O campo fonte não
contém um valor válido"**, **não salvava** e **não refletia na prévia**. O **tamanho base**
"também não aplicava" (colateral: a validação do `salvar()` falhava na fonte e abortava o
salvamento inteiro, levando o tamanho junto).

## Causa raiz — escape DUPLO do `value` do `<option>`
O catálogo `Aparencia::FONTES` usa como **chave** a stack de `font-family`, que para as
fontes não-sistema contém **aspas simples** (ex.: `'Open Sans', ui-sans-serif, sans-serif`).
A view passava o valor assim:

```blade
{{-- ERRADO --}}
<flux:select.option value="{{ $valor }}" style="font-family: {{ $valor }};">
```

`flux:select.option` recebe `value` como **prop** e renderiza `value="{{ $value }}"` (escapa).
Então o valor era escapado **duas vezes**:
1. o `{{ $valor }}` da view escapava `'` → `&#039;` (agora a string tem `&`);
2. o `{{ $value }}` do componente Flux escapava o `&` → `&amp;`.

Resultado renderizado: `value="&amp;#039;Open Sans&amp;#039;, …"`. O navegador envia
`&#039;Open Sans&#039;, …` (literal), que **não** é igual à chave `'Open Sans', …` →
`Rule::in(array_keys(FONTES))` **rejeita**.

Fontes **sem aspas** (ex.: `ui-sans-serif, system-ui, sans-serif`) não tinham o que escapar
e funcionavam — por isso só **Inter/Poppins/Open Sans/…** quebravam. O teste antigo passava
porque fazia `->set('fonte', $chave)` (injeta a chave direto, **sem** passar pelo `<option>`)
— clássico "teste verde × navegador".

## Correção — passar o valor *bound* (escape único)
```blade
{{-- CERTO --}}
<flux:select.option :value="$valor" :style="'font-family: ' . $valor">
```

Com `:value` (binding), o componente recebe a string **crua** (`'Open Sans', …`) e o Flux
escapa **uma** vez → `value="&#039;Open Sans&#039;, …"` → o navegador decodifica para
`'Open Sans', …` = a chave exata → `Rule::in` aceita. Idem para o `:style` da prévia da
fonte na própria opção. Aplicado na tela de aparência **e** no onboarding.

## Verificação
- **Cadeia real (render):** o `<option>` agora sai `value="&#039;Poppins&#039;, …"`
  (escape único); decodificado = a chave do catálogo.
- **Testes** (`tests/Feature/Painel/AparenciaFonteUploadTest.php`):
  - **toda** chave de `FONTES` passa na validação, salva e aplica (`cssVarsAcento`);
  - **todo** tamanho base (14–18px) passa e salva;
  - **cada `<option>` renderizado**, decodificado, é uma chave válida do catálogo e o nº de
    opções == nº de fontes (pega o escape duplo diretamente);
  - fonte fora do catálogo é rejeitada.
  Suíte: **232 verde**.

## Lição
- Em componente Blade/Flux que recebe `value` como **prop** e o re-renderiza, **NÃO**
  pré-escape com `{{ }}` na atribuição — use binding (`:value="$expr"`) para escapar só uma
  vez. Pré-escapar + o escape do componente = escape duplo (o valor enviado deixa de bater).
- Testar o **valor que a tela oferece** (o `<option>` renderizado), não só
  `->set(prop, valorBom)`.
