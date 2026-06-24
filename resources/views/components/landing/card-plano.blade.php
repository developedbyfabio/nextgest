@props([
    'nome' => '',
    'precoDe' => null,
    'precoPor' => '',
    'etiqueta' => null,
    'paraQuem' => null,
    'inclui' => [],
    'naoInclui' => [],
    'destaque' => false,
    'badge' => null,
    'ctaTexto' => 'Começar agora',
    'ctaHref' => '#contato',
])

{{--
    Card de plano (preços de lançamento). "de" riscado + "por" em degradê (ancoragem),
    etiqueta de condição, lista de recursos (incluídos com check / não incluídos com
    x cinza + risco). `destaque` = plano "Mais escolhido" (borda + elevação). CTA leva
    ao contato/WhatsApp (sem checkout). Bom no dark; CTAs alinhados embaixo (mt-auto).
--}}
<div @class([
    'relative flex flex-col rounded-3xl p-6 transition duration-300 sm:p-7',
    'border-2 border-indigo-500 bg-white shadow-xl shadow-indigo-500/15 dark:bg-slate-900 lg:-translate-y-3' => $destaque,
    'border border-slate-200 bg-white hover:-translate-y-1 hover:shadow-lg hover:shadow-indigo-500/10 dark:border-slate-800 dark:bg-slate-900/60' => ! $destaque,
]) }}>
    @if ($badge)
        <span class="absolute -top-3 left-1/2 inline-flex -translate-x-1/2 items-center gap-1 rounded-full bg-gradient-to-r from-violet-600 via-indigo-600 to-blue-600 px-3 py-1 text-xs font-semibold text-white shadow-md shadow-indigo-500/30">
            <flux:icon name="star" class="size-3.5" /> {{ $badge }}
        </span>
    @endif

    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ $nome }}</h3>
    @if ($paraQuem)
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $paraQuem }}</p>
    @endif

    {{-- Preço (ancoragem de/por) --}}
    <div class="mt-5">
        @if ($precoDe)
            <span class="text-sm font-medium text-slate-400 line-through">{{ $precoDe }}</span>
        @endif
        <div class="flex items-baseline gap-1.5">
            <span class="bg-gradient-to-r from-violet-600 via-indigo-600 to-blue-600 bg-clip-text text-4xl font-bold tracking-tight text-transparent">{{ $precoPor }}</span>
            <span class="text-sm font-medium text-slate-500 dark:text-slate-400">/mês</span>
        </div>
        @if ($etiqueta)
            <span class="mt-2 inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300">
                <flux:icon name="sparkles" class="size-3.5" /> {{ $etiqueta }}
            </span>
        @endif
    </div>

    {{-- Recursos --}}
    <ul class="mt-6 flex flex-1 flex-col gap-2.5 text-sm">
        @foreach ($inclui as $item)
            <li class="flex items-start gap-2 text-slate-700 dark:text-slate-200">
                <flux:icon name="check-circle" class="mt-0.5 size-5 shrink-0 text-indigo-600 dark:text-indigo-400" />
                <span>{{ $item }}</span>
            </li>
        @endforeach
        @foreach ($naoInclui as $item)
            <li class="flex items-start gap-2 text-slate-400 dark:text-slate-500">
                <flux:icon name="x-circle" class="mt-0.5 size-5 shrink-0 text-slate-300 dark:text-slate-600" />
                <span class="line-through decoration-slate-300 dark:decoration-slate-600">{{ $item }}</span>
            </li>
        @endforeach
    </ul>

    <a href="{{ $ctaHref }}" @if (str_starts_with($ctaHref, 'http')) target="_blank" rel="noopener noreferrer" @endif
        @class([
            'mt-7 inline-flex items-center justify-center gap-2 rounded-xl px-5 py-3 text-sm font-semibold transition duration-200 hover:-translate-y-0.5 active:translate-y-0 active:scale-[0.98]',
            'bg-gradient-to-r from-violet-600 via-indigo-600 to-blue-600 text-white shadow-lg shadow-indigo-500/25 hover:shadow-xl hover:shadow-indigo-500/30' => $destaque,
            'border border-slate-300 text-slate-700 hover:border-indigo-400 hover:text-indigo-600 dark:border-slate-700 dark:text-slate-200 dark:hover:border-indigo-500/60 dark:hover:text-indigo-300' => ! $destaque,
        ])>{{ $ctaTexto }}</a>
</div>
