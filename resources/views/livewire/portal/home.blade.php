<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="lg">{{ tenant('nome') }}</flux:heading>
        <flux:text class="mt-1">Agende seu horário em poucos toques.</flux:text>
    </div>

    @auth('cliente')
        <flux:callout icon="check-circle" variant="success">
            <flux:callout.heading>Você está conectado</flux:callout.heading>
            <flux:callout.text>Olá, {{ auth('cliente')->user()->nome }}! O agendamento online chega na próxima fatia.</flux:callout.text>
        </flux:callout>
    @else
        <flux:callout icon="calendar-days">
            <flux:callout.heading>Crie sua conta</flux:callout.heading>
            <flux:callout.text>Entre ou cadastre-se para agendar.</flux:callout.text>
        </flux:callout>

        <div class="flex flex-col gap-2">
            <flux:button :href="route('cliente.login', ['tenant' => tenant('id')])" variant="primary" class="w-full" wire:navigate>
                Entrar
            </flux:button>
            <flux:button :href="route('cliente.registrar', ['tenant' => tenant('id')])" variant="ghost" class="w-full" wire:navigate>
                Criar conta
            </flux:button>
        </div>
    @endauth
</div>
