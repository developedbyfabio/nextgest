<div class="flex flex-col gap-6">
    <x-ng.page-header title="WhatsApp" subtitle="Lembretes e mensagens automáticas">
        <x-slot:actions>
            <flux:button :href="route('painel.integracoes', ['tenant' => tenant('id')])" variant="ghost" icon="arrow-left" wire:navigate>Voltar</flux:button>
        </x-slot:actions>
    </x-ng.page-header>

    <form wire:submit="salvar" class="flex w-full max-w-xl flex-col gap-5">
        <flux:card class="flex flex-col gap-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <flux:heading size="lg">Credenciais (WhatsApp Cloud API)</flux:heading>
                    <flux:subheading>Armazenamento seguro (cifrado). Esta etapa não testa conexão.</flux:subheading>
                </div>
                @if ($configurado)
                    <flux:badge color="green" icon="check-circle">Configurado</flux:badge>
                @else
                    <flux:badge color="zinc">Não configurado</flux:badge>
                @endif
            </div>

            <flux:input wire:model="telefone" label="Telefone" placeholder="+55 11 90000-0000" />
            <flux:input wire:model="phone_number_id" label="Phone Number ID" placeholder="ID do número (não secreto)" />
            <flux:input wire:model="business_account_id" label="Business Account ID" placeholder="ID da conta (não secreto)" />

            @if ($configurado)
                <flux:callout icon="lock-closed">
                    <flux:callout.text>
                        Token atual: <span class="font-mono">{{ $mascara }}</span>. Por segurança, não exibimos o valor completo. Deixe o campo abaixo <strong>vazio</strong> para mantê-lo.
                    </flux:callout.text>
                </flux:callout>
            @endif

            <flux:input
                wire:model="token"
                type="password"
                label="Token de acesso"
                :placeholder="$configurado ? 'Preencha apenas para substituir' : 'Cole o token da API'"
                viewable
                autocomplete="off" />

            <flux:switch wire:model="ativo" label="Integração ativa" description="Quando desligada, o token fica salvo mas não é usado." />

            <div class="flex justify-end border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:button type="submit" variant="primary" icon="check">Salvar</flux:button>
            </div>
        </flux:card>
    </form>
</div>
