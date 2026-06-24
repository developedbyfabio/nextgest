@props([
    'icone' => 'sparkles',
    'titulo' => '',
])

{{--
    Card de "tipos de negócio" (para quem o Nextgest serve). Ícone Heroicons em
    bloco de marca + nome do público + 1 frase de benefício específico (slot).
    Hover discreto, responsivo.
--}}
<div {{ $attributes->class('group flex items-start gap-4 rounded-2xl border border-slate-200 bg-white p-5 transition duration-300 hover:-translate-y-1 hover:border-indigo-200 hover:shadow-lg hover:shadow-indigo-500/10 dark:border-slate-800 dark:bg-slate-900/60 dark:hover:border-indigo-500/40') }}>
    <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-violet-600 via-indigo-600 to-blue-600 text-white shadow-md shadow-indigo-500/25">
        <flux:icon :name="$icone" class="size-5" />
    </span>
    <div class="min-w-0">
        <h3 class="font-semibold text-slate-900 dark:text-white">{{ $titulo }}</h3>
        <p class="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $slot }}</p>
    </div>
</div>
