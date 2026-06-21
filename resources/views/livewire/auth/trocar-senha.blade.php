<div>
    <div class="mb-6">
        <flux:heading size="lg">Defina uma nova senha</flux:heading>
        <flux:subheading>Por segurança, escolha uma senha própria para continuar.</flux:subheading>
    </div>

    <form wire:submit="salvar" class="flex flex-col gap-4">
        <flux:input
            wire:model="password"
            type="password"
            label="Nova senha"
            placeholder="Mínimo 8 caracteres"
            autocomplete="new-password"
            viewable
            required
        />

        <flux:input
            wire:model="password_confirmation"
            type="password"
            label="Confirmar nova senha"
            placeholder="Repita a nova senha"
            autocomplete="new-password"
            viewable
            required
        />

        <flux:button type="submit" variant="primary" class="w-full">
            Salvar e continuar
        </flux:button>
    </form>

    <flux:separator class="my-6" />

    <form method="POST" action="{{ route('painel.logout', ['tenant' => tenant('id')]) }}">
        @csrf
        <flux:button type="submit" variant="ghost" class="w-full">Sair</flux:button>
    </form>
</div>
