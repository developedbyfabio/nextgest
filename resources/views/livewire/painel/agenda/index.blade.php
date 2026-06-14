<div class="flex flex-col gap-6 p-6 lg:p-8">
    {{-- Cabeçalho --}}
    <x-ng.page-header title="Agenda" :subtitle="$podeVerTodas ? 'Agendamentos da equipe' : 'Sua agenda'">
        @if ($podeGerir)
            <x-slot:actions>
                <livewire:painel.agenda.novo-agendamento />
            </x-slot:actions>
        @endif
    </x-ng.page-header>

    {{-- Controles --}}
    <div class="flex flex-wrap items-center gap-3">
        <flux:button.group>
            <flux:button wire:click="navegar(-1)" icon="chevron-left" size="sm" />
            <flux:button wire:click="irHoje" size="sm">Hoje</flux:button>
            <flux:button wire:click="navegar(1)" icon="chevron-right" size="sm" />
        </flux:button.group>

        <flux:input type="date" wire:model.live="data" size="sm" class="w-44" />

        <flux:button.group>
            <flux:button wire:click="$set('visao', 'dia')" size="sm" :variant="$visao === 'dia' ? 'primary' : 'outline'">Dia</flux:button>
            <flux:button wire:click="$set('visao', 'semana')" size="sm" :variant="$visao === 'semana' ? 'primary' : 'outline'">Semana</flux:button>
        </flux:button.group>

        <flux:spacer />

        <div wire:loading class="text-sm text-zinc-400">Atualizando…</div>

        @if ($podeVerTodas)
            <flux:select wire:model.live="filtroProfissional" size="sm" placeholder="Todos os profissionais" class="w-48">
                <flux:select.option value="">Todos os profissionais</flux:select.option>
                @foreach ($profissionais as $prof)
                    <flux:select.option value="{{ $prof->id }}">{{ $prof->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif

        @if ($unidades->count() > 1)
            <flux:select wire:model.live="filtroUnidade" size="sm" placeholder="Todas as unidades" class="w-44">
                <flux:select.option value="">Todas as unidades</flux:select.option>
                @foreach ($unidades as $unidade)
                    <flux:select.option value="{{ $unidade->id }}">{{ $unidade->nome }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif

        <flux:select wire:model.live="filtroStatus" size="sm" placeholder="Todos os status" class="w-44">
            <flux:select.option value="">Todos os status</flux:select.option>
            @foreach ($statusLabel as $valor => $rotulo)
                <flux:select.option value="{{ $valor }}">{{ $rotulo }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- Carregando --}}
    <x-ng.skeleton :rows="5" wire:loading.delay.flex wire:target="data,visao,filtroProfissional,filtroUnidade,filtroStatus,navegar,irHoje" />

    {{-- Conteúdo --}}
    <div wire:loading.remove wire:target="data,visao,filtroProfissional,filtroUnidade,filtroStatus,navegar,irHoje">
    @if ($visao === 'dia')
        <div class="flex flex-col gap-2">
            @forelse ($agendamentos as $ag)
                <button type="button" wire:click="abrirDetalhe({{ $ag->id }})"
                    class="ng-card-interactive flex items-center gap-4 p-3">
                    <div class="w-24 shrink-0 font-mono text-sm">
                        {{ $ag->data_hora_inicio->format('H:i') }}–{{ $ag->data_hora_fim->format('H:i') }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium">{{ $ag->cliente?->nome ?? 'Cliente' }}</div>
                        <div class="truncate text-sm text-zinc-500">
                            {{ $ag->itens->map(fn ($i) => $i->servico?->nome)->filter()->join(', ') }}
                            · {{ $ag->profissional?->name }}
                        </div>
                    </div>
                    <flux:badge :color="$statusCor[$ag->status]" size="sm">{{ $statusLabel[$ag->status] }}</flux:badge>
                </button>
            @empty
                <x-ng.empty icon="calendar" title="Nenhum agendamento" text="Não há agendamentos para os filtros deste dia." />
            @endforelse
        </div>
    @else
        {{-- Semana --}}
        <div class="grid grid-cols-1 gap-3 md:grid-cols-7">
            @foreach ($diasSemana as $dia)
                <div class="flex flex-col gap-2 rounded-lg border border-zinc-200 p-2 dark:border-zinc-700">
                    <div class="text-center text-sm font-semibold capitalize">
                        {{ $dia->translatedFormat('D') }}
                        <span class="block text-xs font-normal text-zinc-500">{{ $dia->format('d/m') }}</span>
                    </div>
                    @php($doDia = $agendamentos->filter(fn ($a) => $a->data_hora_inicio->isSameDay($dia)))
                    @forelse ($doDia as $ag)
                        @php($corBorda = match ($ag->status) {
                            'pendente' => 'border-amber-500',
                            'confirmado' => 'border-green-500',
                            'em_andamento' => 'border-blue-500',
                            'concluido' => 'border-teal-500',
                            'cancelado' => 'border-red-500',
                            'nao_compareceu' => 'border-orange-500',
                            default => 'border-zinc-300',
                        })
                        <button type="button" wire:click="abrirDetalhe({{ $ag->id }})"
                            class="rounded-md border-l-4 {{ $corBorda }} bg-zinc-50 p-1.5 text-left text-xs transition hover:bg-zinc-100 dark:bg-zinc-900 dark:hover:bg-zinc-800">
                            <div class="font-mono">{{ $ag->data_hora_inicio->format('H:i') }}</div>
                            <div class="truncate">{{ $ag->cliente?->nome ?? 'Cliente' }}</div>
                        </button>
                    @empty
                        <div class="py-2 text-center text-xs text-zinc-300">—</div>
                    @endforelse
                </div>
            @endforeach
        </div>
    @endif
    </div>

    {{-- Slide-over: detalhe do agendamento --}}
    <flux:modal variant="flyout" position="right" wire:model.self="mostrarDetalhe" class="md:w-[28rem]">
        @if ($detalhe)
            <div class="flex flex-col gap-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <flux:heading size="lg">{{ $detalhe->cliente?->nome ?? 'Cliente' }}</flux:heading>
                        <flux:subheading>{{ $detalhe->data_hora_inicio->translatedFormat('l, d/m/Y') }}</flux:subheading>
                    </div>
                    <flux:badge :color="$statusCor[$detalhe->status]">{{ $statusLabel[$detalhe->status] }}</flux:badge>
                </div>

                <div class="flex flex-col gap-2 text-sm">
                    <div class="flex justify-between"><span class="text-zinc-500">Horário</span><span>{{ $detalhe->data_hora_inicio->format('H:i') }}–{{ $detalhe->data_hora_fim->format('H:i') }}</span></div>
                    <div class="flex justify-between"><span class="text-zinc-500">Profissional</span><span>{{ $detalhe->profissional?->name }}</span></div>
                    <div class="flex justify-between"><span class="text-zinc-500">Unidade</span><span>{{ $detalhe->unidade?->nome }}</span></div>
                    <div class="flex justify-between"><span class="text-zinc-500">Origem</span><span class="capitalize">{{ $detalhe->origem }}</span></div>
                    <div class="flex justify-between"><span class="text-zinc-500">Valor</span><span>R$ {{ number_format((float) $detalhe->valor_total, 2, ',', '.') }}</span></div>
                </div>

                <flux:separator />

                <div>
                    <flux:text class="mb-1 text-sm font-medium">Serviços</flux:text>
                    <ul class="text-sm text-zinc-600 dark:text-zinc-300">
                        @foreach ($detalhe->itens as $item)
                            <li class="flex justify-between">
                                <span>{{ $item->servico?->nome ?? 'Serviço' }} ({{ $item->duracao_minutos }} min)</span>
                                <span>R$ {{ number_format((float) $item->preco, 2, ',', '.') }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                @if ($podeGerir)
                    <flux:separator />

                    @unless ($modoRemarcar)
                        {{-- Ações de status (só transições permitidas) --}}
                        <div class="flex flex-wrap gap-2">
                            @foreach (\App\Models\Agendamento::TRANSICOES[$detalhe->status] as $proximo)
                                @if ($proximo === 'cancelado')
                                    <flux:button wire:click="mudarStatus('cancelado')" wire:confirm="Cancelar este agendamento? O horário será liberado." size="sm" variant="danger">Cancelar</flux:button>
                                @else
                                    <flux:button wire:click="mudarStatus('{{ $proximo }}')" size="sm" variant="primary">{{ $statusLabel[$proximo] }}</flux:button>
                                @endif
                            @endforeach
                        </div>

                        @if (! in_array($detalhe->status, \App\Models\Agendamento::STATUS_LIVRES, true) && $detalhe->status !== 'concluido')
                            <flux:button wire:click="iniciarRemarcacao" size="sm" variant="subtle" icon="arrow-path">Remarcar</flux:button>
                        @endif
                    @else
                        {{-- Modo remarcar --}}
                        <div class="flex flex-col gap-3">
                            <flux:heading size="sm">Remarcar</flux:heading>
                            <flux:input type="date" wire:model.live="remarcarData" :min="now()->format('Y-m-d')" label="Novo dia" />
                            @if ($horariosRemarcar->isEmpty())
                                <flux:text class="text-sm text-zinc-500">Sem horários livres neste dia.</flux:text>
                            @else
                                <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                                    @foreach ($horariosRemarcar as $slot)
                                        <flux:button wire:click="confirmarRemarcacao('{{ $slot['hora'] }}')" size="sm" variant="outline">{{ $slot['hora'] }}</flux:button>
                                    @endforeach
                                </div>
                            @endif
                            <flux:button wire:click="cancelarRemarcacao" size="sm" variant="ghost">Voltar</flux:button>
                        </div>
                    @endunless
                @endif
            </div>
        @endif
    </flux:modal>
</div>
