<div>
    <div class="mb-6">
        <flux:heading size="lg">Acesso administrativo</flux:heading>
        <flux:subheading>Painel do super-admin Nextgest</flux:subheading>
    </div>

    <form wire:submit="login" class="flex flex-col gap-4">
        <flux:input
            wire:model="email"
            type="email"
            label="E-mail"
            placeholder="voce@nextgest.com.br"
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
</div>
