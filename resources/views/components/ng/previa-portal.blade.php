@props([
    'aparencia' => [],
    'nome' => 'Seu Estabelecimento',
])

@php($a = array_merge(\App\Support\Aparencia::PADRAO, $aparencia))
@php($logoUrl = $a['logo_url'] ?? null)
@php($headerUrl = $a['header_url'] ?? null)
@php($fundoUrl = $a['fundo_url'] ?? null)

{{--
    Prévia fiel do portal do cliente, dirigida pelas CSS variables da MARCA
    (acento + secundária + tipografia, via cssVarsAcento). As SUPERFÍCIES vêm das
    classes .ng-previa / .ng-previa.is-dark (claro/escuro), com um alternador
    PRÓPRIO — o dono vê os dois modos sem mexer no tema do painel. Reutilizável:
    recebe um array de aparência (edição e onboarding).
--}}
<div x-data="{ dark: false }" class="flex flex-col items-center gap-3">
    {{-- Alternador claro/escuro — afeta SÓ a prévia. --}}
    <div class="inline-flex items-center gap-1 rounded-full border border-zinc-200 bg-white p-0.5 text-xs dark:border-zinc-700 dark:bg-zinc-800">
        <button type="button" @click="dark = false"
            class="flex items-center gap-1 rounded-full px-2.5 py-1 font-medium transition"
            :class="!dark ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-500'">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5"><path d="M10 2a.75.75 0 0 1 .75.75v1.5a.75.75 0 0 1-1.5 0v-1.5A.75.75 0 0 1 10 2ZM10 15a.75.75 0 0 1 .75.75v1.5a.75.75 0 0 1-1.5 0v-1.5A.75.75 0 0 1 10 15ZM10 7a3 3 0 1 0 0 6 3 3 0 0 0 0-6ZM15.657 5.404a.75.75 0 1 0-1.06-1.06l-1.061 1.06a.75.75 0 0 0 1.06 1.06l1.06-1.06ZM6.464 14.596a.75.75 0 1 0-1.06-1.06l-1.06 1.06a.75.75 0 0 0 1.06 1.06l1.06-1.06ZM18 10a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1 0-1.5h1.5A.75.75 0 0 1 18 10ZM5 10a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1 0-1.5h1.5A.75.75 0 0 1 5 10ZM14.596 15.657a.75.75 0 0 0 1.06-1.06l-1.06-1.061a.75.75 0 1 0-1.06 1.06l1.06 1.06ZM5.404 6.464a.75.75 0 0 0 1.06-1.06l-1.06-1.06a.75.75 0 1 0-1.061 1.06l1.06 1.06Z"/></svg> Claro
        </button>
        <button type="button" @click="dark = true"
            class="flex items-center gap-1 rounded-full px-2.5 py-1 font-medium transition"
            :class="dark ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-500'">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5"><path fill-rule="evenodd" d="M7.455 2.004a.75.75 0 0 1 .26.77 7 7 0 0 0 9.958 7.967.75.75 0 0 1 1.067.853A8.5 8.5 0 1 1 6.647 1.921a.75.75 0 0 1 .808.083Z" clip-rule="evenodd"/></svg> Escuro
        </button>
    </div>

    <div
        {{ $attributes->class('ng-previa mx-auto w-full max-w-sm overflow-hidden rounded-[2rem] border-4 border-zinc-800 shadow-xl') }}
        :class="{ 'is-dark': dark }"
        style="{{ \App\Support\Aparencia::cssVarsAcento($a) }}; background-color: var(--cor-fundo); color: var(--cor-texto);@if ($fundoUrl) background-image: url('{{ $fundoUrl }}'); background-size: cover; background-position: center;@endif"
    >
        {{-- cabeçalho --}}
        <div class="flex items-center justify-between px-4 py-3" style="background-color: var(--cor-superficie); border-bottom: 1px solid color-mix(in srgb, var(--cor-texto) 10%, transparent);">
            <div class="flex items-center gap-2">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $nome }}" class="size-7 rounded object-contain" />
                @else
                    <span class="inline-flex size-6 items-center justify-center" style="color: var(--cor-principal);">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                    </span>
                @endif
                <span class="text-sm font-semibold">{{ $nome }}</span>
            </div>
            <span class="rounded-md px-2.5 py-1 text-xs font-medium" style="background-color: var(--cor-principal); color: var(--cor-sobre-principal);">Entrar</span>
        </div>

        {{-- corpo (font-size em em: escala com o tamanho base escolhido) --}}
        <div class="flex flex-col gap-4 p-4" style="font-size: 0.92em;">
            <div class="relative flex flex-col items-center gap-2 overflow-hidden rounded-2xl border px-4 py-6 text-center"
                style="border-color: color-mix(in srgb, var(--cor-texto) 8%, transparent); background-color: color-mix(in srgb, var(--cor-principal) 6%, var(--cor-superficie));@if ($headerUrl) background-image: url('{{ $headerUrl }}'); background-size: cover; background-position: center;@endif">
                @if ($headerUrl)
                    <div class="absolute inset-0" style="background-color: color-mix(in srgb, var(--cor-principal) 55%, transparent);"></div>
                @endif
                <span class="relative inline-flex size-12 items-center justify-center rounded-2xl" style="background-color: var(--cor-principal); color: var(--cor-sobre-principal);">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-7"><path d="M14.5 2.5a3 3 0 1 0-2.9 3.74L8.7 9.1a3 3 0 1 0 .9 1.78l3-2.94a3 3 0 0 0 1.9-5.44Z"/></svg>
                </span>
                <div class="relative font-semibold" style="font-size: 1.05em;@if ($headerUrl) color: #ffffff;@endif">{{ $nome }}</div>
                <div class="relative" style="color: @if ($headerUrl) rgba(255,255,255,0.85) @else var(--cor-texto-suave) @endif;">Agende seu horário online.</div>
            </div>

            <div class="font-medium" style="font-size: 0.8em; color: var(--cor-texto-suave);">Serviços</div>

            <div class="flex items-center justify-between rounded-xl border p-3"
                style="border-color: color-mix(in srgb, var(--cor-texto) 10%, transparent); background-color: var(--cor-superficie);">
                <div>
                    <div class="font-medium">Corte masculino</div>
                    <div style="font-size: 0.8em; color: var(--cor-texto-suave);">30 min · R$ 45,00</div>
                </div>
                <span class="inline-flex size-5 items-center justify-center rounded-full" style="color: var(--cor-secundaria);">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd"/></svg>
                </span>
            </div>

            <button type="button" class="w-full rounded-lg px-4 py-2.5 font-semibold" style="background-color: var(--cor-principal); color: var(--cor-sobre-principal);">
                Criar conta e agendar
            </button>
        </div>
    </div>
</div>
