<div class="flex flex-col gap-6 p-6 lg:p-8">
    {{-- Cabeçalho + filtros --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Olá, {{ auth('web')->user()->name }}</flux:heading>
            <flux:subheading>{{ tenant('nome') }} · {{ $d['inicio']->format('d/m/Y') }} – {{ $d['fim']->format('d/m/Y') }}</flux:subheading>
        </div>

        <div class="flex flex-wrap items-end gap-3">
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

    @unless ($temDados)
        <flux:callout icon="information-circle">
            <flux:callout.heading>Sem agendamentos no período</flux:callout.heading>
            <flux:callout.text>Ajuste o período ou registre agendamentos para ver os indicadores.</flux:callout.text>
        </flux:callout>
    @endunless

    {{-- Indicadores (KPIs) --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
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

    {{-- Gráficos --}}
    <div class="grid gap-4 lg:grid-cols-2">
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
        <flux:card class="flex flex-col gap-3">
            <flux:heading size="sm">Profissionais com melhor desempenho</flux:heading>
            @forelse ($d['profissionais'] as $p)
                <div class="flex items-center justify-between border-b border-zinc-100 pb-2 last:border-0 last:pb-0 dark:border-zinc-700">
                    <flux:text class="font-medium">{{ $p['nome'] }}</flux:text>
                    <div class="text-right">
                        <div class="text-sm font-semibold tabular-nums">{{ $p['total'] }} concluídos</div>
                        <flux:text class="text-xs text-zinc-500">R$ {{ number_format($p['valor'], 2, ',', '.') }} estimado</flux:text>
                    </div>
                </div>
            @empty
                <x-ng.empty icon="identification" title="Sem concluídos no período" />
            @endforelse
        </flux:card>

        <flux:card class="flex flex-col gap-3">
            <flux:heading size="sm">Atalhos</flux:heading>
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
        </flux:card>
    </div>

    <flux:text class="text-xs text-zinc-400">
        Faturamento estimado a partir dos serviços concluídos no período (snapshots de preço). Vendas e clube entram em fatias futuras.
    </flux:text>
</div>
