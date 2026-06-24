@props([
    'icone' => 'sparkles',
    'titulo' => '',
    'destaque' => false,
])

{{--
    Tile do bento de Recursos. `destaque` = tile maior (2 colunas no grid de 4) com
    o degradê de marca (branco sobre o degradê); regular = superfície clara/escura.
    Bloco geométrico no canto (assinatura). Hover com elevação discreta. O texto
    (opcional) vem pelo slot.

    No grid `grid-cols-2 lg:grid-cols-4`: regular ocupa 1 coluna (metade no mobile,
    quarto no desktop); destaque ocupa 2 (largura cheia no mobile, metade no desktop).
--}}
<div {{ $attributes->class([
    'group relative flex flex-col gap-2 overflow-hidden rounded-2xl p-5 transition duration-300 hover:-translate-y-1',
    'col-span-2' => $destaque,
    'bg-gradient-to-br from-violet-600 via-indigo-600 to-blue-600 text-white shadow-lg shadow-indigo-500/25 hover:shadow-xl hover:shadow-indigo-500/30' => $destaque,
    'border border-slate-200 bg-white text-slate-900 hover:border-indigo-200 hover:shadow-lg hover:shadow-indigo-500/10 dark:border-slate-800 dark:bg-slate-900/60 dark:text-white dark:hover:border-indigo-500/40' => ! $destaque,
]) }}>
    {{-- Bloco geométrico no canto (assinatura da marca) --}}
    <div @class([
        'pointer-events-none absolute -right-5 -top-5 size-20 rounded-2xl transition duration-300 group-hover:scale-110',
        'bg-white/10' => $destaque,
        'bg-gradient-to-br from-violet-600 to-blue-600 opacity-[0.08] group-hover:opacity-[0.16]' => ! $destaque,
    ])></div>

    <span @class([
        'relative inline-flex size-11 items-center justify-center rounded-xl',
        'bg-white/15 text-white ring-1 ring-white/25' => $destaque,
        'bg-gradient-to-br from-violet-600 via-indigo-600 to-blue-600 text-white shadow-md shadow-indigo-500/25' => ! $destaque,
    ])>
        <flux:icon :name="$icone" class="size-5" />
    </span>

    <h3 @class(['relative mt-1 font-semibold', 'text-lg' => $destaque, 'text-sm' => ! $destaque])>{{ $titulo }}</h3>

    @if (trim($slot) !== '')
        <p @class([
            'relative text-sm leading-relaxed',
            'text-white/85' => $destaque,
            'text-slate-600 dark:text-slate-300' => ! $destaque,
        ])>{{ $slot }}</p>
    @endif
</div>
