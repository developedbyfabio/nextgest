<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="lg">{{ tenant('nome') }}</flux:heading>
        <flux:text class="mt-1">Agende seu horário em poucos toques.</flux:text>
    </div>

    @auth('cliente')
        <flux:button :href="route('cliente.agendar', ['tenant' => tenant('id')])" variant="primary" icon="plus" class="w-full" wire:navigate>
            Novo agendamento
        </flux:button>

        <div class="flex flex-col gap-3">
            <flux:heading size="sm">Próximos agendamentos</flux:heading>

            @forelse ($proximos as $agendamento)
                <flux:card class="flex flex-col gap-2">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <flux:text class="font-semibold">
                                {{ $agendamento->data_hora_inicio->translatedFormat('D, d/m H:i') }}
                            </flux:text>
                            <flux:text class="text-sm text-zinc-500">
                                {{ $agendamento->itens->map(fn ($i) => $i->servico?->nome)->filter()->join(', ') }}
                            </flux:text>
                            <flux:text class="text-sm text-zinc-500">
                                com {{ $agendamento->profissional?->name }}
                            </flux:text>
                        </div>
                        @if ($agendamento->status === 'pendente')
                            <flux:badge color="amber" size="sm">Pendente</flux:badge>
                        @else
                            <flux:badge color="green" size="sm">Confirmado</flux:badge>
                        @endif
                    </div>

                    @if ($podeCancelar($agendamento))
                        <flux:button wire:click="cancelar({{ $agendamento->id }})" wire:confirm="Cancelar este agendamento?" size="sm" variant="subtle" class="self-end">
                            Cancelar
                        </flux:button>
                    @endif
                </flux:card>
            @empty
                <flux:text class="text-sm text-zinc-500">Você não tem agendamentos futuros.</flux:text>
            @endforelse
        </div>

        <flux:callout icon="star">
            <flux:callout.heading>Clube de assinatura</flux:callout.heading>
            <flux:callout.text>Em breve: planos e benefícios.</flux:callout.text>
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
