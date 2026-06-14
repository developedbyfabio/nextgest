<div>
    <flux:button wire:click="abrir" variant="primary" icon="plus">Novo agendamento</flux:button>

    <flux:modal wire:model.self="mostrar" class="md:w-[34rem]">
        <div class="flex flex-col gap-4">
            <flux:heading size="lg">Novo agendamento</flux:heading>

            {{-- Passo 1: cliente --}}
            @if ($passo === 1)
                <flux:subheading>Cliente</flux:subheading>

                <flux:input wire:model.live.debounce.300ms="buscaCliente" icon="magnifying-glass" placeholder="Buscar por nome ou telefone" />

                @if ($clientes->isNotEmpty())
                    <div class="flex flex-col gap-1">
                        @foreach ($clientes as $cliente)
                            <button type="button" wire:click="selecionarCliente({{ $cliente->id }})"
                                class="flex items-center justify-between rounded-md border border-zinc-200 p-2 text-left text-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900">
                                <span>{{ $cliente->nome }}</span>
                                <span class="text-zinc-500">{{ $cliente->telefone }}</span>
                            </button>
                        @endforeach
                    </div>
                @elseif (strlen($buscaCliente) >= 2)
                    <flux:text class="text-sm text-zinc-500">Nenhum cliente encontrado. Cadastre rápido abaixo.</flux:text>
                @endif

                <flux:separator text="ou cadastre" />

                <div class="flex flex-wrap items-end gap-3">
                    <flux:input wire:model="novoNome" label="Nome" class="flex-1" />
                    <flux:input wire:model="novoTelefone" label="Telefone" class="flex-1" />
                    <flux:button wire:click="criarCliente" variant="primary">Adicionar</flux:button>
                </div>
            @endif

            {{-- Passo 2: unidade --}}
            @if ($passo === 2)
                <flux:subheading>Cliente: {{ $clienteNome }}</flux:subheading>
                <flux:text class="text-sm font-medium">Unidade</flux:text>
                @foreach ($unidades as $unidade)
                    <flux:button wire:click="selecionarUnidade({{ $unidade->id }})" variant="outline" class="w-full justify-start">{{ $unidade->nome }}</flux:button>
                @endforeach
            @endif

            {{-- Passo 3: serviços --}}
            @if ($passo === 3)
                <flux:subheading>Cliente: {{ $clienteNome }}</flux:subheading>
                <flux:text class="text-sm font-medium">Serviços</flux:text>
                @forelse ($servicosDisponiveis as $servico)
                    @php($marcado = in_array($servico->id, $servicoIds, true))
                    <button type="button" wire:click="toggleServico({{ $servico->id }})"
                        class="flex items-center justify-between rounded-lg border p-3 text-left transition {{ $marcado ? 'border-zinc-900 bg-zinc-50 dark:border-white dark:bg-zinc-900' : 'border-zinc-200 dark:border-zinc-700' }}">
                        <div>
                            <div class="font-medium">{{ $servico->nome }}</div>
                            <div class="text-sm text-zinc-500">{{ $servico->duracao_minutos }} min · R$ {{ number_format((float) $servico->preco, 2, ',', '.') }}</div>
                        </div>
                        @if ($marcado)<flux:icon name="check-circle" variant="solid" class="size-5" />@endif
                    </button>
                @empty
                    <flux:text class="text-sm text-zinc-500">Nenhum serviço nesta unidade.</flux:text>
                @endforelse

                @if (! empty($servicoIds))
                    <div class="flex items-center justify-between rounded-lg bg-zinc-100 px-3 py-2 text-sm dark:bg-zinc-800">
                        <span>{{ $duracaoTotal }} min</span>
                        <span class="font-semibold">R$ {{ number_format($valorTotal, 2, ',', '.') }}</span>
                    </div>
                @endif

                <div class="flex gap-2">
                    <flux:button wire:click="voltar" variant="ghost" class="flex-1">Voltar</flux:button>
                    <flux:button wire:click="irParaProfissional" variant="primary" class="flex-1">Continuar</flux:button>
                </div>
            @endif

            {{-- Passo 4: profissional --}}
            @if ($passo === 4)
                <flux:text class="text-sm font-medium">Profissional</flux:text>
                <flux:button wire:click="selecionarProfissional('sem')" variant="outline" class="w-full justify-start">Sem preferência</flux:button>
                @forelse ($profissionais as $prof)
                    <flux:button wire:click="selecionarProfissional('{{ $prof->id }}')" variant="outline" class="w-full justify-start">{{ $prof->name }}</flux:button>
                @empty
                    <flux:text class="text-sm text-zinc-500">Nenhum profissional faz todos os serviços nesta unidade.</flux:text>
                @endforelse
                <flux:button wire:click="voltar" variant="ghost" class="w-full">Voltar</flux:button>
            @endif

            {{-- Passo 5: dia/hora --}}
            @if ($passo === 5)
                <flux:input type="date" wire:model.live="data" :min="now()->format('Y-m-d')" label="Dia" />
                <flux:text class="text-sm font-medium">Horários</flux:text>
                @if ($horarios->isEmpty())
                    <flux:text class="text-sm text-zinc-500">Sem horários livres neste dia.</flux:text>
                @else
                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                        @foreach ($horarios as $slot)
                            <flux:button wire:click="selecionarSlot('{{ $slot['hora'] }}', {{ $slot['profissional_id'] }})" size="sm" :variant="$slotHora === $slot['hora'] ? 'primary' : 'outline'">{{ $slot['hora'] }}</flux:button>
                        @endforeach
                    </div>
                @endif
                <div class="flex gap-2">
                    <flux:button wire:click="voltar" variant="ghost" class="flex-1">Voltar</flux:button>
                    <flux:button wire:click="confirmar" variant="primary" class="flex-1" :disabled="$slotHora === null">Confirmar</flux:button>
                </div>
            @endif
        </div>
    </flux:modal>
</div>
