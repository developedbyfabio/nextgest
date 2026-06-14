<div class="flex flex-col gap-5">
    <div class="flex items-center justify-between">
        <flux:heading size="lg">Novo agendamento</flux:heading>
        <flux:button :href="route('tenant.home', ['tenant' => tenant('id')])" size="sm" variant="ghost" icon="x-mark" wire:navigate />
    </div>

    {{-- Passo 1: filial --}}
    @if ($passo === 1)
        <div class="flex flex-col gap-3">
            <flux:subheading>Escolha a unidade</flux:subheading>
            @foreach ($unidades as $unidade)
                <flux:button wire:click="selecionarUnidade({{ $unidade->id }})" variant="outline" class="w-full justify-start">
                    {{ $unidade->nome }}
                </flux:button>
            @endforeach
        </div>
    @endif

    {{-- Passo 2: serviços --}}
    @if ($passo === 2)
        <div class="flex flex-col gap-3">
            <flux:subheading>Quais serviços?</flux:subheading>

            @forelse ($servicosDisponiveis as $servico)
                @php($marcado = in_array($servico->id, $servicoIds, true))
                <button type="button" wire:click="toggleServico({{ $servico->id }})"
                    class="flex items-center justify-between rounded-lg border p-3 text-left {{ $marcado ? 'border-zinc-900 bg-zinc-50' : 'border-zinc-200' }}">
                    <div>
                        <div class="font-medium">{{ $servico->nome }}</div>
                        <div class="text-sm text-zinc-500">{{ $servico->duracao_minutos }} min · R$ {{ number_format((float) $servico->preco, 2, ',', '.') }}</div>
                    </div>
                    @if ($marcado)
                        <flux:icon name="check-circle" variant="solid" class="size-6 text-zinc-900" />
                    @endif
                </button>
            @empty
                <flux:text class="text-sm text-zinc-500">Nenhum serviço disponível nesta unidade.</flux:text>
            @endforelse

            @if (! empty($servicoIds))
                <div class="flex items-center justify-between rounded-lg bg-zinc-100 px-3 py-2 text-sm">
                    <span>Total: {{ $duracaoTotal }} min</span>
                    <span class="font-semibold">R$ {{ number_format($valorTotal, 2, ',', '.') }}</span>
                </div>
            @endif

            <div class="flex gap-2">
                @if ($unidades->count() > 1)
                    <flux:button wire:click="voltar" variant="ghost" class="flex-1">Voltar</flux:button>
                @endif
                <flux:button wire:click="irParaProfissional" variant="primary" class="flex-1">Continuar</flux:button>
            </div>
        </div>
    @endif

    {{-- Passo 3: profissional --}}
    @if ($passo === 3)
        <div class="flex flex-col gap-3">
            <flux:subheading>Com quem?</flux:subheading>

            <flux:button wire:click="selecionarProfissional('sem')" variant="outline" class="w-full justify-start">
                Sem preferência
            </flux:button>

            @forelse ($profissionais as $prof)
                <flux:button wire:click="selecionarProfissional('{{ $prof->id }}')" variant="outline" class="w-full justify-start">
                    {{ $prof->name }}
                </flux:button>
            @empty
                <flux:text class="text-sm text-zinc-500">Nenhum profissional faz todos os serviços escolhidos nesta unidade.</flux:text>
            @endforelse

            <flux:button wire:click="voltar" variant="ghost" class="w-full">Voltar</flux:button>
        </div>
    @endif

    {{-- Passo 4: dia e horário --}}
    @if ($passo === 4)
        <div class="flex flex-col gap-4">
            <flux:subheading>Quando?</flux:subheading>

            <flux:input type="date" wire:model.live="data" :min="now()->format('Y-m-d')" label="Dia" />

            <div class="flex flex-col gap-2">
                <flux:text class="text-sm font-medium">Horários disponíveis</flux:text>
                @if ($horarios->isEmpty())
                    <flux:text class="text-sm text-zinc-500">Nenhum horário livre neste dia. Tente outra data.</flux:text>
                @else
                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                        @foreach ($horarios as $slot)
                            <flux:button
                                wire:click="selecionarSlot('{{ $slot['hora'] }}', {{ $slot['profissional_id'] }})"
                                size="sm"
                                :variant="$slotHora === $slot['hora'] ? 'primary' : 'outline'">
                                {{ $slot['hora'] }}
                            </flux:button>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="flex gap-2">
                <flux:button wire:click="voltar" variant="ghost" class="flex-1">Voltar</flux:button>
                <flux:button wire:click="confirmar" variant="primary" class="flex-1" :disabled="$slotHora === null">
                    Confirmar
                </flux:button>
            </div>
        </div>
    @endif
</div>
