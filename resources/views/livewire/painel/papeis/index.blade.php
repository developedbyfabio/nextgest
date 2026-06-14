<div class="flex flex-col gap-6 p-6 lg:p-8">
    <x-ng.page-header title="Papéis e permissões" subtitle="Defina o que cada papel pode fazer">
        <x-slot:actions>
            <flux:button wire:click="novo" variant="primary" icon="plus">Novo papel</flux:button>
        </x-slot:actions>
    </x-ng.page-header>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Papel</flux:table.column>
            <flux:table.column>Permissões</flux:table.column>
            <flux:table.column>Membros</flux:table.column>
            <flux:table.column />
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($papeis as $papel)
                <flux:table.row :key="$papel->id">
                    <flux:table.cell variant="strong">{{ $papel->name }}</flux:table.cell>
                    <flux:table.cell>{{ $papel->permissions_count }}</flux:table.cell>
                    <flux:table.cell>{{ $papel->users_count }}</flux:table.cell>
                    <flux:table.cell class="text-right">
                        <flux:button wire:click="editar({{ $papel->id }})" size="sm" variant="ghost" icon="pencil-square">Editar</flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model.self="mostrarFormulario" class="md:w-[34rem]">
        <form wire:submit="salvar" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ $editandoId ? 'Editar papel' : 'Novo papel' }}</flux:heading>

            <flux:input wire:model="nomePapel" label="Nome do papel" placeholder="Ex.: Caixa" required />

            @if ($donoSelecionado)
                <flux:callout icon="shield-check" variant="warning">
                    <flux:callout.text>O papel Dono mantém todas as permissões automaticamente.</flux:callout.text>
                </flux:callout>
            @else
                <flux:checkbox.group wire:model="permissoesSelecionadas" label="Permissões">
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        @foreach ($todasPermissoes as $permissao)
                            <flux:checkbox value="{{ $permissao }}" label="{{ $permissao }}" />
                        @endforeach
                    </div>
                </flux:checkbox.group>
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Salvar</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
