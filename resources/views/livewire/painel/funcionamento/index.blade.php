@php($hoje = \Carbon\Carbon::today())
<div class="flex flex-col gap-6 p-6 lg:p-8">
    <x-ng.page-header title="Funcionamento" subtitle="Horário semanal e exceções (feriados, fechamentos, horário especial)" />

    <div class="grid gap-8 lg:grid-cols-2">
        {{-- A — Horário semanal --}}
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <flux:heading size="sm">Horário semanal</flux:heading>
                <flux:button wire:click="salvarHorario" size="sm" variant="primary" icon="check">Salvar horário</flux:button>
            </div>
            <flux:text class="text-sm text-zinc-500">Faixas padrão do estabelecimento. Dias fechados não oferecem horários no portal.</flux:text>

            @error('funcionamento')
                <flux:callout variant="danger" icon="exclamation-triangle">{{ $message }}</flux:callout>
            @enderror

            {{-- MESMO editor do onboarding (fonte de verdade única). --}}
            <x-funcionamento-editor :funcionamento="$funcionamento" />
        </div>

        {{-- B — Calendário de exceções --}}
        <div class="flex flex-col gap-4">
            <flux:heading size="sm">Exceções</flux:heading>
            <flux:text class="text-sm text-zinc-500">Clique num dia (de hoje em diante) para fechar ou definir um horário especial.</flux:text>

            <div class="ng-surface flex flex-col gap-3 p-4">
                {{-- Navegação do mês --}}
                <div class="flex items-center justify-between">
                    <flux:button wire:click="navegarMes(-1)" size="sm" variant="ghost" icon="chevron-left" aria-label="Mês anterior" />
                    <span class="text-sm font-semibold capitalize" style="color: var(--cor-texto);">{{ $mesLabel }}</span>
                    <flux:button wire:click="navegarMes(1)" size="sm" variant="ghost" icon="chevron-right" aria-label="Próximo mês" />
                </div>

                {{-- Cabeçalho dos dias da semana --}}
                <div class="grid grid-cols-7 gap-1 text-center text-xs font-medium text-zinc-400">
                    @foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $d)
                        <div>{{ $d }}</div>
                    @endforeach
                </div>

                {{-- Grade --}}
                <div class="grid grid-cols-7 gap-1">
                    @foreach ($dias as $dia)
                        @php($iso = $dia->toDateString())
                        @php($exc = $excecoesMes[$iso] ?? null)
                        @php($doMes = $dia->month === $mesInicio->month)
                        @php($passado = $dia->lt($hoje))
                        @php($hojeDia = $dia->isSameDay($hoje))
                        @if ($passado || ! $doMes)
                            <div @class([
                                'flex h-10 items-center justify-center rounded-lg text-sm',
                                'text-zinc-300 dark:text-zinc-600' => true,
                            ]) @style(['opacity: 0.45' => ! $doMes])>{{ $dia->day }}</div>
                        @else
                            <button type="button" wire:click="abrirExcecao('{{ $iso }}')" wire:key="dia-{{ $iso }}"
                                @class([
                                    'relative flex h-10 items-center justify-center rounded-lg text-sm font-medium transition hover:ring-2 hover:ring-[var(--color-accent)]',
                                    'ring-1 ring-[var(--color-accent)]' => $hojeDia && ! $exc,
                                    'bg-red-500/15 text-red-700 dark:text-red-300' => $exc && $exc->tipo === 'fechado',
                                    'text-zinc-700 dark:text-zinc-200' => ! $exc,
                                ])
                                @style(['background-color: color-mix(in srgb, var(--color-accent) 18%, transparent); color: var(--color-accent)' => $exc && $exc->tipo === 'horario_especial'])
                                title="{{ $exc ? ($exc->tipo === 'fechado' ? 'Fechado' : 'Especial '.substr((string)$exc->hora_inicio,0,5).'–'.substr((string)$exc->hora_fim,0,5)).($exc->descricao ? ' · '.$exc->descricao : '') : 'Marcar exceção' }}">
                                {{ $dia->day }}
                                @if ($exc)
                                    <span class="absolute bottom-1 h-1 w-1 rounded-full {{ $exc->tipo === 'fechado' ? 'bg-red-500' : '' }}" @style(['background-color: var(--color-accent)' => $exc->tipo === 'horario_especial'])></span>
                                @endif
                            </button>
                        @endif
                    @endforeach
                </div>

                <div class="flex flex-wrap gap-3 text-xs text-zinc-500">
                    <span class="flex items-center gap-1"><span class="size-2.5 rounded-full bg-red-500"></span> Fechado</span>
                    <span class="flex items-center gap-1"><span class="size-2.5 rounded-full" style="background-color: var(--color-accent);"></span> Horário especial</span>
                </div>
            </div>

            {{-- Próximas exceções --}}
            <div class="flex flex-col gap-2">
                <flux:heading size="sm">Próximas exceções</flux:heading>
                @forelse ($excecoesProximas as $e)
                    <div class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700" wire:key="exc-{{ $e->id }}">
                        <div class="min-w-0">
                            <div class="text-sm font-medium capitalize">{{ $e->data->translatedFormat('D, d/m/Y') }}</div>
                            <div class="text-xs text-zinc-500">
                                @if ($e->tipo === 'fechado') Fechado o dia todo
                                @else Especial · {{ substr((string) $e->hora_inicio, 0, 5) }}–{{ substr((string) $e->hora_fim, 0, 5) }} @endif
                                @if ($e->descricao) · {{ $e->descricao }} @endif
                            </div>
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <flux:button wire:click="abrirExcecao('{{ $e->data->toDateString() }}')" size="xs" variant="ghost" icon="pencil" aria-label="Editar" />
                            <flux:button wire:click="removerExcecao({{ $e->id }})" size="xs" variant="ghost" icon="trash" aria-label="Remover" />
                        </div>
                    </div>
                @empty
                    <flux:text class="text-sm text-zinc-500">Nenhuma exceção marcada.</flux:text>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Modal de exceção --}}
    <flux:modal wire:model.self="mostrarExcecao" class="md:w-96">
        <form wire:submit="salvarExcecao" class="flex flex-col gap-4">
            <flux:heading size="lg">
                {{ $excecaoData ? \Carbon\Carbon::parse($excecaoData)->translatedFormat('D, d/m/Y') : 'Exceção' }}
            </flux:heading>

            <flux:radio.group wire:model.live="excecaoTipo" label="Tipo" variant="segmented">
                <flux:radio value="fechado" label="Fechado" />
                <flux:radio value="horario_especial" label="Horário especial" />
            </flux:radio.group>

            @if ($excecaoTipo === 'horario_especial')
                <div class="flex items-center gap-2">
                    <flux:input type="time" wire:model="excecaoInicio" label="Início" class="w-32" />
                    <flux:input type="time" wire:model="excecaoFim" label="Fim" class="w-32" />
                </div>
            @endif

            <flux:input wire:model="excecaoDescricao" label="Descrição (opcional)" placeholder="Ex.: Natal" maxlength="120" />

            <div class="flex justify-between gap-2">
                <div>
                    @if ($excecaoEditId)
                        <flux:button wire:click="removerExcecao({{ $excecaoEditId }})" variant="ghost" icon="trash">Remover</flux:button>
                    @endif
                </div>
                <div class="flex gap-2">
                    <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary">Salvar</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>
</div>
