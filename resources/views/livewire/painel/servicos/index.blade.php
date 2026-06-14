<div class="flex flex-col gap-6 p-6 lg:p-8">
    <x-ng.page-header title="Serviços" subtitle="O que o estabelecimento oferece">
        <x-slot:actions>
            @can('criar_servico')
                <flux:button wire:click="novo" variant="primary" icon="plus">Novo serviço</flux:button>
            @endcan
        </x-slot:actions>
    </x-ng.page-header>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Nome</flux:table.column>
            <flux:table.column>Duração</flux:table.column>
            <flux:table.column>Preço</flux:table.column>
            <flux:table.column>Unidades</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column />
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($servicos as $servico)
                <flux:table.row :key="$servico->id">
                    <flux:table.cell variant="strong">{{ $servico->nome }}</flux:table.cell>
                    <flux:table.cell>{{ $servico->duracao_minutos }} min</flux:table.cell>
                    <flux:table.cell>R$ {{ number_format((float) $servico->preco, 2, ',', '.') }}</flux:table.cell>
                    <flux:table.cell>{{ $servico->unidades->count() }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($servico->ativo)
                            <flux:badge color="green" size="sm">Ativo</flux:badge>
                        @else
                            <flux:badge color="zinc" size="sm">Inativo</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="text-right">
                        <flux:button wire:click="editar({{ $servico->id }})" size="sm" variant="ghost" icon="pencil-square">Editar</flux:button>
                        @if ($servico->ativo)
                            <flux:button wire:click="inativar({{ $servico->id }})" wire:confirm="Inativar este serviço?" size="sm" variant="subtle" icon="eye-slash">Inativar</flux:button>
                        @else
                            <flux:button wire:click="reativar({{ $servico->id }})" size="sm" variant="subtle" icon="eye">Reativar</flux:button>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center text-zinc-500">Nenhum serviço cadastrado.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model.self="mostrarFormulario" class="md:w-[32rem]">
        <form wire:submit="salvar" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ $editandoId ? 'Editar serviço' : 'Novo serviço' }}</flux:heading>

            <flux:input wire:model="nome" label="Nome" placeholder="Ex.: Corte masculino" required />
            <flux:textarea wire:model="descricao" label="Descrição" placeholder="Opcional" rows="2" />

            <div class="flex gap-4">
                <flux:input wire:model="duracao_minutos" type="number" min="1" label="Duração (min)" class="flex-1" required />
                <flux:input wire:model="preco" type="number" step="0.01" min="0" label="Preço (R$)" class="flex-1" required />
            </div>

            @if ($todasUnidades->count() > 1)
                <flux:checkbox.group wire:model="unidades" label="Oferecido nas unidades">
                    @foreach ($todasUnidades as $unidade)
                        <flux:checkbox value="{{ $unidade->id }}" label="{{ $unidade->nome }}" />
                    @endforeach
                </flux:checkbox.group>
            @endif

            <flux:switch wire:model="ativo" label="Ativo" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Salvar</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
