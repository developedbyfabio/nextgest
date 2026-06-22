<div class="flex flex-col gap-6 p-6 lg:p-8">
    <x-ng.page-header title="Bloqueios" subtitle="Folgas, feriados e imprevistos por profissional">
        <x-slot:actions>
            <flux:button wire:click="novo" variant="primary" icon="plus">Novo bloqueio</flux:button>
        </x-slot:actions>
    </x-ng.page-header>

    @if ($bloqueios->isEmpty())
        <x-ng.empty themed icon="no-symbol" title="Nenhum bloqueio"
            text="Cadastre folgas, feriados ou imprevistos para reservar a agenda.">
            <flux:button wire:click="novo" variant="primary" size="sm" icon="plus" class="mt-2">Novo bloqueio</flux:button>
        </x-ng.empty>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Profissional</flux:table.column>
                <flux:table.column>Início</flux:table.column>
                <flux:table.column>Fim</flux:table.column>
                <flux:table.column>Motivo</flux:table.column>
                <flux:table.column />
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($bloqueios as $bloqueio)
                    <flux:table.row :key="$bloqueio->id">
                        <flux:table.cell variant="strong">{{ $bloqueio->user?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $bloqueio->inicio->format('d/m/Y H:i') }}</flux:table.cell>
                        <flux:table.cell>{{ $bloqueio->fim->format('d/m/Y H:i') }}</flux:table.cell>
                        <flux:table.cell>{{ $bloqueio->motivo ?: '—' }}</flux:table.cell>
                        <flux:table.cell class="text-right">
                            <flux:button wire:click="editar({{ $bloqueio->id }})" size="sm" variant="ghost" icon="pencil-square">Editar</flux:button>
                            <flux:button wire:click="pedirExcluir({{ $bloqueio->id }})" size="sm" variant="subtle" icon="trash">Remover</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        <div>{{ $bloqueios->links() }}</div>
    @endif

    <x-ng.confirmar name="remover-bloqueio" tom="red" icone="trash" titulo="Remover bloqueio?"
        texto="O horário volta a ficar disponível na agenda.">
        @if ($confirmarId)
            <flux:button wire:click="excluir({{ $confirmarId }})" variant="danger" icon="trash">Remover</flux:button>
        @endif
    </x-ng.confirmar>

    <flux:modal wire:model.self="mostrarFormulario" class="md:w-[30rem]">
        <form wire:submit="salvar" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ $editandoId ? 'Editar bloqueio' : 'Novo bloqueio' }}</flux:heading>

            <flux:select wire:model="user_id" label="Profissional" placeholder="Selecione..." required>
                @foreach ($profissionais as $prof)
                    <flux:select.option value="{{ $prof->id }}">{{ $prof->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex gap-4">
                <flux:input wire:model="inicio" type="datetime-local" label="Início" class="flex-1" required />
                <flux:input wire:model="fim" type="datetime-local" label="Fim" class="flex-1" required />
            </div>

            <flux:input wire:model="motivo" label="Motivo" placeholder="Opcional (ex.: Almoço, Folga)" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Salvar</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
