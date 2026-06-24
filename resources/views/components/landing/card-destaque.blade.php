@props([
    'icone' => 'sparkles',
    'titulo' => '',
])

{{--
    Card de destaque (faixa de recursos). Linguagem geométrica da marca: bloco em
    degradê no canto + ícone Heroicons em bloco de marca. Hover com elevação
    discreta. O texto vem pelo slot.
--}}
<div {{ $attributes->class('group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-6 transition duration-300 hover:-translate-y-1 hover:border-indigo-200 hover:shadow-xl hover:shadow-indigo-500/10 dark:border-slate-800 dark:bg-slate-900/60 dark:hover:border-indigo-500/40') }}>
    {{-- Bloco geométrico (assinatura da marca) --}}
    <div class="absolute -right-5 -top-5 size-20 rounded-2xl bg-gradient-to-br from-violet-600 to-blue-600 opacity-[0.08] transition duration-300 group-hover:scale-110 group-hover:opacity-[0.16]"></div>

    <span class="relative inline-flex size-12 items-center justify-center rounded-xl bg-gradient-to-br from-violet-600 via-indigo-600 to-blue-600 text-white shadow-lg shadow-indigo-500/25">
        <flux:icon :name="$icone" class="size-6" />
    </span>

    <h3 class="relative mt-4 text-lg font-semibold text-slate-900 dark:text-white">{{ $titulo }}</h3>
    <p class="relative mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $slot }}</p>
</div>
