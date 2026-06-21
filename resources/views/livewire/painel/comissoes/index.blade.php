@php($alvos = 'periodo,unidadeId,dataInicio,dataFim')
<div class="flex flex-col gap-6 p-6 lg:p-8">
    <x-ng.page-header title="Comissões" subtitle="A pagar por profissional (vendas pagas no período)">
        <x-slot:actions>
            <flux:button wire:click="abrirOverrides" variant="ghost" icon="adjustments-horizontal">Comissões personalizadas</flux:button>
        </x-slot:actions>
    </x-ng.page-header>

    {{-- Filtros --}}
    <div class="flex flex-wrap items-end gap-3">
        <flux:icon name="arrow-path" wire:loading.delay wire:target="{{ $alvos }}" class="size-5 animate-spin self-center" style="color: var(--cor-principal);" />
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

    {{-- Total geral --}}
    <div class="ng-surface flex items-center justify-between p-5">
        <div>
            <flux:text class="text-sm" style="color: var(--cor-texto-suave);">Total de comissões no período</flux:text>
            <flux:subheading style="color: var(--cor-texto-suave);">{{ $inicio->format('d/m/Y') }} – {{ $fim->format('d/m/Y') }}</flux:subheading>
        </div>
        <div class="text-2xl font-bold" style="color: var(--cor-principal);">R$ {{ number_format($totalGeral, 2, ',', '.') }}</div>
    </div>

    {{-- Por profissional --}}
    <div wire:loading.class.delay="opacity-60" wire:target="{{ $alvos }}" class="transition-opacity">
        @if ($comissoes->isEmpty())
            <x-ng.empty themed icon="wallet" title="Sem comissões no período"
                text="As comissões aparecem quando há vendas pagas com profissional e % definida." />
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Profissional</flux:table.column>
                    <flux:table.column>Itens</flux:table.column>
                    <flux:table.column>Comissão</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($comissoes as $c)
                        <flux:table.row :key="$c['profissional_id']">
                            <flux:table.cell variant="strong">
                                <span class="flex items-center gap-3">
                                    <flux:avatar size="sm" :name="$c['nome']" />
                                    {{ $c['nome'] }}
                                </span>
                            </flux:table.cell>
                            <flux:table.cell>{{ $c['itens'] }}</flux:table.cell>
                            <flux:table.cell variant="strong">R$ {{ number_format($c['total'], 2, ',', '.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>

    <flux:text class="text-xs" style="color: var(--cor-texto-suave);">
        Comissão = snapshot gravado em cada item ao pagar a comanda. Precedência: override do profissional → % padrão do serviço/produto → nenhuma.
    </flux:text>

    {{-- Modal: overrides por profissional --}}
    <flux:modal wire:model.self="mostrarOverrides" class="md:w-[40rem]">
        <div class="flex flex-col gap-4">
            <flux:heading size="lg">Comissões personalizadas</flux:heading>
            <flux:text class="text-sm" style="color: var(--cor-texto-suave);">
                Defina uma % específica de um profissional para serviços/produtos. Deixe em branco para usar a % padrão do cadastro.
            </flux:text>

            <flux:select wire:model.live="overrideProfId" label="Profissional" placeholder="Escolha um profissional">
                <flux:select.option value="">—</flux:select.option>
                @foreach ($profissionais as $prof)
                    <flux:select.option value="{{ $prof->id }}">{{ $prof->name }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($overrideProfId)
                <form wire:submit="salvarOverrides" class="flex flex-col gap-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-2">
                            <flux:heading size="sm" style="color: var(--cor-texto);">Serviços</flux:heading>
                            @forelse ($servicos as $s)
                                <div wire:key="ovs-{{ $s->id }}" class="flex items-center gap-2">
                                    <span class="min-w-0 flex-1 truncate text-sm" style="color: var(--cor-texto);">{{ $s->nome }}</span>
                                    <flux:input wire:model="overrideServico.{{ $s->id }}" type="number" step="0.01" min="0" max="100"
                                        size="sm" class="w-24" :placeholder="$s->percentual_comissao !== null ? (string) $s->percentual_comissao : '—'" />
                                </div>
                            @empty
                                <flux:text class="text-sm" style="color: var(--cor-texto-suave);">Sem serviços ativos.</flux:text>
                            @endforelse
                        </div>

                        <div class="flex flex-col gap-2">
                            <flux:heading size="sm" style="color: var(--cor-texto);">Produtos</flux:heading>
                            @forelse ($produtos as $p)
                                <div wire:key="ovp-{{ $p->id }}" class="flex items-center gap-2">
                                    <span class="min-w-0 flex-1 truncate text-sm" style="color: var(--cor-texto);">{{ $p->nome }}</span>
                                    <flux:input wire:model="overrideProduto.{{ $p->id }}" type="number" step="0.01" min="0" max="100"
                                        size="sm" class="w-24" :placeholder="$p->percentual_comissao !== null ? (string) $p->percentual_comissao : '—'" />
                                </div>
                            @empty
                                <flux:text class="text-sm" style="color: var(--cor-texto-suave);">Sem produtos ativos.</flux:text>
                            @endforelse
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                        <flux:button type="submit" variant="primary">Salvar</flux:button>
                    </div>
                </form>
            @endif
        </div>
    </flux:modal>
</div>
