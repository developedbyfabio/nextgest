<div class="flex flex-col gap-6">
    <div class="flex flex-col items-center gap-3 text-center">
        <span class="flex size-14 items-center justify-center rounded-2xl bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400">
            <flux:icon name="lock-closed" class="size-7" />
        </span>
        <flux:heading size="xl">
            {{ $cancelada ? 'Assinatura cancelada' : 'Assinatura pausada' }}
        </flux:heading>
        <flux:text class="text-zinc-500 dark:text-zinc-400">
            @if ($cancelada)
                A assinatura deste estabelecimento foi cancelada. Regularize para reativar o acesso ao painel.
            @else
                O acesso ao painel foi pausado por falta de pagamento. Regularize a fatura abaixo para
                reativar — o portal de agendamento dos seus clientes continua no ar.
            @endif
        </flux:text>
    </div>

    @if ($fatura)
        <flux:card class="flex flex-col gap-3">
            <div class="flex items-center justify-between">
                <flux:text class="text-sm text-zinc-500">Fatura em aberto</flux:text>
                <flux:badge color="red" size="sm">Vencida em {{ $fatura->data_vencimento->format('d/m/Y') }}</flux:badge>
            </div>
            <div class="flex items-baseline justify-between">
                <span class="text-sm text-zinc-500">Competência {{ $fatura->competencia->format('m/Y') }}</span>
                <span class="text-2xl font-bold tracking-tight">R$ {{ number_format((float) $fatura->valor, 2, ',', '.') }}</span>
            </div>

            @if ($fatura->link_pagamento)
                <flux:button :href="$fatura->link_pagamento" variant="primary" icon="credit-card" class="w-full">Pagar agora</flux:button>
            @else
                <flux:callout icon="information-circle">
                    <flux:callout.text>
                        Para regularizar, fale com o time do Nextgest. O pagamento online estará disponível em breve.
                    </flux:callout.text>
                </flux:callout>
            @endif
        </flux:card>
    @else
        <flux:callout icon="information-circle">
            <flux:callout.text>Fale com o time do Nextgest para regularizar a assinatura.</flux:callout.text>
        </flux:callout>
    @endif

    {{-- Permite sair (o logout é isento do middleware de suspensão). --}}
    <form method="POST" action="{{ route('painel.logout', ['tenant' => tenant('id')]) }}">
        @csrf
        <flux:button type="submit" variant="ghost" icon="arrow-right-start-on-rectangle" class="w-full">Sair</flux:button>
    </form>
</div>
