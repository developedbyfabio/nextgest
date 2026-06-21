<div class="flex flex-col gap-6 p-6 lg:p-8">
    <x-ng.page-header title="Unidades" subtitle="Filiais do estabelecimento">
        <x-slot:actions>
            <flux:button wire:click="novo" variant="primary" icon="plus">Nova unidade</flux:button>
        </x-slot:actions>
    </x-ng.page-header>

    @if ($unidades->isEmpty())
        <x-ng.empty themed icon="building-storefront" title="Nenhuma unidade cadastrada"
            text="Cadastre a primeira filial do estabelecimento.">
            <flux:button wire:click="novo" variant="primary" size="sm" icon="plus" class="mt-2">Nova unidade</flux:button>
        </x-ng.empty>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Nome</flux:table.column>
                <flux:table.column>Telefone</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column />
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($unidades as $unidade)
                    <flux:table.row :key="$unidade->id">
                        <flux:table.cell variant="strong">{{ $unidade->nome }}</flux:table.cell>
                        <flux:table.cell>{{ $unidade->telefone ?: '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$unidade->ativo ? 'green' : 'zinc'" size="sm">{{ $unidade->ativo ? 'Ativa' : 'Inativa' }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <flux:button wire:click="editar({{ $unidade->id }})" size="sm" variant="ghost" icon="pencil-square">Editar</flux:button>
                            @if ($unidade->ativo)
                                <flux:button wire:click="pedirInativar({{ $unidade->id }})" size="sm" variant="subtle" icon="eye-slash">Inativar</flux:button>
                            @else
                                <flux:button wire:click="reativar({{ $unidade->id }})" size="sm" variant="subtle" icon="eye">Reativar</flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <x-ng.confirmar name="inativar-unidade" tom="amber" icone="eye-slash" titulo="Inativar unidade?"
        texto="Ela sai das listas ativas, mas não é apagada (pode reativar depois).">
        @if ($confirmarId)
            <flux:button wire:click="inativar({{ $confirmarId }})" variant="primary" icon="eye-slash">Inativar</flux:button>
        @endif
    </x-ng.confirmar>

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
