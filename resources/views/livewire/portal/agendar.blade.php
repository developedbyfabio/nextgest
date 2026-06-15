@php
    $passoUnico = $unidades->count() === 1;
    $totalPassos = $passoUnico ? 3 : 4;
    $passoAtual = $passoUnico ? $passo - 1 : $passo;
@endphp

<div class="flex min-h-[70vh] flex-col gap-5">
    <div class="flex items-center justify-between">
        <flux:heading size="lg">Novo agendamento</flux:heading>
        <flux:button :href="route('tenant.home', ['tenant' => tenant('id')])" size="sm" variant="ghost" icon="x-mark" wire:navigate />
    </div>

    {{-- Indicador de progresso --}}
    <div class="flex items-center gap-1.5" aria-label="Progresso">
        @for ($i = 1; $i <= $totalPassos; $i++)
            <div class="h-1.5 flex-1 rounded-full transition-colors duration-300"
                @style([
                    'background-color: var(--cor-principal)' => $i <= $passoAtual,
                    'background-color: color-mix(in srgb, var(--cor-texto) 12%, transparent)' => $i > $passoAtual,
                ])></div>
        @endfor
    </div>

    {{-- Passo 1: filial --}}
    @if ($passo === 1)
        <div class="flex flex-col gap-3">
            <flux:subheading>Escolha a unidade</flux:subheading>
            @foreach ($unidades as $unidade)
                <x-ng.option-card wire:click="selecionarUnidade({{ $unidade->id }})">
                    <span class="font-medium">{{ $unidade->nome }}</span>
                    @if ($unidade->endereco)
                        <span class="block text-sm text-zinc-500">{{ $unidade->endereco }}</span>
                    @endif
                </x-ng.option-card>
            @endforeach
        </div>
    @endif

    {{-- Passo 2: serviços --}}
    @if ($passo === 2)
        <div class="flex flex-1 flex-col gap-3">
            <flux:subheading>Quais serviços?</flux:subheading>

            @forelse ($servicosDisponiveis as $servico)
                <x-ng.option-card :selected="in_array($servico->id, $servicoIds, true)" wire:click="toggleServico({{ $servico->id }})" wire:key="srv-{{ $servico->id }}">
                    <span class="block font-medium">{{ $servico->nome }}</span>
                    <span class="block text-sm text-zinc-500">{{ $servico->duracao_minutos }} min · R$ {{ number_format((float) $servico->preco, 2, ',', '.') }}</span>
                </x-ng.option-card>
            @empty
                <x-ng.empty icon="scissors" title="Sem serviços" text="Nenhum serviço disponível nesta unidade." />
            @endforelse

            @if (! empty($servicoIds))
                <div class="flex items-center justify-between rounded-lg bg-zinc-100 px-3 py-2 text-sm dark:bg-zinc-800">
                    <span>Total: {{ $duracaoTotal }} min</span>
                    <span class="font-semibold">R$ {{ number_format($valorTotal, 2, ',', '.') }}</span>
                </div>
            @endif

            <div class="mt-auto flex gap-2 pt-2">
                @unless ($passoUnico)
                    <flux:button wire:click="voltar" variant="ghost" class="flex-1">Voltar</flux:button>
                @endunless
                <flux:button wire:click="irParaProfissional" variant="primary" class="flex-1" :disabled="empty($servicoIds)">Continuar</flux:button>
            </div>
        </div>
    @endif

    {{-- Passo 3: profissional --}}
    @if ($passo === 3)
        <div class="flex flex-1 flex-col gap-3">
            <flux:subheading>Com quem?</flux:subheading>

            <x-ng.option-card wire:click="selecionarProfissional('sem')">
                <span class="flex items-center gap-3">
                    <flux:icon name="sparkles" class="size-5 text-indigo-500" />
                    <span class="font-medium">Sem preferência</span>
                </span>
            </x-ng.option-card>

            @forelse ($profissionais as $prof)
                <x-ng.option-card wire:click="selecionarProfissional('{{ $prof->id }}')" wire:key="prof-{{ $prof->id }}">
                    <span class="flex items-center gap-3">
                        <flux:avatar size="sm" :name="$prof->name" />
                        <span class="font-medium">{{ $prof->name }}</span>
                    </span>
                </x-ng.option-card>
            @empty
                <x-ng.empty icon="user" title="Sem profissionais" text="Ninguém faz todos os serviços escolhidos nesta unidade." />
            @endforelse

            <flux:button wire:click="voltar" variant="ghost" class="mt-auto">Voltar</flux:button>
        </div>
    @endif

    {{-- Passo 4: dia e horário --}}
    @if ($passo === 4)
        <div class="flex flex-1 flex-col gap-4">
            <flux:subheading>Quando?</flux:subheading>

            <flux:input type="date" wire:model.live="data" :min="now()->format('Y-m-d')" label="Dia" />

            <div class="flex flex-col gap-2">
                <flux:text class="text-sm font-medium">Horários disponíveis</flux:text>

                {{-- Carregando --}}
                <div wire:loading.delay.flex wire:target="data" class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                    @for ($i = 0; $i < 8; $i++)
                        <div class="ng-skeleton h-9"></div>
                    @endfor
                </div>

                <div wire:loading.remove wire:target="data">
                    @if ($horarios->isEmpty())
                        <x-ng.empty icon="clock" title="Sem horários" text="Nenhum horário livre neste dia. Tente outra data." />
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
            </div>

            @if ($slotHora)
                <flux:callout icon="check-circle" variant="success" class="mt-1">
                    <flux:callout.text>Horário selecionado: <strong>{{ $slotHora }}</strong> · {{ $duracaoTotal }} min · R$ {{ number_format($valorTotal, 2, ',', '.') }}</flux:callout.text>
                </flux:callout>
            @endif

            <div class="mt-auto flex gap-2 pt-2">
                <flux:button wire:click="voltar" variant="ghost" class="flex-1">Voltar</flux:button>
                <flux:button wire:click="confirmar" variant="primary" class="flex-1" :disabled="$slotHora === null">
                    <span wire:loading.remove wire:target="confirmar">Confirmar</span>
                    <span wire:loading wire:target="confirmar">Confirmando…</span>
                </flux:button>
            </div>
        </div>
    @endif
</div>
