@props([
    'nome' => '',
    'descricao' => null,
    'headerUrl' => null, // imagem de cabeçalho (capa)
    'aparencia' => [],   // aparência do tenant (define a marca — ver x-portal.marca)
    'logoUrl' => null,   // logo já resolvido (p/ marca_tipo=logo); null → ícone
])

@php($corTitulo = $headerUrl ? '#ffffff' : 'var(--cor-texto)')
@php($corSub = $headerUrl ? 'rgba(255,255,255,0.88)' : 'var(--cor-texto-suave)')

{{-- Capa/hero do portal (identidade + chamada). Mostra a IMAGEM DE CABEÇALHO como
     banner quando configurada. FONTE DE VERDADE única: portal real (home do
     visitante) e prévia. (Evita diretivas Blade dentro de tags de componente Flux.) --}}
<div class="ng-fade-in relative flex flex-col items-center gap-4 overflow-hidden rounded-2xl border px-6 py-10 text-center"
    style="border-color: color-mix(in srgb, var(--cor-texto) 8%, transparent); background-color: color-mix(in srgb, var(--cor-principal) 6%, var(--cor-superficie));@if ($headerUrl) background-image: url('{{ $headerUrl }}'); background-size: cover; background-position: center;@endif">
    @if ($headerUrl)
        {{-- Véu na cor da marca para o texto ficar legível sobre a foto. --}}
        <div class="absolute inset-0" style="background-color: color-mix(in srgb, var(--cor-principal) 55%, transparent);"></div>
    @endif

    <x-portal.marca :aparencia="$aparencia" :logo-url="$logoUrl" :nome="$nome" contexto="hero" />
    <div class="relative">
        <div class="text-2xl font-bold tracking-tight" style="color: {{ $corTitulo }};">{{ $nome }}</div>
        <div class="mt-1 text-sm" style="color: {{ $corSub }};">{{ $descricao ?: 'Agende seu horário online, em poucos toques.' }}</div>
    </div>
</div>
