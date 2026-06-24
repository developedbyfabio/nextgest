@props([
    'nome' => '',
    'logoUrl' => null,
    'fundoUrl' => null,
    'titulo' => 'Entrar',
    'subtitulo' => null,
    'tagline' => 'Agende em poucos toques e acompanhe tudo num só lugar.',
])

{{--
    Shell de autenticação do PORTAL do cliente. FONTE DE VERDADE única: usado pelo
    login/registro REAIS (guard `cliente`) e pela PRÉVIA da Aparência — sem maquete
    divergente. O corpo do formulário entra pelo slot (campos reais nas telas reais,
    estáticos na prévia).

    Responsivo por CSS via CONTAINER QUERY (não user-agent): o layout reflui pela
    LARGURA do próprio shell (@container), então funciona igual na página real (o
    container é a viewport) e na prévia (o container é a moldura) — mudar a largura
    simula o breakpoint. Larguras grandes (>=48rem / @3xl) → 2 colunas (painel de
    marca + formulário); estreito → coluna única.

    Tema/superfícies via CSS vars (--cor-*) + .ng-com-fundo, NUNCA utilitários
    `dark:` — a prévia tem alternador claro/escuro PRÓPRIO (.ng-previa.is-dark), que
    não casa com a classe .dark do Flux. O fundo (D36) aparece como painel de marca
    (desktop) e como plano de fundo do formulário (mobile).
--}}
@php
    $estiloRaiz = $fundoUrl
        ? "background-color: var(--cor-fundo); background-image: url('{$fundoUrl}'); background-size: cover; background-position: center;"
        : 'background-color: var(--cor-fundo);';
@endphp

<div {{ $attributes->class('@container relative isolate flex flex-col') }} style="{{ $estiloRaiz }}">
    {{-- grid-rows-1 (1fr) faz a única linha preencher a altura toda do shell (flex-1),
         então as colunas ocupam 100% da altura e o fundo do root não vaza embaixo. --}}
    <div class="grid flex-1 grid-rows-1 @3xl:grid-cols-2">

        {{-- Painel de marca — só nas larguras grandes (2 colunas). Com fundo, a foto
             é o painel (com leve tinta da marca p/ o texto contrastar); sem fundo, a
             cor principal da marca. --}}
        <div class="relative hidden flex-col justify-between overflow-hidden p-10 @3xl:flex"
            @style([
                'background-color: var(--cor-principal); color: var(--cor-sobre-principal)' => ! $fundoUrl,
                "background-image: url('{$fundoUrl}'); background-size: cover; background-position: center; color: var(--cor-sobre-principal)" => (bool) $fundoUrl,
            ])>
            @if ($fundoUrl)
                {{-- Tinta da marca sobre a foto: garante leitura do texto claro. --}}
                <div class="absolute inset-0" style="background-color: color-mix(in srgb, var(--cor-principal) 60%, transparent);"></div>
            @endif
            <div class="absolute -right-24 -top-24 size-96 rounded-full opacity-20" style="background-color: var(--cor-sobre-principal); filter: blur(64px);"></div>

            <div class="relative flex items-center gap-3">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $nome }}" class="size-10 rounded-lg bg-white/10 object-contain p-1" />
                @else
                    <flux:icon name="calendar-days" class="size-7" />
                @endif
                <span class="text-xl font-semibold tracking-tight">{{ $nome }}</span>
            </div>

            <div class="relative">
                <h1 class="text-3xl font-semibold leading-tight">Seu horário, do seu jeito.</h1>
                <p class="mt-3 max-w-md" style="color: color-mix(in srgb, var(--cor-sobre-principal) 80%, transparent);">{{ $tagline }}</p>
            </div>

            <div class="relative text-sm" style="color: color-mix(in srgb, var(--cor-sobre-principal) 70%, transparent);">{{ $nome }}</div>
        </div>

        {{-- Coluna do formulário. No desktop é superfície sólida (cobre a foto à
             direita); no estreito COM fundo vira superfície de leitura translúcida
             sobre a foto (.ng-com-fundo), e SEM fundo fica transparente (visual limpo
             sobre --cor-fundo, como antes). --}}
        <div @class([
                'flex flex-col items-center justify-center px-4 py-10 sm:px-8 @3xl:bg-[var(--cor-superficie)]',
                'ng-com-fundo' => $fundoUrl,
            ])>
            {{-- Marca no topo — só na coluna única (estreito). --}}
            <div class="mb-6 flex items-center gap-2 @3xl:hidden">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $nome }}" class="size-8 rounded-lg object-contain" />
                @else
                    <flux:icon name="calendar-days" class="size-7" style="color: var(--cor-principal);" />
                @endif
                <span class="text-xl font-semibold tracking-tight">{{ $nome }}</span>
            </div>

            <div class="w-full max-w-sm">
                <div class="mb-6">
                    <div class="text-lg font-semibold" style="color: var(--cor-texto);">{{ $titulo }}</div>
                    @if ($subtitulo)
                        <div class="text-sm" style="color: var(--cor-texto-suave);">{{ $subtitulo }}</div>
                    @endif
                </div>

                {{ $slot }}
            </div>
        </div>
    </div>
</div>
