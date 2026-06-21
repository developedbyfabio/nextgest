<div class="flex flex-col gap-6 p-6 lg:p-8">
    <x-ng.page-header title="Equipe" subtitle="Membros, papéis e profissionais">
        <x-slot:actions>
            @can('criar_usuario')
                <flux:button wire:click="novo" variant="primary" icon="plus">Novo membro</flux:button>
            @endcan
        </x-slot:actions>
    </x-ng.page-header>

    <flux:input wire:model.live.debounce.300ms="busca" icon="magnifying-glass" placeholder="Buscar por nome ou e-mail" class="max-w-xs" />

    <div wire:loading.delay.flex wire:target="busca" class="flex-col gap-2">
        @for ($i = 0; $i < 4; $i++)<div class="ng-skeleton-portal h-12 w-full"></div>@endfor
    </div>

    <div wire:loading.remove.delay wire:target="busca">
        @if ($membros->isEmpty())
            <x-ng.empty themed icon="identification" title="Nenhum membro encontrado"
                text="{{ $busca ? 'Ajuste a busca.' : 'Cadastre o primeiro membro da equipe.' }}">
                @if (! $busca)
                    @can('criar_usuario')<flux:button wire:click="novo" variant="primary" size="sm" icon="plus" class="mt-2">Novo membro</flux:button>@endcan
                @endif
            </x-ng.empty>
        @else
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
                    @foreach ($membros as $membro)
                        <flux:table.row :key="$membro->id">
                            <flux:table.cell variant="strong">{{ $membro->name }}</flux:table.cell>
                            <flux:table.cell>{{ $membro->email }}</flux:table.cell>
                            <flux:table.cell>{{ $membro->roles->pluck('name')->join(', ') ?: '—' }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($membro->e_profissional)<flux:badge color="blue" size="sm" icon="scissors">Profissional</flux:badge>@else <span style="color: var(--cor-texto-suave);">—</span>@endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="$membro->ativo ? 'green' : 'zinc'" size="sm">{{ $membro->ativo ? 'Ativo' : 'Inativo' }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="text-right whitespace-nowrap">
                                @if ($membro->e_profissional)
                                    <flux:button :href="route('painel.equipe.horarios', ['tenant' => tenant('id'), 'user' => $membro->id])" size="sm" variant="ghost" icon="clock" wire:navigate>Horários</flux:button>
                                @endif
                                <flux:button wire:click="editar({{ $membro->id }})" size="sm" variant="ghost" icon="pencil-square">Editar</flux:button>
                                @if ($membro->ativo)
                                    <flux:button wire:click="pedirInativar({{ $membro->id }})" size="sm" variant="subtle" icon="eye-slash">Inativar</flux:button>
                                @else
                                    <flux:button wire:click="reativar({{ $membro->id }})" size="sm" variant="subtle" icon="eye">Reativar</flux:button>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>

    <x-ng.confirmar name="inativar-membro" tom="amber" icone="eye-slash" titulo="Inativar membro?"
        texto="Ele perde o acesso, mas não é apagado (pode reativar depois).">
        @if ($confirmarId)
            <flux:button wire:click="inativar({{ $confirmarId }})" variant="primary" icon="eye-slash">Inativar</flux:button>
        @endif
    </x-ng.confirmar>

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
                        <flux:text class="text-sm" style="color: var(--cor-texto-suave);">Cadastre serviços primeiro.</flux:text>
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
