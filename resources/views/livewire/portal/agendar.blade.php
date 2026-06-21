@php
    $passoUnico = $unidades->count() === 1;
    $totalPassos = $passoUnico ? 3 : 4;
    $passoAtual = $passoUnico ? $passo - 1 : $passo;
    $rotuloPasso = [1 => 'Unidade', 2 => 'Serviços', 3 => 'Profissional', 4 => 'Data e horário'][$passo] ?? '';
@endphp

<div class="flex min-h-[78vh] flex-col gap-5">
    {{-- Cabeçalho --}}
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <flux:heading size="lg">Novo agendamento</flux:heading>
            <flux:text class="mt-0.5 text-sm" style="color: var(--cor-texto-suave);">
                Passo {{ $passoAtual }} de {{ $totalPassos }} · {{ $rotuloPasso }}
            </flux:text>
        </div>
        <flux:button :href="route('tenant.home', ['tenant' => tenant('id')])" size="sm" variant="ghost" icon="x-mark" wire:navigate aria-label="Fechar" />
    </div>

    {{-- Indicador de progresso --}}
    <div class="flex items-center gap-1.5" role="progressbar" aria-valuenow="{{ $passoAtual }}" aria-valuemin="1" aria-valuemax="{{ $totalPassos }}" aria-label="Progresso do agendamento">
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
        <div class="ng-fade-in flex flex-col gap-3" wire:key="step-1">
            <flux:subheading>Escolha a unidade</flux:subheading>
            @foreach ($unidades as $unidade)
                <x-ng.option-card themed wire:click="selecionarUnidade({{ $unidade->id }})" wire:key="uni-{{ $unidade->id }}">
                    <span class="flex items-center gap-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg" style="background-color: color-mix(in srgb, var(--cor-principal) 12%, transparent); color: var(--cor-principal);">
                            <flux:icon name="map-pin" class="size-5" />
                        </span>
                        <span class="min-w-0">
                            <span class="block font-medium">{{ $unidade->nome }}</span>
                            @if ($unidade->endereco)
                                <span class="block truncate text-sm" style="color: var(--cor-texto-suave);">{{ $unidade->endereco }}</span>
                            @endif
                        </span>
                    </span>
                </x-ng.option-card>
            @endforeach
        </div>
    @endif

    {{-- Passo 2: serviços --}}
    @if ($passo === 2)
        <div class="ng-fade-in flex flex-1 flex-col gap-3" wire:key="step-2">
            <flux:subheading>Quais serviços?</flux:subheading>

            @forelse ($servicosDisponiveis as $servico)
                <x-ng.option-card themed :selected="in_array($servico->id, $servicoIds, true)" wire:click="toggleServico({{ $servico->id }})" wire:key="srv-{{ $servico->id }}">
                    <span class="block font-medium">{{ $servico->nome }}</span>
                    <span class="mt-0.5 flex items-center gap-2 text-sm" style="color: var(--cor-texto-suave);">
                        <flux:icon name="clock" class="size-4" />{{ $servico->duracao_minutos }} min
                        <span aria-hidden="true">·</span>
                        <span class="font-medium" style="color: var(--cor-texto);">R$ {{ number_format((float) $servico->preco, 2, ',', '.') }}</span>
                    </span>
                </x-ng.option-card>
            @empty
                <x-ng.empty themed icon="scissors" title="Sem serviços" text="Nenhum serviço disponível nesta unidade." />
            @endforelse

            @if (! empty($servicoIds))
                <div class="ng-fade-in flex items-center justify-between rounded-xl px-4 py-3 text-sm" style="background-color: color-mix(in srgb, var(--cor-principal) 8%, var(--cor-superficie)); color: var(--cor-texto);">
                    <span class="flex items-center gap-1.5" style="color: var(--cor-texto-suave);">
                        <flux:icon name="clock" class="size-4" /> {{ $duracaoTotal }} min
                    </span>
                    <span class="text-base font-semibold">R$ {{ number_format($valorTotal, 2, ',', '.') }}</span>
                </div>
            @endif

            <div class="mt-auto flex gap-2 pt-2">
                @unless ($passoUnico)
                    <flux:button wire:click="voltar" variant="ghost" class="flex-1">Voltar</flux:button>
                @endunless
                <flux:button wire:click="irParaProfissional" variant="primary" class="flex-1" icon-trailing="arrow-right" :disabled="empty($servicoIds)">Continuar</flux:button>
            </div>
        </div>
    @endif

    {{-- Passo 3: profissional --}}
    @if ($passo === 3)
        <div class="ng-fade-in flex flex-1 flex-col gap-3" wire:key="step-3">
            <flux:subheading>Com quem?</flux:subheading>

            <x-ng.option-card themed wire:click="selecionarProfissional('sem')">
                <span class="flex items-center gap-3">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full" style="background-color: color-mix(in srgb, var(--cor-principal) 12%, transparent); color: var(--cor-principal);">
                        <flux:icon name="sparkles" class="size-5" />
                    </span>
                    <span class="min-w-0">
                        <span class="block font-medium">Sem preferência</span>
                        <span class="block text-sm" style="color: var(--cor-texto-suave);">Atende quem estiver disponível mais cedo</span>
                    </span>
                </span>
            </x-ng.option-card>

            @forelse ($profissionais as $prof)
                <x-ng.option-card themed wire:click="selecionarProfissional('{{ $prof->id }}')" wire:key="prof-{{ $prof->id }}">
                    <span class="flex items-center gap-3">
                        <flux:avatar size="sm" :name="$prof->name" />
                        <span class="font-medium">{{ $prof->name }}</span>
                    </span>
                </x-ng.option-card>
            @empty
                <x-ng.empty themed icon="user" title="Sem profissionais" text="Ninguém faz todos os serviços escolhidos nesta unidade." />
            @endforelse

            <flux:button wire:click="voltar" variant="ghost" class="mt-auto">Voltar</flux:button>
        </div>
    @endif

    {{-- Passo 4: dia e horário --}}
    @if ($passo === 4)
        <div class="ng-fade-in flex flex-1 flex-col gap-4" wire:key="step-4">
            <flux:subheading>Quando?</flux:subheading>

            <flux:input type="date" wire:model.live="data" :min="now()->format('Y-m-d')" label="Dia" icon="calendar-days" />

            <div class="flex flex-col gap-2">
                <flux:text class="text-sm font-medium">Horários disponíveis</flux:text>

                {{-- Carregando (skeleton temático) --}}
                <div wire:loading.delay.flex wire:target="data" class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                    @for ($i = 0; $i < 8; $i++)
                        <div class="ng-skeleton-portal h-10"></div>
                    @endfor
                </div>

                <div wire:loading.remove wire:target="data">
                    @if ($horarios->isEmpty())
                        <x-ng.empty themed icon="clock" title="Sem horários" text="Nenhum horário livre neste dia. Tente outra data." />
                    @else
                        <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                            @foreach ($horarios as $slot)
                                @php($ativo = $slotHora === $slot['hora'])
                                <button type="button"
                                    wire:click="selecionarSlot('{{ $slot['hora'] }}', {{ $slot['profissional_id'] }})"
                                    wire:key="slot-{{ $slot['hora'] }}"
                                    aria-pressed="{{ $ativo ? 'true' : 'false' }}"
                                    class="rounded-lg border py-2 text-sm font-medium transition duration-150 ease-out hover:-translate-y-0.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-accent)]"
                                    @style([
                                        'background-color: var(--cor-principal); color: var(--cor-sobre-principal); border-color: var(--cor-principal)' => $ativo,
                                        'background-color: var(--cor-superficie); color: var(--cor-texto); border-color: color-mix(in srgb, var(--cor-texto) 14%, transparent)' => ! $ativo,
                                    ])>
                                    {{ $slot['hora'] }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            @if ($slotHora)
                <div class="ng-fade-in flex items-center gap-3 rounded-xl border p-4" style="border-color: color-mix(in srgb, var(--cor-principal) 30%, transparent); background-color: color-mix(in srgb, var(--cor-principal) 8%, var(--cor-superficie));">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full" style="background-color: var(--cor-principal); color: var(--cor-sobre-principal);">
                        <flux:icon name="check" class="size-5" />
                    </span>
                    <div class="min-w-0 text-sm">
                        <div class="font-semibold">{{ \Illuminate\Support\Carbon::parse($data)->translatedFormat('D, d/m') }} às {{ $slotHora }}</div>
                        <div style="color: var(--cor-texto-suave);">{{ $duracaoTotal }} min · R$ {{ number_format($valorTotal, 2, ',', '.') }}</div>
                    </div>
                </div>
            @endif

            <div class="mt-auto flex gap-2 pt-2">
                <flux:button wire:click="voltar" variant="ghost" class="flex-1">Voltar</flux:button>
                <flux:button wire:click="confirmar" variant="primary" class="flex-1" icon="check" :disabled="$slotHora === null">
                    <span wire:loading.remove wire:target="confirmar">Confirmar</span>
                    <span wire:loading wire:target="confirmar">Confirmando…</span>
                </flux:button>
            </div>
        </div>
    @endif
</div>
