<div class="flex flex-col gap-6 p-6 lg:p-8">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Equipe</flux:heading>
            <flux:subheading>Membros, papéis e profissionais</flux:subheading>
        </div>
        @can('criar_usuario')
            <flux:button wire:click="novo" variant="primary" icon="plus">Novo membro</flux:button>
        @endcan
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Nome</flux:table.column>
            <flux:table.column>E-mail</flux:table.column>
            <flux:table.column>Papel</flux:table.column>
            <flux:table.column>Profissional</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column />
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($membros as $membro)
                <flux:table.row :key="$membro->id">
                    <flux:table.cell variant="strong">{{ $membro->name }}</flux:table.cell>
                    <flux:table.cell>{{ $membro->email }}</flux:table.cell>
                    <flux:table.cell>{{ $membro->roles->pluck('name')->join(', ') ?: '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $membro->e_profissional ? 'Sim' : 'Não' }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($membro->ativo)
                            <flux:badge color="green" size="sm">Ativo</flux:badge>
                        @else
                            <flux:badge color="zinc" size="sm">Inativo</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="text-right">
                        @if ($membro->e_profissional)
                            <flux:button :href="route('painel.equipe.horarios', ['tenant' => tenant('id'), 'user' => $membro->id])" size="sm" variant="ghost" icon="clock" wire:navigate>Horários</flux:button>
                        @endif
                        <flux:button wire:click="editar({{ $membro->id }})" size="sm" variant="ghost" icon="pencil-square">Editar</flux:button>
                        @if ($membro->ativo)
                            <flux:button wire:click="inativar({{ $membro->id }})" wire:confirm="Inativar este membro?" size="sm" variant="subtle" icon="eye-slash">Inativar</flux:button>
                        @else
                            <flux:button wire:click="reativar({{ $membro->id }})" size="sm" variant="subtle" icon="eye">Reativar</flux:button>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center text-zinc-500">Nenhum membro cadastrado.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model.self="mostrarFormulario" class="md:w-[34rem]">
        <form wire:submit="salvar" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ $editandoId ? 'Editar membro' : 'Novo membro' }}</flux:heading>

            <flux:input wire:model="name" label="Nome" required />
            <flux:input wire:model="email" type="email" label="E-mail" required />

            <div class="flex gap-4">
                <flux:select wire:model="papel" label="Papel" class="flex-1" required placeholder="Selecione...">
                    @foreach ($papeis as $nomePapel)
                        <flux:select.option value="{{ $nomePapel }}">{{ $nomePapel }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="password" type="password" label="{{ $editandoId ? 'Nova senha (opcional)' : 'Senha inicial' }}" viewable class="flex-1" :required="! $editandoId" />
            </div>

            @if ($todasUnidades->count() > 1)
                <flux:checkbox.group wire:model="unidades" label="Atua nas unidades">
                    @foreach ($todasUnidades as $unidade)
                        <flux:checkbox value="{{ $unidade->id }}" label="{{ $unidade->nome }}" />
                    @endforeach
                </flux:checkbox.group>
            @endif

            <flux:switch wire:model.live="e_profissional" label="É profissional (aparece na agenda)" />

            @if ($e_profissional)
                <flux:checkbox.group wire:model="servicos" label="Serviços que executa">
                    @forelse ($todosServicos as $servico)
                        <flux:checkbox value="{{ $servico->id }}" label="{{ $servico->nome }}" />
                    @empty
                        <flux:text class="text-sm text-zinc-500">Cadastre serviços primeiro.</flux:text>
                    @endforelse
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
