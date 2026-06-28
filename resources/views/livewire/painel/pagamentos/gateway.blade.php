<div class="flex flex-col gap-6 p-6 lg:p-8">
    <x-ng.page-header title="Gateway de pagamento" subtitle="Receba os pagamentos do clube direto na sua conta" />

    <div class="ng-surface mx-auto flex w-full max-w-xl flex-col items-center gap-5 p-6 text-center">
        @if ($conectado)
            <span class="flex size-14 items-center justify-center rounded-full bg-green-100 text-green-600 dark:bg-green-500/15">
                <flux:icon name="check-circle" class="size-8" />
            </span>
            <div>
                <flux:heading size="lg" style="color: var(--cor-texto);">Mercado Pago conectado</flux:heading>
                <flux:text class="mt-1 text-sm" style="color: var(--cor-texto-suave);">
                    Conta: <strong style="color: var(--cor-texto);">{{ $conta['nome'] ?? $conta['id'] ?? '—' }}</strong>
                    @if (!empty($conta['conectado_em'])) · desde {{ $conta['conectado_em']->format('d/m/Y') }} @endif
                </flux:text>
                <flux:text class="mt-1 text-xs" style="color: var(--cor-texto-suave);">
                    O dinheiro cai direto na sua conta Mercado Pago. O Nextgest não toca no valor.
                </flux:text>
            </div>
            <flux:button wire:click="desconectar" variant="danger" icon="arrow-right-start-on-rectangle"
                wire:confirm="Desconectar a conta Mercado Pago deste estabelecimento?">Desconectar</flux:button>
        @else
            <span class="flex size-14 items-center justify-center rounded-full"
                style="background-color: color-mix(in srgb, var(--cor-principal) 14%, transparent); color: var(--cor-principal);">
                <flux:icon name="credit-card" class="size-8" />
            </span>
            <div>
                <flux:heading size="lg" style="color: var(--cor-texto);">Conecte sua conta Mercado Pago</flux:heading>
                <flux:text class="mt-1 text-sm" style="color: var(--cor-texto-suave);">
                    Você autoriza no site do Mercado Pago — o Nextgest <strong>nunca</strong> vê sua senha
                    ou seu token. O dinheiro cai direto na sua conta.
                </flux:text>
            </div>
            <flux:button wire:click="conectar" variant="primary" icon="link"
                wire:loading.attr="disabled" wire:target="conectar">
                <span wire:loading.remove wire:target="conectar">Conectar Mercado Pago</span>
                <span wire:loading wire:target="conectar">Redirecionando…</span>
            </flux:button>
        @endif
    </div>
</div>
