<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl">Painel do super-admin</flux:heading>
        <flux:subheading>Visão geral dos estabelecimentos do Nextgest.</flux:subheading>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {{-- Card "Estabelecimentos" — acento de marca (degradê violeta→azul) + bloco
             geométrico no canto (assinatura da landing). Conteúdo/lógica inalterados. --}}
        <div class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-6 transition duration-300 hover:-translate-y-1 hover:border-indigo-200 hover:shadow-lg hover:shadow-indigo-500/10 dark:border-slate-800 dark:bg-slate-900/60 dark:hover:border-indigo-500/40">
            <div class="pointer-events-none absolute -right-5 -top-5 size-20 rounded-2xl bg-gradient-to-br from-violet-600 to-blue-600 opacity-[0.08] transition duration-300 group-hover:scale-110 group-hover:opacity-[0.16]"></div>

            <span class="relative inline-flex size-11 items-center justify-center rounded-xl bg-gradient-to-br from-violet-600 via-indigo-600 to-blue-600 text-white shadow-md shadow-indigo-500/25">
                <flux:icon name="building-storefront" class="size-5" />
            </span>

            <div class="relative mt-4">
                <div class="text-sm font-medium text-slate-500 dark:text-slate-400">Estabelecimentos</div>
                <div class="mt-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $totalTenants }}</div>
            </div>

            <flux:button :href="route('admin.tenants')" size="sm" variant="primary" icon="building-storefront" class="relative mt-4 self-start" wire:navigate>
                Gerenciar
            </flux:button>
        </div>
    </div>

    {{-- Banner "Em construção" no visual de marca (degradê sutil). --}}
    <div class="flex items-start gap-3 rounded-2xl border border-indigo-200/70 bg-gradient-to-r from-violet-600/[0.06] via-indigo-600/[0.06] to-blue-600/[0.06] p-5 dark:border-indigo-500/30">
        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-violet-600 to-blue-600 text-white">
            <flux:icon name="wrench-screwdriver" class="size-5" />
        </span>
        <div>
            <div class="font-semibold text-slate-900 dark:text-white">Em construção</div>
            <div class="mt-0.5 text-sm text-slate-600 dark:text-slate-300">
                Planos do SaaS e cobrança dos estabelecimentos chegam nas próximas fatias.
            </div>
        </div>
    </div>
</div>
