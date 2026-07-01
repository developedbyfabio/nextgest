@props([
    'aparencia' => [],
    'logoUrl' => null,    // URL do logo já resolvida (portal: urlArquivo; prévia: logo_url/temporária)
    'contexto' => 'hero', // 'hero' (quadrado de acento, size-16) | 'topo' (marca compacta do cabeçalho)
    'nome' => '',
])

{{-- NÃO usar a prop 'variant' aqui: ela COLIDIRIA com o <flux:icon> aninhado
     (cujo variant é outline/solid/…), e um 'hero'/'topo' quebraria o match do ícone. --}}

{{-- Brand-mark do portal (D92) — PARTIAL ÚNICO usado no hero (x-portal.capa) e no
     topo (x-portal.cabecalho), portanto no portal real E na prévia da Aparência (sem
     markup paralelo). A escolha do dono (marca_tipo/marca_icone) decide a fonte:
      - 'logo' + logo enviado → a imagem, RESPEITANDO TRANSPARÊNCIA (sem quadrado
        colorido atrás, que anularia a transparência);
      - senão → o ícone escolhido (padrão tesoura) na cor de acento.
     Fallback: 'logo' sem imagem cai no ícone. --}}
@php($tipo = $aparencia['marca_tipo'] ?? \App\Support\Aparencia::PADRAO['marca_tipo'])
@php($icone = \App\Support\Aparencia::marcaIcone($aparencia))
@php($usaLogo = $tipo === 'logo' && $logoUrl)

@if ($contexto === 'hero')
    @if ($usaLogo)
        <img src="{{ $logoUrl }}" alt="{{ $nome }}" class="relative size-16 rounded-2xl object-contain" />
    @else
        <div class="relative flex size-16 items-center justify-center rounded-2xl shadow-sm" style="background-color: var(--cor-principal); color: var(--cor-sobre-principal);">
            <flux:icon :name="$icone" class="size-8" />
        </div>
    @endif
@else
    @if ($usaLogo)
        <img src="{{ $logoUrl }}" alt="{{ $nome }}" class="size-8 shrink-0 rounded object-contain" />
    @else
        <flux:icon :name="$icone" class="size-6 shrink-0" style="color: var(--cor-principal);" />
    @endif
@endif
