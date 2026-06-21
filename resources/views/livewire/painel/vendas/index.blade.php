@php($alvos = 'busca,statusFiltro,periodo,unidadeFiltro,page')
@php($statusCor = ['aberta' => 'amber', 'paga' => 'green', 'cancelada' => 'zinc'])
<div class="flex flex-col gap-6 p-6 lg:p-8">
    <x-ng.page-header title="Comandas" subtitle="Vendas de balcão e a partir de atendimentos">
        <x-slot:actions>
            <flux:button wire:click="novaComanda" variant="primary" icon="plus">Nova comanda</flux:button>
        </x-slot:actions>
    </x-ng.page-header>

    {{-- Filtros --}}
    <div class="flex flex-wrap items-end gap-3">
        <flux:input wire:model.live.debounce.300ms="busca" icon="magnifying-glass" placeholder="Buscar por cliente" class="min-w-52 flex-1" />
        <flux:select wire:model.live="statusFiltro" label="Status" class="min-w-36">
            <flux:select.option value="todas">Todas</flux:select.option>
            <flux:select.option value="aberta">Abertas</flux:select.option>
            <flux:select.option value="paga">Pagas</flux:select.option>
            <flux:select.option value="cancelada">Canceladas</flux:select.option>
        </flux:select>
        <flux:select wire:model.live="periodo" label="Período" class="min-w-36">
            <flux:select.option value="hoje">Hoje</flux:select.option>
            <flux:select.option value="7d">7 dias</flux:select.option>
            <flux:select.option value="30d">30 dias</flux:select.option>
            <flux:select.option value="todos">Todos</flux:select.option>
        </flux:select>
        @if ($multiUnidade)
            <flux:select wire:model.live="unidadeFiltro" label="Unidade" class="min-w-40">
                <flux:select.option value="">Todas</flux:select.option>
                @foreach ($unidades as $u)
                    <flux:select.option value="{{ $u->id }}">{{ $u->nome }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif
    </div>

    {{-- Loading --}}
    <div wire:loading.delay.flex wire:target="{{ $alvos }}" class="flex-col gap-2">
        @for ($i = 0; $i < 6; $i++)
            <div class="ng-skeleton-portal h-12 w-full"></div>
        @endfor
    </div>

    <div wire:loading.remove.delay wire:target="{{ $alvos }}" class="flex flex-col gap-4">
        @if ($vendas->isEmpty())
            <x-ng.empty themed icon="shopping-cart" title="Nenhuma comanda encontrada"
                text="Abra uma comanda de balcão ou gere a partir de um atendimento concluído.">
                <flux:button wire:click="novaComanda" variant="primary" size="sm" icon="plus" class="mt-2">Nova comanda</flux:button>
            </x-ng.empty>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Data</flux:table.column>
                    <flux:table.column>Cliente</flux:table.column>
                    @if ($multiUnidade)<flux:table.column>Unidade</flux:table.column>@endif
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Total</flux:table.column>
                    <flux:table.column />
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($vendas as $venda)
                        <flux:table.row :key="$venda->id">
                            <flux:table.cell>{{ $venda->data->format('d/m/Y H:i') }}</flux:table.cell>
                            <flux:table.cell variant="strong">{{ $venda->cliente?->nome ?? 'Balcão (anônimo)' }}</flux:table.cell>
                            @if ($multiUnidade)<flux:table.cell>{{ $venda->unidade?->nome }}</flux:table.cell>@endif
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$statusCor[$venda->status] ?? 'zinc'">{{ \App\Models\Venda::STATUS_LABEL[$venda->status] ?? $venda->status }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell variant="strong">R$ {{ number_format((float) $venda->valor_total, 2, ',', '.') }}</flux:table.cell>
                            <flux:table.cell class="text-right">
                                <flux:button :href="route('painel.vendas.detalhe', ['tenant' => tenant('id'), 'venda' => $venda->id])" size="sm" variant="ghost" icon="arrow-right" wire:navigate>Abrir</flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <div>{{ $vendas->links() }}</div>
        @endif
    </div>

    {{-- Modal: nova comanda --}}
    <flux:modal wire:model.self="mostrarNova" class="md:w-96">
        <form wire:submit="criar" class="flex flex-col gap-4">
            <flux:heading size="lg">Nova comanda</flux:heading>

            <flux:select wire:model="novaUnidadeId" label="Unidade" required>
                @foreach ($unidades as $u)
                    <flux:select.option value="{{ $u->id }}">{{ $u->nome }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="novaClienteId" label="Cliente (opcional)" placeholder="Balcão (anônimo)">
                <flux:select.option value="">Balcão (anônimo)</flux:select.option>
                @foreach ($clientes as $c)
                    <flux:select.option value="{{ $c->id }}">{{ $c->nome }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" icon="plus">Abrir comanda</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
