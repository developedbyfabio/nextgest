@php($alvos = 'data,visao,filtroProfissional,filtroUnidade,filtroStatus,navegar,irHoje')
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
            <flux:button wire:click="navegar(-1)" icon="chevron-left" size="sm" aria-label="Anterior" />
            <flux:button wire:click="irHoje" size="sm">Hoje</flux:button>
            <flux:button wire:click="navegar(1)" icon="chevron-right" size="sm" aria-label="Próximo" />
        </flux:button.group>

        <flux:input type="date" wire:model.live="data" size="sm" class="w-44" />

        <flux:button.group>
            <flux:button wire:click="$set('visao', 'dia')" size="sm" :variant="$visao === 'dia' ? 'primary' : 'outline'">Dia</flux:button>
            <flux:button wire:click="$set('visao', 'semana')" size="sm" :variant="$visao === 'semana' ? 'primary' : 'outline'">Semana</flux:button>
        </flux:button.group>

        <flux:icon name="arrow-path" wire:loading.delay wire:target="{{ $alvos }}" class="size-5 animate-spin self-center" style="color: var(--cor-principal);" />

        <flux:spacer />

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

    {{-- Carregando: skeleton --}}
    <div wire:loading.delay.flex wire:target="{{ $alvos }}" class="flex-col gap-2">
        @for ($i = 0; $i < 5; $i++)
            <div class="ng-skeleton-portal h-16 w-full"></div>
        @endfor
    </div>

    {{-- Conteúdo --}}
    <div wire:loading.remove.delay wire:target="{{ $alvos }}" class="flex flex-col gap-4">
        @if ($erro)
            <div class="ng-surface flex flex-col items-center gap-3 px-6 py-12 text-center">
                <span class="flex size-12 items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-red-500/15">
                    <flux:icon name="exclamation-triangle" class="size-6" />
                </span>
                <div>
                    <flux:heading size="sm" style="color: var(--cor-texto);">Não foi possível carregar a agenda</flux:heading>
                    <flux:text class="text-sm" style="color: var(--cor-texto-suave);">Tente novamente em instantes.</flux:text>
                </div>
                <flux:button wire:click="$refresh" variant="primary" icon="arrow-path" size="sm">Tentar de novo</flux:button>
            </div>
        @elseif ($visao === 'dia')
            {{-- Visão de DIA: lista de cartões com acento de status --}}
            <div class="flex flex-col gap-2">
                @forelse ($agendamentos as $ag)
                    <button type="button" wire:click="abrirDetalhe({{ $ag->id }})" wire:key="ag-{{ $ag->id }}"
                        class="ng-surface ng-surface-interactive flex items-stretch gap-0 overflow-hidden p-0 text-left">
                        <span class="w-1.5 shrink-0" style="background-color: {{ $statusHex[$ag->status] ?? '#a1a1aa' }};"></span>
                        <span class="flex min-w-0 flex-1 items-center gap-4 p-3">
                            <span class="w-24 shrink-0 text-sm font-semibold tabular-nums" style="color: var(--cor-texto);">
                                {{ $ag->data_hora_inicio->format('H:i') }}<span style="color: var(--cor-texto-suave);">–{{ $ag->data_hora_fim->format('H:i') }}</span>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium" style="color: var(--cor-texto);">{{ $ag->cliente?->nome ?? 'Cliente' }}</span>
                                <span class="block truncate text-sm" style="color: var(--cor-texto-suave);">
                                    {{ $ag->itens->map(fn ($i) => $i->servico?->nome)->filter()->join(', ') ?: 'Serviço' }}
                                    @if ($podeVerTodas) · {{ $ag->profissional?->name }} @endif
                                </span>
                            </span>
                            <flux:badge :color="$statusCor[$ag->status]" size="sm">{{ $statusLabel[$ag->status] }}</flux:badge>
                        </span>
                    </button>
                @empty
                    <x-ng.empty themed icon="calendar-days" title="Dia livre"
                        text="Nenhum agendamento para os filtros deste dia." />
                @endforelse
            </div>
        @else
            {{-- Visão de SEMANA: scroll horizontal com snap no mobile, grade no desktop --}}
            <div class="flex snap-x snap-mandatory gap-3 overflow-x-auto pb-2 md:grid md:grid-cols-7 md:overflow-visible">
                @foreach ($diasSemana as $dia)
                    @php($hoje = $dia->isToday())
                    <div class="ng-surface-muted flex w-[44vw] shrink-0 snap-start flex-col gap-2 rounded-xl p-2 sm:w-52 md:w-auto">
                        <div class="rounded-lg px-2 py-1 text-center" @if ($hoje) style="background-color: color-mix(in srgb, var(--cor-principal) 14%, transparent);" @endif>
                            <span class="text-sm font-semibold capitalize" style="color: {{ $hoje ? 'var(--cor-principal)' : 'var(--cor-texto)' }};">{{ $dia->translatedFormat('D') }}</span>
                            <span class="block text-xs" style="color: var(--cor-texto-suave);">{{ $dia->format('d/m') }}</span>
                        </div>
                        @php($doDia = $agendamentos->filter(fn ($a) => $a->data_hora_inicio->isSameDay($dia)))
                        @forelse ($doDia as $ag)
                            <button type="button" wire:click="abrirDetalhe({{ $ag->id }})" wire:key="ws-{{ $ag->id }}"
                                class="ng-surface ng-surface-interactive overflow-hidden p-0 text-left">
                                <span class="block border-s-4 px-2 py-1.5" style="border-color: {{ $statusHex[$ag->status] ?? '#a1a1aa' }};">
                                    <span class="block text-xs font-semibold tabular-nums" style="color: var(--cor-texto);">{{ $ag->data_hora_inicio->format('H:i') }}</span>
                                    <span class="block truncate text-xs" style="color: var(--cor-texto-suave);">{{ $ag->cliente?->nome ?? 'Cliente' }}</span>
                                </span>
                            </button>
                        @empty
                            <div class="py-3 text-center text-xs" style="color: color-mix(in srgb, var(--cor-texto) 35%, transparent);">—</div>
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
                        <flux:heading size="lg" style="color: var(--cor-texto);">{{ $detalhe->cliente?->nome ?? 'Cliente' }}</flux:heading>
                        <flux:subheading style="color: var(--cor-texto-suave);">{{ $detalhe->data_hora_inicio->translatedFormat('l, d/m/Y') }}</flux:subheading>
                    </div>
                    <flux:badge :color="$statusCor[$detalhe->status]">{{ $statusLabel[$detalhe->status] }}</flux:badge>
                </div>

                <div class="flex flex-col gap-2 text-sm">
                    @php($linhas = [
                        ['Horário', $detalhe->data_hora_inicio->format('H:i').'–'.$detalhe->data_hora_fim->format('H:i')],
                        ['Profissional', $detalhe->profissional?->name],
                        ['Unidade', $detalhe->unidade?->nome],
                        ['Origem', ucfirst($detalhe->origem)],
                        ['Valor', 'R$ '.number_format((float) $detalhe->valor_total, 2, ',', '.')],
                    ])
                    @foreach ($linhas as [$rotulo, $valor])
                        <div class="flex justify-between">
                            <span style="color: var(--cor-texto-suave);">{{ $rotulo }}</span>
                            <span style="color: var(--cor-texto);">{{ $valor }}</span>
                        </div>
                    @endforeach
                </div>

                <flux:separator />

                <div>
                    <flux:text class="mb-1 text-sm font-medium" style="color: var(--cor-texto);">Serviços</flux:text>
                    <ul class="text-sm" style="color: var(--cor-texto-suave);">
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
                                    <flux:button wire:click="pedirCancelar" size="sm" variant="danger" icon="x-mark">Cancelar</flux:button>
                                @else
                                    <flux:button wire:click="mudarStatus('{{ $proximo }}')" size="sm" variant="primary">{{ $statusLabel[$proximo] }}</flux:button>
                                @endif
                            @endforeach
                        </div>

                        @if (! in_array($detalhe->status, \App\Models\Agendamento::STATUS_LIVRES, true) && $detalhe->status !== 'concluido')
                            <flux:button wire:click="iniciarRemarcacao" size="sm" variant="subtle" icon="arrow-path">Remarcar</flux:button>
                        @endif

                        {{-- Gerar comanda do atendimento concluído (financeiro, Fatia 2B) --}}
                        @if ($detalhe->status === 'concluido')
                            @can('criar_venda')
                                <flux:button wire:click="gerarComanda" size="sm" variant="primary" icon="shopping-cart">Gerar comanda</flux:button>
                            @endcan
                        @endif
                    @else
                        {{-- Modo remarcar --}}
                        <div class="flex flex-col gap-3">
                            <flux:heading size="sm" style="color: var(--cor-texto);">Remarcar</flux:heading>
                            <flux:input type="date" wire:model.live="remarcarData" :min="now()->format('Y-m-d')" label="Novo dia" />
                            @if ($horariosRemarcar->isEmpty())
                                <flux:text class="text-sm" style="color: var(--cor-texto-suave);">Sem horários livres neste dia.</flux:text>
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

    {{-- Modal: confirmar cancelamento (sem confirm nativo) --}}
    <flux:modal name="cancelar-agendamento" class="max-w-sm">
        <div class="flex flex-col gap-4">
            <div class="flex items-center gap-3">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-red-500/15">
                    <flux:icon name="exclamation-triangle" class="size-6" />
                </span>
                <div>
                    <flux:heading size="lg">Cancelar agendamento?</flux:heading>
                    <flux:text class="mt-1">O horário será liberado na agenda. Esta ação não pode ser desfeita.</flux:text>
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Voltar</flux:button></flux:modal.close>
                <flux:button wire:click="cancelarAgendamento" variant="danger" icon="x-mark">Cancelar agendamento</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
