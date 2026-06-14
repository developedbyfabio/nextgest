<div>
    <div class="mb-6">
        <flux:heading size="lg">Entrar</flux:heading>
        <flux:subheading>Acesse para agendar em {{ tenant('nome') }}</flux:subheading>
    </div>

    <form wire:submit="login" class="flex flex-col gap-4">
        <flux:input
            wire:model="email"
            type="email"
            label="E-mail"
            placeholder="voce@exemplo.com"
            autocomplete="username"
            required
        />

        <flux:input
            wire:model="password"
            type="password"
            label="Senha"
            placeholder="Sua senha"
            autocomplete="current-password"
            viewable
            required
        />

        <flux:checkbox wire:model="remember" label="Manter conectado" />

        <flux:button type="submit" variant="primary" class="w-full">
            Entrar
        </flux:button>
    </form>

    <flux:separator class="my-6" text="ou" />

    <flux:button :href="route('cliente.registrar', ['tenant' => tenant('id')])" variant="ghost" class="w-full" wire:navigate>
        Criar uma conta
    </flux:button>
</div>
