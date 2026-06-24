<div class="flex flex-col gap-6 p-6 lg:p-8">
    <x-ng.page-header title="Serviços" subtitle="O que o estabelecimento oferece">
        <x-slot:actions>
            @can('criar_servico')
                <flux:button wire:click="novo" variant="primary" icon="plus">Novo serviço</flux:button>
            @endcan
        </x-slot:actions>
    </x-ng.page-header>

    <flux:input wire:model.live.debounce.300ms="busca" icon="magnifying-glass" placeholder="Buscar serviço" class="max-w-xs" />

    <div wire:loading.delay.flex wire:target="busca" class="flex-col gap-2">
        @for ($i = 0; $i < 4; $i++)<div class="ng-skeleton-portal h-12 w-full"></div>@endfor
    </div>

    <div wire:loading.remove.delay wire:target="busca">
        @if ($servicos->isEmpty())
            <x-ng.empty themed icon="scissors" title="Nenhum serviço encontrado"
                text="{{ $busca ? 'Ajuste a busca.' : 'Cadastre o primeiro serviço.' }}">
                @if (! $busca)
                    <flux:button wire:click="novo" variant="primary" size="sm" icon="plus" class="mt-2">Novo serviço</flux:button>
                @endif
            </x-ng.empty>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Nome</flux:table.column>
                    <flux:table.column>Duração</flux:table.column>
                    <flux:table.column>Preço</flux:table.column>
                    <flux:table.column>Comissão</flux:table.column>
                    <flux:table.column>Unidades</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column />
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($servicos as $servico)
                        <flux:table.row :key="$servico->id">
                            <flux:table.cell variant="strong">{{ $servico->nome }}</flux:table.cell>
                            <flux:table.cell>{{ $servico->duracao_minutos }} min</flux:table.cell>
                            <flux:table.cell>R$ {{ number_format((float) $servico->preco, 2, ',', '.') }}</flux:table.cell>
                            <flux:table.cell>{{ $servico->percentual_comissao !== null ? rtrim(rtrim(number_format((float) $servico->percentual_comissao, 2, ',', '.'), '0'), ',').'%' : '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $servico->unidades->count() }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="$servico->ativo ? 'green' : 'zinc'" size="sm">{{ $servico->ativo ? 'Ativo' : 'Inativo' }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="text-right">
                                <flux:button wire:click="editar({{ $servico->id }})" size="sm" variant="ghost" icon="pencil-square">Editar</flux:button>
                                @if ($servico->ativo)
                                    <flux:button wire:click="pedirInativar({{ $servico->id }})" size="sm" variant="subtle" icon="eye-slash">Inativar</flux:button>
                                @else
                                    <flux:button wire:click="reativar({{ $servico->id }})" size="sm" variant="subtle" icon="eye">Reativar</flux:button>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>

    <x-ng.confirmar name="inativar-servico" tom="amber" icone="eye-slash" titulo="Inativar serviço?"
        texto="Ele sai das listas ativas, mas não é apagado (pode reativar depois).">
        @if ($confirmarId)
            <flux:button wire:click="inativar({{ $confirmarId }})" variant="primary" icon="eye-slash">Inativar</flux:button>
        @endif
    </x-ng.confirmar>

    <flux:modal wire:model.self="mostrarFormulario" class="md:w-[32rem]">
        <form wire:submit="salvar" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ $editandoId ? 'Editar serviço' : 'Novo serviço' }}</flux:heading>

            <flux:input wire:model="nome" label="Nome" placeholder="Ex.: Corte masculino" required />
            <flux:textarea wire:model="descricao" label="Descrição" placeholder="Opcional" rows="2" />

            <div class="flex gap-4">
                <flux:input wire:model="duracao_minutos" type="number" min="1" label="Duração (min)" class="flex-1" required />
                <flux:input wire:model="preco" type="number" step="0.01" min="0" label="Preço (R$)" class="flex-1" required />
            </div>

            <flux:input wire:model="percentual_comissao" type="number" step="0.01" min="0" max="100" label="Comissão padrão (%)" placeholder="Opcional — comissão do profissional ao vender este serviço" />

            <flux:checkbox.group wire:model="unidades" label="Oferecido nas unidades" description="O serviço só aparece para o cliente nas unidades marcadas.">
                @forelse ($todasUnidades as $unidade)
                    <flux:checkbox value="{{ $unidade->id }}" label="{{ $unidade->nome }}" />
                @empty
                    <flux:text class="text-sm" style="color: var(--cor-texto-suave);">Cadastre uma unidade primeiro.</flux:text>
                @endforelse
            </flux:checkbox.group>
            <flux:error name="unidades" />

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
