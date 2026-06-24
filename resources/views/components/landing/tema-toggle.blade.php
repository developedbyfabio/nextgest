{{--
    Alternador de tema claro/escuro. Usa a abordagem PADRÃO do projeto: o
    `$flux.appearance` do Flux (mesma que o painel/portal usam) — nada de
    localStorage NOSSO. Lê a classe `.dark` aplicada pelo @fluxAppearance e
    inverte. Ícone sol/lua segue o estado via utilitário `dark:`.
--}}
<button
    type="button"
    x-data
    @click="$flux.appearance = document.documentElement.classList.contains('dark') ? 'light' : 'dark'"
    aria-label="Alternar tema claro e escuro"
    class="inline-flex size-10 items-center justify-center rounded-lg text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white"
>
    <flux:icon name="sun" class="size-5 dark:hidden" />
    <flux:icon name="moon" class="hidden size-5 dark:block" />
</button>
