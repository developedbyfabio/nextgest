@php($alvosFiltro = 'periodo,dataInicio,dataFim,profissionalId')
<div class="flex flex-col gap-6 p-6 lg:p-8">
    {{-- Cabeçalho + filtros --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" style="color: var(--cor-texto);">Indicadores</flux:heading>
            <flux:subheading style="color: var(--cor-texto-suave);">
                Retenção e frequência dos clientes · {{ $inicio->format('d/m/Y') }} – {{ $fim->format('d/m/Y') }}
            </flux:subheading>
        </div>

        <div class="flex flex-wrap items-end gap-3">
            <flux:icon name="arrow-path" wire:loading.delay wire:target="{{ $alvosFiltro }}" class="size-5 animate-spin self-center" style="color: var(--cor-principal);" />

            <flux:select wire:model.live="profissionalId" label="Profissional" class="min-w-44">
                <flux:select.option value="">Todos</flux:select.option>
                @foreach ($profissionais as $p)
                    <flux:select.option :value="$p->id">{{ $p->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="periodo" label="Período" class="min-w-40">
                <flux:select.option value="hoje">Hoje</flux:select.option>
                <flux:select.option value="7d">Últimos 7 dias</flux:select.option>
                <flux:select.option value="30d">Últimos 30 dias</flux:select.option>
                <flux:select.option value="90d">Últimos 90 dias</flux:select.option>
                <flux:select.option value="mes">Mês atual</flux:select.option>
                <flux:select.option value="custom">Personalizado</flux:select.option>
            </flux:select>

            @if ($periodo === 'custom')
                <flux:input type="date" wire:model.live="dataInicio" label="De" />
                <flux:input type="date" wire:model.live="dataFim" label="Até" />
            @endif
        </div>
    </div>

    @if ($erro)
        <div class="ng-surface flex flex-col items-center gap-3 px-6 py-12 text-center">
            <span class="flex size-12 items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-red-500/15">
                <flux:icon name="exclamation-triangle" class="size-6" />
            </span>
            <div>
                <flux:heading size="sm" style="color: var(--cor-texto);">Não foi possível carregar os indicadores</flux:heading>
                <flux:text class="text-sm" style="color: var(--cor-texto-suave);">Tente novamente em instantes.</flux:text>
            </div>
            <flux:button wire:click="$refresh" variant="primary" icon="arrow-path" size="sm">Tentar de novo</flux:button>
        </div>
    @else
        <flux:text class="text-xs" style="color: var(--cor-texto-suave);">
            Risco e frequência refletem o histórico de visitas pagas (não dependem do período). Ticket médio e retenção seguem os filtros acima.
        </flux:text>

        {{-- 4 cards --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {{-- RISCO (clicável → drill-in) --}}
            <button type="button" wire:click="abrirRisco" class="text-left">
                <div class="ng-surface ng-surface-interactive flex h-full flex-col gap-3 p-5 {{ $mostrarRisco ? 'ring-2' : '' }}" style="{{ $mostrarRisco ? 'box-shadow: 0 0 0 2px var(--cor-principal);' : '' }}">
                    <div class="flex items-start justify-between gap-2">
                        <flux:text class="text-sm font-medium" style="color: var(--cor-texto-suave);">Clientes sumindo</flux:text>
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-500/15">
                            <flux:icon name="exclamation-triangle" class="size-5" />
                        </span>
                    </div>
                    <div class="text-3xl font-bold tracking-tight tabular-nums" style="color: var(--cor-texto);">{{ $risco?->total() ?? 0 }}</div>
                    <flux:text class="text-xs" style="color: var(--cor-texto-suave);">
                        @if (($risco?->total() ?? 0) > 0 && isset($risco->items()[0]))
                            mais atrasado: {{ (int) round($risco->items()[0]->dias_desde_ultima) }} dias · ver lista
                        @else
                            ninguém em risco · clique para detalhes
                        @endif
                    </flux:text>
                </div>
            </button>

            {{-- TICKET MÉDIO --}}
            <x-ng.indicador
                titulo="Ticket médio"
                :valor="'R$ '.number_format($ticket, 2, ',', '.')"
                :sub="$profissionalId ? 'no período · profissional filtrado' : 'no período'"
                icone="banknotes"
            />

            {{-- RETENÇÃO --}}
            <x-ng.indicador
                titulo="Retenção"
                :valor="number_format($retencao['taxa'], 1, ',', '.').'%'"
                :sub="$retencao['voltaram'].' de '.$retencao['base'].' voltaram (vs. período anterior)'"
                icone="arrow-path-rounded-square"
            />

            {{-- FREQUÊNCIA (buckets clicáveis) --}}
            <div class="ng-surface flex flex-col gap-3 p-5">
                <div class="flex items-start justify-between gap-2">
                    <flux:text class="text-sm font-medium" style="color: var(--cor-texto-suave);">Frequência</flux:text>
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl" style="background-color: color-mix(in srgb, var(--cor-principal) 14%, transparent); color: var(--cor-principal);">
                        <flux:icon name="users" class="size-5" />
                    </span>
                </div>
                @php($rotuloBucket = ['sempre' => 'Vai sempre', 'regular' => 'Regular', 'esporadico' => 'Esporádico', 'novos' => 'Novos / poucos dados'])
                <div class="flex flex-col gap-1">
                    @foreach (['sempre', 'regular', 'esporadico', 'novos'] as $bk)
                        <button type="button" wire:click="abrirBucket('{{ $bk }}')"
                            class="flex items-center justify-between rounded-lg px-2 py-1 text-left transition hover:bg-[color-mix(in_srgb,var(--cor-texto)_6%,transparent)] {{ $bucketAberto === $bk ? 'bg-[color-mix(in_srgb,var(--cor-principal)_12%,transparent)]' : '' }}">
                            <flux:text class="text-sm" style="color: var(--cor-texto);">{{ $rotuloBucket[$bk] }}</flux:text>
                            <flux:text class="text-sm font-semibold tabular-nums" style="color: var(--cor-texto);">{{ $frequencia[$bk] }}</flux:text>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- DRILL-IN (seção expandível, uma por vez) --}}
        @if (($mostrarRisco || $bucketAberto !== null) && $listaDrill)
            <div class="ng-surface flex flex-col gap-4 p-5">
                <div class="flex items-center justify-between gap-2">
                    <flux:heading size="lg" style="color: var(--cor-texto);">
                        @if ($mostrarRisco)
                            Clientes sumindo ({{ $listaDrill->total() }})
                        @else
                            {{ $rotuloBucket[$bucketAberto] ?? 'Clientes' }} ({{ $listaDrill->total() }})
                        @endif
                    </flux:heading>
                    <flux:button wire:click="fecharDrill" variant="ghost" size="sm" icon="x-mark">Fechar</flux:button>
                </div>

                @if ($listaDrill->total() === 0)
                    <x-ng.empty icon="user" title="Nenhum cliente aqui" text="Sem clientes neste segmento por enquanto." />
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr style="color: var(--cor-texto-suave);" class="text-left">
                                    <th class="py-2 pr-4 font-medium">Cliente</th>
                                    <th class="py-2 pr-4 font-medium tabular-nums">Visitas</th>
                                    <th class="py-2 pr-4 font-medium tabular-nums">Intervalo médio</th>
                                    @if ($mostrarRisco)
                                        <th class="py-2 pr-4 font-medium tabular-nums">Dias sem vir</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($listaDrill->items() as $linha)
                                    <tr style="border-top: 1px solid color-mix(in srgb, var(--cor-texto) 10%, transparent);">
                                        <td class="py-2 pr-4" style="color: var(--cor-texto);">{{ $nomes[$linha->cliente_id] ?? 'Cliente #'.$linha->cliente_id }}</td>
                                        <td class="py-2 pr-4 tabular-nums" style="color: var(--cor-texto);">{{ (int) $linha->visitas }}</td>
                                        <td class="py-2 pr-4 tabular-nums" style="color: var(--cor-texto);">{{ is_null($linha->intervalo_medio) ? '—' : (int) round($linha->intervalo_medio).' dias' }}</td>
                                        @if ($mostrarRisco)
                                            <td class="py-2 pr-4 tabular-nums" style="color: var(--cor-texto);">{{ (int) round($linha->dias_desde_ultima) }}</td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div>{{ $listaDrill->links() }}</div>
                @endif
            </div>
        @endif
    @endif
</div>
