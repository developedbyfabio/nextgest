@props([
    'titulo' => '',
])

{{-- Moldura compartilhada dos documentos legais (D93): voltar + cabeçalho (nome do
     estabelecimento, título, data/versão) + a prosa (slot). Data e versão vêm de
     App\Support\Legal (fonte única). O texto de cada documento fica no seu próprio
     arquivo (a view Livewire), renderizado aqui dentro. --}}
@php($tenantId = tenant('id'))

<div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
    <a href="{{ route('tenant.home', ['tenant' => $tenantId]) }}"
        class="inline-flex w-fit items-center gap-1.5 text-sm font-medium hover:underline"
        style="color: var(--cor-principal);">
        <flux:icon name="arrow-left" class="size-4" />
        Voltar para {{ tenant('nome') }}
    </a>

    <header class="flex flex-col gap-1 border-b pb-4" style="border-color: color-mix(in srgb, var(--cor-texto) 10%, transparent);">
        <div class="text-xs font-semibold uppercase tracking-wide" style="color: var(--cor-texto-suave);">{{ tenant('nome') }}</div>
        <h1 class="text-2xl font-bold tracking-tight" style="color: var(--cor-texto);">{{ $titulo }}</h1>
        <div class="text-xs" style="color: var(--cor-texto-suave);">
            Última atualização:
            <time datetime="{{ \App\Support\Legal::ATUALIZADO_EM }}">{{ \App\Support\Legal::atualizadoEmLabel() }}</time>
            · Versão {{ \App\Support\Legal::VERSAO }}
        </div>
    </header>

    <article class="ng-prosa flex flex-col gap-5 pb-4 text-sm leading-relaxed">
        {{ $slot }}
    </article>
</div>
