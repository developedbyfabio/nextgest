@php($alvosFiltro = 'periodo,unidadeId,dataInicio,dataFim')
<div class="flex flex-col gap-6 p-6 lg:p-8">
    {{-- Cabeçalho + filtros --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" style="color: var(--cor-texto);">Olá, {{ auth('web')->user()->name }}</flux:heading>
            <flux:subheading style="color: var(--cor-texto-suave);">
                {{ tenant('nome') }} · {{ $d['inicio']->format('d/m/Y') }} – {{ $d['fim']->format('d/m/Y') }}
            </flux:subheading>
        </div>

        <div class="flex flex-wrap items-end gap-3">
            {{-- Spinner discreto enquanto recalcula --}}
            <flux:icon name="arrow-path" wire:loading.delay wire:target="{{ $alvosFiltro }}" class="size-5 animate-spin self-center" style="color: var(--cor-principal);" />

            @if ($multiUnidade)
                <flux:select wire:model.live="unidadeId" label="Unidade" class="min-w-44">
                    <flux:select.option value="">Todas</flux:select.option>
                    @foreach ($unidades as $u)
                        <flux:select.option value="{{ $u->id }}">{{ $u->nome }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:select wire:model.live="periodo" label="Período" class="min-w-40">
                <flux:select.option value="hoje">Hoje</flux:select.option>
                <flux:select.option value="7d">Últimos 7 dias</flux:select.option>
                <flux:select.option value="30d">Últimos 30 dias</flux:select.option>
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
        {{-- Estado de ERRO recuperável --}}
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
        @unless ($temDados)
            <div class="ng-surface flex items-start gap-3 p-4">
                <flux:icon name="information-circle" class="size-5 shrink-0" style="color: var(--cor-principal);" />
                <div>
                    <flux:heading size="sm" style="color: var(--cor-texto);">Sem agendamentos no período</flux:heading>
                    <flux:text class="text-sm" style="color: var(--cor-texto-suave);">Ajuste o período ou registre agendamentos para ver os indicadores.</flux:text>
                </div>
            </div>
        @endunless

        {{-- Indicadores (KPIs) — skeleton enquanto recalcula --}}
        <div wire:loading.delay wire:target="{{ $alvosFiltro }}" class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            @for ($i = 0; $i < 5; $i++)
                <div class="ng-surface flex flex-col gap-3 p-5">
                    <div class="ng-skeleton-portal h-4 w-20"></div>
                    <div class="ng-skeleton-portal h-8 w-24"></div>
                    <div class="ng-skeleton-portal h-3 w-16"></div>
                </div>
            @endfor
        </div>

        <div wire:loading.remove.delay wire:target="{{ $alvosFiltro }}" class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            <x-ng.indicador titulo="Agendamentos" :valor="$d['total']" icone="calendar-days"
                :tendencia="$d['delta']" sub="vs. período anterior" />
            <x-ng.indicador titulo="Faturamento estimado" :valor="'R$ '.number_format($d['faturamento'], 2, ',', '.')"
                icone="banknotes" sub="serviços concluídos" />
            <x-ng.indicador titulo="Clientes novos" :valor="$d['clientesNovos']" icone="user-plus" sub="cadastrados no período" />
            <x-ng.indicador titulo="Clientes recorrentes" :valor="$d['clientesRecorrentes']" icone="users" sub="2+ agendamentos" />
            <x-ng.indicador titulo="Comparecimento"
                :valor="is_null($d['comparecimento']['taxa']) ? '—' : number_format($d['comparecimento']['taxa'], 0).'%'"
                icone="check-circle" sub="concluídos / resolvidos" />
        </div>

        {{-- Gráficos (escurecem levemente durante o recálculo) --}}
        <div wire:loading.class.delay="opacity-60" wire:target="{{ $alvosFiltro }}" class="grid gap-4 transition-opacity lg:grid-cols-2">
            <x-ng.grafico chave="porDia" titulo="Agendamentos por dia" tipo="line"
                :dados="$d['graficos']['porDia']" :vazio="! $temDados" />

            <x-ng.grafico chave="horarios" titulo="Horários mais movimentados" tipo="bar"
                :dados="$d['graficos']['horarios']" :vazio="! $temDados" />

            <x-ng.grafico chave="servicos" titulo="Serviços mais agendados" tipo="bar"
                :dados="$d['graficos']['servicos']" :vazio="$d['servicos']->isEmpty()" />

            <x-ng.grafico chave="comparecimento" titulo="Taxa de comparecimento" tipo="doughnut"
                :dados="$d['graficos']['comparecimento']" :legenda="true" :vazio="! $temDados" />
        </div>

        {{-- Profissionais + atalhos --}}
        <div class="grid gap-4 lg:grid-cols-2">
            <div class="ng-surface flex flex-col gap-3 p-5">
                <flux:heading size="sm" style="color: var(--cor-texto);">Profissionais com melhor desempenho</flux:heading>
                @forelse ($d['profissionais'] as $p)
                    <div class="flex items-center justify-between border-b pb-2 last:border-0 last:pb-0" style="border-color: color-mix(in srgb, var(--cor-texto) 8%, transparent);">
                        <span class="flex items-center gap-3">
                            <flux:avatar size="sm" :name="$p['nome']" />
                            <flux:text class="font-medium" style="color: var(--cor-texto);">{{ $p['nome'] }}</flux:text>
                        </span>
                        <div class="text-right">
                            <div class="text-sm font-semibold tabular-nums" style="color: var(--cor-texto);">{{ $p['total'] }} concluídos</div>
                            <flux:text class="text-xs" style="color: var(--cor-texto-suave);">R$ {{ number_format($p['valor'], 2, ',', '.') }} estimado</flux:text>
                        </div>
                    </div>
                @empty
                    <x-ng.empty themed icon="identification" title="Sem concluídos no período" />
                @endforelse
            </div>

            <div class="ng-surface flex flex-col gap-3 p-5">
                <flux:heading size="sm" style="color: var(--cor-texto);">Atalhos</flux:heading>
                <div class="grid grid-cols-2 gap-2">
                    @can('ver_agenda')
                        <flux:button :href="route('painel.agenda', ['tenant' => tenant('id')])" variant="ghost" icon="calendar-days" class="justify-start" wire:navigate>Agenda</flux:button>
                    @endcan
                    @can('editar_servico')
                        <flux:button :href="route('painel.servicos', ['tenant' => tenant('id')])" variant="ghost" icon="scissors" class="justify-start" wire:navigate>Serviços</flux:button>
                    @endcan
                    @can('editar_usuario')
                        <flux:button :href="route('painel.equipe', ['tenant' => tenant('id')])" variant="ghost" icon="identification" class="justify-start" wire:navigate>Equipe</flux:button>
                    @endcan
                    @can('gerir_agenda')
                        <flux:button :href="route('painel.bloqueios', ['tenant' => tenant('id')])" variant="ghost" icon="no-symbol" class="justify-start" wire:navigate>Bloqueios</flux:button>
                    @endcan
                    @can('gerir_aparencia')
                        <flux:button :href="route('painel.aparencia', ['tenant' => tenant('id')])" variant="ghost" icon="paint-brush" class="justify-start" wire:navigate>Aparência</flux:button>
                    @endcan
                </div>
            </div>
        </div>

        <flux:text class="text-xs" style="color: var(--cor-texto-suave);">
            Faturamento <strong>estimado</strong> a partir dos serviços concluídos no período (snapshots de preço). Vendas e clube entram em fatias futuras.
        </flux:text>
    @endif
</div>
