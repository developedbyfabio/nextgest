@props([
    'aparencia' => [],
    'nome' => 'Seu Estabelecimento',
])

@php($a = array_merge(\App\Support\Aparencia::PADRAO, $aparencia))

{{--
    Prévia fiel do portal do cliente, dirigida pelas CSS variables da aparência.
    Reutilizável: recebe um array de aparência e renderiza um recorte (cabeçalho +
    hero + card de serviço + botão primário). Usado na edição (Etapa 2) e será
    reusado no onboarding do super-admin (Etapa 3) só passando outra `aparencia`.
--}}
<div
    {{ $attributes->class('mx-auto w-full max-w-sm overflow-hidden rounded-[2rem] border-4 border-zinc-800 shadow-xl') }}
    style="{{ \App\Support\Aparencia::cssVars($a) }}; background-color: var(--cor-fundo); color: var(--cor-texto);"
>
    {{-- cabeçalho --}}
    <div class="flex items-center justify-between px-4 py-3" style="background-color: var(--cor-superficie); border-bottom: 1px solid color-mix(in srgb, var(--cor-texto) 10%, transparent);">
        <div class="flex items-center gap-2">
            <span class="inline-flex size-6 items-center justify-center" style="color: var(--cor-principal);">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
            </span>
            <span class="text-sm font-semibold">{{ $nome }}</span>
        </div>
        <span class="rounded-md px-2.5 py-1 text-xs font-medium text-white" style="background-color: var(--cor-principal);">Entrar</span>
    </div>

    {{-- corpo --}}
    <div class="flex flex-col gap-4 p-4" style="font-size: 0.9rem;">
        <div class="flex flex-col items-center gap-2 rounded-2xl border px-4 py-6 text-center"
            style="border-color: color-mix(in srgb, var(--cor-texto) 8%, transparent); background-color: color-mix(in srgb, var(--cor-principal) 6%, var(--cor-superficie));">
            <span class="inline-flex size-12 items-center justify-center rounded-2xl text-white" style="background-color: var(--cor-principal);">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-7"><path d="M14.5 2.5a3 3 0 1 0-2.9 3.74L8.7 9.1a3 3 0 1 0 .9 1.78l3-2.94a3 3 0 0 0 1.9-5.44Z"/></svg>
            </span>
            <div class="text-base font-semibold">{{ $nome }}</div>
            <div style="color: var(--cor-texto-suave);">Agende seu horário online.</div>
        </div>

        <div class="text-xs font-medium" style="color: var(--cor-texto-suave);">Serviços</div>

        <div class="flex items-center justify-between rounded-xl border p-3"
            style="border-color: color-mix(in srgb, var(--cor-texto) 10%, transparent); background-color: var(--cor-superficie);">
            <div>
                <div class="font-medium">Corte masculino</div>
                <div class="text-xs" style="color: var(--cor-texto-suave);">30 min · R$ 45,00</div>
            </div>
            <span class="inline-flex size-5 items-center justify-center rounded-full" style="color: var(--cor-secundaria);">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd"/></svg>
            </span>
        </div>

        <button type="button" class="w-full rounded-lg px-4 py-2.5 text-sm font-semibold text-white" style="background-color: var(--cor-principal);">
            Criar conta e agendar
        </button>
    </div>
</div>
