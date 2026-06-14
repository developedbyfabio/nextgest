<div class="flex flex-col gap-6">
    <flux:heading size="xl">Painel do super-admin</flux:heading>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <flux:card>
            <flux:text>Estabelecimentos</flux:text>
            <flux:heading size="xl" class="mt-1">{{ $totalTenants }}</flux:heading>
        </flux:card>
    </div>

    <flux:callout icon="information-circle">
        <flux:callout.heading>Em construção</flux:callout.heading>
        <flux:callout.text>
            Gestão de estabelecimentos, planos do SaaS e cobrança chegam nas próximas fatias.
        </flux:callout.text>
    </flux:callout>
</div>
