<div class="flex flex-col gap-6 p-6 lg:p-8">
    <div>
        <flux:heading size="xl">Olá, {{ auth('web')->user()->name }}</flux:heading>
        <flux:subheading>{{ tenant('nome') }}</flux:subheading>
    </div>

    <flux:callout icon="information-circle">
        <flux:callout.heading>Painel em construção</flux:callout.heading>
        <flux:callout.text>
            Agenda, serviços, clientes e vendas chegam nas próximas fatias (1B/1C).
        </flux:callout.text>
    </flux:callout>
</div>
