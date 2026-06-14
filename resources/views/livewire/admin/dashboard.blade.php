<div class="flex flex-col gap-6">
    <flux:heading size="xl">Painel do super-admin</flux:heading>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <flux:card class="flex flex-col gap-3">
            <div>
                <flux:text>Estabelecimentos</flux:text>
                <flux:heading size="xl" class="mt-1">{{ $totalTenants }}</flux:heading>
            </div>
            <flux:button :href="route('admin.tenants')" size="sm" variant="primary" icon="building-storefront" class="self-start" wire:navigate>
                Gerenciar
            </flux:button>
        </flux:card>
    </div>

    <flux:callout icon="information-circle">
        <flux:callout.heading>Em construção</flux:callout.heading>
        <flux:callout.text>
            Planos do SaaS e cobrança dos estabelecimentos chegam nas próximas fatias.
        </flux:callout.text>
    </flux:callout>
</div>
