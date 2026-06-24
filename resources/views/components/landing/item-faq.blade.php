@props([
    'pergunta' => '',
])

{{--
    Item de FAQ (accordion) com Alpine — abre/fecha independente (várias podem ficar
    abertas). Acessível: <button> com aria-expanded + aria-controls; chevron gira;
    painel com transição suave. Sem Livewire. A resposta vem pelo slot.
--}}
<div x-data="{ open: false }" {{ $attributes->class('overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900/60') }}>
    <h3>
        <button type="button" @click="open = ! open" :aria-expanded="open"
            class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:hover:bg-slate-800/50">
            <span class="font-medium text-slate-900 dark:text-white">{{ $pergunta }}</span>
            <svg viewBox="0 0 20 20" fill="currentColor" class="size-5 shrink-0 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': open }" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/>
            </svg>
        </button>
    </h3>
    <div x-show="open" x-cloak
        x-transition:enter="transition duration-200 ease-out"
        x-transition:enter-start="-translate-y-1 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition duration-150 ease-in"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0">
        <p class="px-5 pb-4 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $slot }}</p>
    </div>
</div>
