<div class="flex flex-col gap-6 p-6 lg:p-8">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Unidades</flux:heading>
            <flux:subheading>Filiais do estabelecimento</flux:subheading>
        </div>
        <flux:button wire:click="novo" variant="primary" icon="plus">Nova unidade</flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Nome</flux:table.column>
            <flux:table.column>Telefone</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column />
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($unidades as $unidade)
                <flux:table.row :key="$unidade->id">
                    <flux:table.cell variant="strong">{{ $unidade->nome }}</flux:table.cell>
                    <flux:table.cell>{{ $unidade->telefone ?: '—' }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($unidade->ativo)
                            <flux:badge color="green" size="sm">Ativa</flux:badge>
                        @else
                            <flux:badge color="zinc" size="sm">Inativa</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="text-right">
                        <flux:button wire:click="editar({{ $unidade->id }})" size="sm" variant="ghost" icon="pencil-square">Editar</flux:button>
                        @if ($unidade->ativo)
                            <flux:button wire:click="inativar({{ $unidade->id }})" wire:confirm="Inativar esta unidade?" size="sm" variant="subtle" icon="eye-slash">Inativar</flux:button>
                        @else
                            <flux:button wire:click="reativar({{ $unidade->id }})" size="sm" variant="subtle" icon="eye">Reativar</flux:button>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4" class="text-center text-zinc-500">Nenhuma unidade cadastrada.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model.self="mostrarFormulario" class="md:w-96">
        <form wire:submit="salvar" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ $editandoId ? 'Editar unidade' : 'Nova unidade' }}</flux:heading>

            <flux:input wire:model="nome" label="Nome" placeholder="Ex.: Matriz Centro" required />
            <flux:input wire:model="endereco" label="Endereço" placeholder="Opcional" />
            <flux:input wire:model="telefone" label="Telefone" placeholder="Opcional" />
            <flux:switch wire:model="ativo" label="Ativa" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Salvar</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
