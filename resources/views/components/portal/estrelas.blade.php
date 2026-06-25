@props([
    'nota' => 0,
    'tamanho' => 'size-4',
])

{{-- Exibição read-only de uma nota (1–5) em estrelas. Reutilizado no histórico do
     portal e (futuramente) no painel. Preenchidas em âmbar; vazias esmaecidas. --}}
@php($n = (int) $nota)
<div {{ $attributes->class('inline-flex items-center gap-0.5') }} role="img" aria-label="{{ $n }} de 5 estrelas">
    @for ($i = 1; $i <= 5; $i++)
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"
            class="{{ $tamanho }} {{ $i <= $n ? 'text-amber-400' : 'text-zinc-300 dark:text-zinc-600' }}">
            <path d="M11.48 3.5a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.563.563 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .32-.988l5.519-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z"/>
        </svg>
    @endfor
</div>
