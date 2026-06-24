@props([
    'numero' => '01',
    'icone' => 'sparkles',
    'titulo' => '',
])

{{--
    Passo de "Como funciona" (sequência real do produto). Número em degradê de
    marca + ícone Heroicons + título + descrição (slot). Hover discreto. Card
    responsivo (empilha no mobile).
--}}
<div {{ $attributes->class('group relative flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-5 transition duration-300 hover:-translate-y-1 hover:border-indigo-200 hover:shadow-lg hover:shadow-indigo-500/10 dark:border-slate-800 dark:bg-slate-900/60 dark:hover:border-indigo-500/40') }}>
    <div class="flex items-center justify-between">
        <span class="bg-gradient-to-r from-violet-600 to-blue-600 bg-clip-text text-2xl font-bold tracking-tight text-transparent">{{ $numero }}</span>
        <span class="flex size-10 items-center justify-center rounded-xl bg-gradient-to-br from-violet-600 via-indigo-600 to-blue-600 text-white shadow-md shadow-indigo-500/25">
            <flux:icon :name="$icone" class="size-5" />
        </span>
    </div>
    <div>
        <h3 class="text-base font-semibold text-slate-900 dark:text-white">{{ $titulo }}</h3>
        <p class="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $slot }}</p>
    </div>
</div>
