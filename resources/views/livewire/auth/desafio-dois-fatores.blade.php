<div>
    <div class="mb-6">
        <flux:heading size="lg">Verificação em duas etapas</flux:heading>
        <flux:subheading>
            Abra seu app autenticador e informe o código de 6 dígitos. Sem o app? Use um
            código de recuperação.
        </flux:subheading>
    </div>

    <form wire:submit="verificar" class="flex flex-col gap-4">
        <flux:input
            wire:model="codigo"
            label="Código"
            placeholder="000000"
            inputmode="text"
            autocomplete="one-time-code"
            autofocus
            required
        />

        <flux:button type="submit" variant="primary" class="w-full">
            Verificar
        </flux:button>
    </form>

    <flux:separator class="my-6" />

    <flux:button
        :href="route('painel.login', ['tenant' => tenant('id')])"
        variant="ghost"
        class="w-full"
        wire:navigate
    >
        Voltar ao login
    </flux:button>
</div>
