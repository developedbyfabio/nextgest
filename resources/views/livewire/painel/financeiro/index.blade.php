@php($alvosFiltro = 'periodo,dataInicio,dataFim,profissionalId,unidadeId,forma')
<div class="flex flex-col gap-6 p-6 lg:p-8">
    {{-- Cabeçalho --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" style="color: var(--cor-texto);">Financeiro</flux:heading>
            <flux:subheading style="color: var(--cor-texto-suave);">
                Números do negócio · {{ $inicio->format('d/m/Y') }} – {{ $fim->format('d/m/Y') }}
            </flux:subheading>
        </div>
        <div class="flex flex-wrap items-end gap-3">
            <flux:icon name="arrow-path" wire:loading.delay wire:target="{{ $alvosFiltro }}" class="size-5 animate-spin self-center" style="color: var(--cor-principal);" />
            <flux:button wire:click="exportarCsv" variant="primary" icon="arrow-down-tray">Exportar CSV</flux:button>
        </div>
    </div>

    {{-- Banner de responsabilidade (FIXO) — não é cálculo de imposto. --}}
    <flux:callout icon="information-circle" color="amber">
        <flux:callout.heading>Para organização e para o contador — não é cálculo de impostos</flux:callout.heading>
        <flux:callout.text>{{ $aviso }}</flux:callout.text>
    </flux:callout>

    {{-- Filtros --}}
    <div class="flex flex-wrap items-end gap-3">
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
        <flux:select wire:model.live="profissionalId" label="Profissional" class="min-w-44">
            <flux:select.option value="">Todos</flux:select.option>
            @foreach ($profissionais as $p)
                <flux:select.option :value="$p->id">{{ $p->name }}</flux:select.option>
            @endforeach
        </flux:select>
        @if ($multiUnidade)
            <flux:select wire:model.live="unidadeId" label="Unidade" class="min-w-44">
                <flux:select.option value="">Todas</flux:select.option>
                @foreach ($unidades as $u)
                    <flux:select.option :value="$u->id">{{ $u->nome }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif
        <flux:select wire:model.live="forma" label="Forma (recebimentos)" class="min-w-44">
            <flux:select.option value="">Todas</flux:select.option>
            @foreach ($metodos as $valor => $rotulo)
                <flux:select.option :value="$valor">{{ $rotulo }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if ($erro)
        <div class="ng-surface flex flex-col items-center gap-3 px-6 py-12 text-center">
            <flux:icon name="exclamation-triangle" class="size-8 text-red-500" />
            <flux:heading size="sm" style="color: var(--cor-texto);">Não foi possível carregar o financeiro</flux:heading>
            <flux:button wire:click="$refresh" variant="primary" icon="arrow-path" size="sm">Tentar de novo</flux:button>
        </div>
    @else
        {{-- Cards --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ng.indicador titulo="Faturamento (receita bruta)" :valor="'R$ '.number_format($totais['faturamento'], 2, ',', '.')" sub="comandas pagas no período" icone="banknotes" />
            <x-ng.indicador titulo="Lucro bruto" :valor="'R$ '.number_format($lucroBruto, 2, ',', '.')" sub="receita − comissões − CPV" icone="chart-bar-square" />
            <x-ng.indicador titulo="Ticket médio" :valor="'R$ '.number_format($totais['ticketMedio'], 2, ',', '.')" icone="receipt-percent" />
            <x-ng.indicador titulo="Nº de vendas" :valor="$totais['vendas']" sub="atendimentos pagos" icone="shopping-bag" />
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            {{-- Recebimentos por forma de pagamento --}}
            <div class="ng-surface flex flex-col gap-3 p-5">
                <flux:heading size="lg" style="color: var(--cor-texto);">Recebimentos por forma de pagamento</flux:heading>
                @if (empty($recebimentos))
                    <x-ng.empty icon="credit-card" title="Sem recebimentos no período" text="Quando houver comandas pagas, a quebra por forma aparece aqui." />
                @else
                    <table class="w-full text-sm">
                        <tbody>
                            @foreach ($recebimentos as $metodo => $valor)
                                <tr style="border-top: 1px solid color-mix(in srgb, var(--cor-texto) 10%, transparent); color: var(--cor-texto);">
                                    <td class="py-2">{{ $metodos[$metodo] ?? $metodo }}</td>
                                    <td class="py-2 text-right tabular-nums">R$ {{ number_format($valor, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                            <tr class="font-semibold" style="border-top: 2px solid color-mix(in srgb, var(--cor-texto) 20%, transparent); color: var(--cor-texto);">
                                <td class="py-2">Total recebido</td>
                                <td class="py-2 text-right tabular-nums">R$ {{ number_format(array_sum($recebimentos), 2, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                @endif
            </div>

            {{-- Como calculamos o lucro bruto (sem caixa-preta) --}}
            <div class="ng-surface flex flex-col gap-3 p-5">
                <flux:heading size="lg" style="color: var(--cor-texto);">Como calculamos o lucro bruto</flux:heading>
                <table class="w-full text-sm">
                    <tbody style="color: var(--cor-texto);">
                        <tr><td class="py-1">Receita (comandas pagas)</td><td class="py-1 text-right tabular-nums">+ R$ {{ number_format($totais['faturamento'], 2, ',', '.') }}</td></tr>
                        <tr><td class="py-1">Comissões</td><td class="py-1 text-right tabular-nums">− R$ {{ number_format($comissoes, 2, ',', '.') }}</td></tr>
                        <tr>
                            <td class="py-1">CPV (custo de produto vendido)</td>
                            <td class="py-1 text-right tabular-nums">− R$ {{ number_format($cpv, 2, ',', '.') }}</td>
                        </tr>
                        <tr class="font-semibold" style="border-top: 2px solid color-mix(in srgb, var(--cor-texto) 20%, transparent);">
                            <td class="py-2">Lucro bruto</td>
                            <td class="py-2 text-right tabular-nums">R$ {{ number_format($lucroBruto, 2, ',', '.') }}</td>
                        </tr>
                    </tbody>
                </table>
                <flux:text class="text-xs" style="color: var(--cor-texto-suave);">
                    @unless ($temCusto)
                        Nenhum produto tem custo de compra cadastrado — o CPV está como R$ 0,00.
                    @else
                        CPV usa o custo de compra atual cadastrado nos produtos (não o custo histórico da venda).
                    @endunless
                    <strong>Despesas operacionais (aluguel, fornecedores etc.) entram na próxima versão</strong> — por isso este é o lucro <strong>bruto</strong>, não o líquido.
                </flux:text>
            </div>
        </div>

        {{-- Série de faturamento por dia --}}
        <div class="ng-surface flex flex-col gap-3 p-5">
            <flux:heading size="lg" style="color: var(--cor-texto);">Faturamento por dia</flux:heading>
            @if (empty($serie))
                <x-ng.empty icon="chart-bar" title="Sem faturamento no período" text="Ajuste o período ou registre vendas." />
            @else
                @php($maxSerie = max($serie))
                <div class="flex flex-col gap-1">
                    @foreach ($serie as $dia => $total)
                        <div class="flex items-center gap-3 text-sm">
                            <span class="w-24 shrink-0 tabular-nums" style="color: var(--cor-texto-suave);">{{ \Illuminate\Support\Carbon::parse($dia)->format('d/m') }}</span>
                            <div class="h-4 flex-1 overflow-hidden rounded" style="background-color: color-mix(in srgb, var(--cor-texto) 8%, transparent);">
                                <div class="h-full rounded" style="width: {{ $maxSerie > 0 ? max(2, round($total / $maxSerie * 100)) : 0 }}%; background-color: var(--cor-principal);"></div>
                            </div>
                            <span class="w-28 shrink-0 text-right tabular-nums" style="color: var(--cor-texto);">R$ {{ number_format($total, 2, ',', '.') }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
