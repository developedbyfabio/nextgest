<div class="flex flex-col gap-6">
    <x-ng.page-header title="Mercado Pago" subtitle="Pagamento online (gateway)">
        <x-slot:actions>
            <flux:button :href="route('painel.integracoes', ['tenant' => tenant('id')])" variant="ghost" icon="arrow-left" wire:navigate>Voltar</flux:button>
        </x-slot:actions>
    </x-ng.page-header>

    <form wire:submit="salvar" class="flex w-full max-w-xl flex-col gap-5">
        <flux:card class="flex flex-col gap-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <flux:heading size="lg">Credenciais</flux:heading>
                    <flux:subheading>Armazenamento seguro (cifrado). Esta etapa não testa conexão.</flux:subheading>
                </div>
                @if ($configurado)
                    <flux:badge color="green" icon="check-circle">Configurado</flux:badge>
                @else
                    <flux:badge color="zinc">Não configurado</flux:badge>
                @endif
            </div>

            @if ($configurado)
                <flux:callout icon="lock-closed">
                    <flux:callout.text>
                        Token atual: <span class="font-mono">{{ $mascara }}</span>. Por segurança, não exibimos o valor completo. Deixe o campo abaixo <strong>vazio</strong> para mantê-lo.
                    </flux:callout.text>
                </flux:callout>
            @endif

            <flux:input
                wire:model="access_token"
                type="password"
                label="Access Token"
                :placeholder="$configurado ? 'Preencha apenas para substituir' : 'Cole o Access Token da sua conta'"
                viewable
                autocomplete="off" />

            <flux:select wire:model="modo" label="Modo">
                <flux:select.option value="sandbox">Sandbox (testes)</flux:select.option>
                <flux:select.option value="producao">Produção</flux:select.option>
            </flux:select>

            <flux:switch wire:model="ativo" label="Integração ativa" description="Quando desligada, o token fica salvo mas não é usado." />

            <div class="flex justify-end border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:button type="submit" variant="primary" icon="check">Salvar</flux:button>
            </div>
        </flux:card>
    </form>
</div>
