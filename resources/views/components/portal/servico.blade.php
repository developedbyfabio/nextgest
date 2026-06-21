@props([
    'nome' => '',
    'duracao' => null, // minutos
    'preco' => null,
    'selecionado' => false,
])

{{-- Linha de serviço do portal (nome + duração/preço). Presentacional; usado na
     lista de serviços da prévia (o fluxo real usa x-ng.option-card interativo). --}}
<div class="flex items-center justify-between rounded-xl border p-3"
    @style([
        'border-color: color-mix(in srgb, var(--cor-texto) 10%, transparent)' => ! $selecionado,
        'border-color: var(--cor-principal); box-shadow: inset 0 0 0 1px var(--cor-principal)' => $selecionado,
    ])
    style="background-color: var(--cor-superficie);">
    <div class="min-w-0">
        <div class="font-medium">{{ $nome }}</div>
        <div class="text-sm" style="color: var(--cor-texto-suave);">
            {{ $duracao }} min · R$ {{ number_format((float) $preco, 2, ',', '.') }}
        </div>
    </div>
    <span class="inline-flex size-5 shrink-0 items-center justify-center rounded-full" style="color: {{ $selecionado ? 'var(--cor-principal)' : 'var(--cor-secundaria)' }};">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd"/></svg>
    </span>
</div>
