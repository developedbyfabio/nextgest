<div class="flex flex-col gap-6 p-6 lg:p-8">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Bloqueios</flux:heading>
            <flux:subheading>Folgas, feriados e imprevistos por profissional</flux:subheading>
        </div>
        <flux:button wire:click="novo" variant="primary" icon="plus">Novo bloqueio</flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Profissional</flux:table.column>
            <flux:table.column>Início</flux:table.column>
            <flux:table.column>Fim</flux:table.column>
            <flux:table.column>Motivo</flux:table.column>
            <flux:table.column />
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($bloqueios as $bloqueio)
                <flux:table.row :key="$bloqueio->id">
                    <flux:table.cell variant="strong">{{ $bloqueio->user?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $bloqueio->inicio->format('d/m/Y H:i') }}</flux:table.cell>
                    <flux:table.cell>{{ $bloqueio->fim->format('d/m/Y H:i') }}</flux:table.cell>
                    <flux:table.cell>{{ $bloqueio->motivo ?: '—' }}</flux:table.cell>
                    <flux:table.cell class="text-right">
                        <flux:button wire:click="editar({{ $bloqueio->id }})" size="sm" variant="ghost" icon="pencil-square">Editar</flux:button>
                        <flux:button wire:click="excluir({{ $bloqueio->id }})" wire:confirm="Remover este bloqueio?" size="sm" variant="subtle" icon="trash">Remover</flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center text-zinc-500">Nenhum bloqueio cadastrado.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

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
