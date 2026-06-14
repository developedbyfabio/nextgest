<div class="flex flex-col gap-6">
    @auth('cliente')
        <div>
            <flux:heading size="lg">{{ tenant('nome') }}</flux:heading>
            <flux:text class="mt-1">Agende seu horário em poucos toques.</flux:text>
        </div>

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
        {{-- Visitante: identidade do estabelecimento + chamada para agendar --}}
        <div class="flex flex-col items-center gap-4 rounded-2xl border border-zinc-200 bg-gradient-to-b from-indigo-50/60 to-white px-6 py-10 text-center dark:border-zinc-800 dark:from-indigo-950/30 dark:to-zinc-900">
            <div class="flex size-16 items-center justify-center rounded-2xl bg-indigo-600 text-white shadow-sm">
                <flux:icon name="scissors" class="size-8" />
            </div>
            <div>
                <flux:heading size="xl">{{ tenant('nome') }}</flux:heading>
                <flux:text class="mt-1 text-zinc-600 dark:text-zinc-300">Agende seu horário online, em poucos toques.</flux:text>
            </div>
        </div>

        <div class="flex flex-col gap-3">
            <flux:text class="text-center text-sm font-medium text-zinc-500">Como funciona</flux:text>
            <div class="grid grid-cols-3 gap-2 text-center">
                @php($passos = [['user-plus', 'Crie sua conta'], ['scissors', 'Escolha o serviço'], ['calendar-days', 'Marque o horário']])
                @foreach ($passos as $i => [$icone, $texto])
                    <div class="flex flex-col items-center gap-1 rounded-xl border border-zinc-100 p-3 dark:border-zinc-800">
                        <flux:icon :name="$icone" class="size-5 text-indigo-600 dark:text-indigo-400" />
                        <span class="text-xs text-zinc-600 dark:text-zinc-300">{{ $texto }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex flex-col gap-2">
            <flux:button :href="route('cliente.registrar', ['tenant' => tenant('id')])" variant="primary" icon="calendar-days" class="w-full" wire:navigate>
                Criar conta e agendar
            </flux:button>
            <flux:button :href="route('cliente.login', ['tenant' => tenant('id')])" variant="ghost" class="w-full" wire:navigate>
                Já tenho conta
            </flux:button>
        </div>
    @endauth
</div>
