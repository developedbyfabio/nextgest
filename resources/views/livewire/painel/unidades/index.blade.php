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
                        <flux:table.cell class="text-right whitespace-nowrap">
                            <flux:button wire:click="gerir({{ $unidade->id }})" size="sm" variant="ghost" icon="squares-2x2">Gerir</flux:button>
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

    {{-- Gerir a unidade: serviços oferecidos (servico_unidade) + profissionais (leitura). --}}
    <flux:modal wire:model.self="mostrarGerir" class="md:w-[34rem]">
        <div class="flex flex-col gap-5">
            <flux:heading size="lg">Gerir unidade{{ $gerindo ? ': '.$gerindo->nome : '' }}</flux:heading>

            <form wire:submit="salvarServicos" class="flex flex-col gap-3">
                <flux:checkbox.group wire:model="servicosUnidade" label="Serviços oferecidos aqui"
                    description="O cliente só vê estes serviços ao agendar nesta unidade.">
                    @forelse ($todosServicos as $servico)
                        <flux:checkbox value="{{ $servico->id }}" label="{{ $servico->nome }}" />
                    @empty
                        <flux:text class="text-sm" style="color: var(--cor-texto-suave);">Cadastre serviços primeiro.</flux:text>
                    @endforelse
                </flux:checkbox.group>
                @if ($todosServicos->isNotEmpty())
                    <div class="flex justify-end">
                        <flux:button type="submit" variant="primary" size="sm" icon="check">Salvar serviços</flux:button>
                    </div>
                @endif
            </form>

            <flux:separator />

            <div class="flex flex-col gap-2">
                <flux:heading size="sm">Profissionais nesta unidade</flux:heading>
                @forelse ($profissionaisUnidade as $prof)
                    <div class="flex items-center justify-between gap-2 rounded-lg border px-3 py-2"
                        style="border-color: color-mix(in srgb, var(--cor-texto) 10%, transparent);">
                        <span class="min-w-0 truncate font-medium">{{ $prof->name }}</span>
                        <span class="flex shrink-0 items-center gap-1.5">
                            <flux:badge :color="$prof->servicos_count > 0 ? 'green' : 'amber'" size="sm">{{ $prof->servicos_count }} serviço(s)</flux:badge>
                            <flux:badge :color="$prof->horarios_count > 0 ? 'green' : 'amber'" size="sm">{{ $prof->horarios_count > 0 ? 'com horários' : 'sem horários' }}</flux:badge>
                        </span>
                    </div>
                @empty
                    <flux:text class="text-sm" style="color: var(--cor-texto-suave);">Nenhum profissional atribuído a esta unidade.</flux:text>
                @endforelse
                <flux:text class="text-xs" style="color: var(--cor-texto-suave);">
                    Para atribuir ou trocar a unidade de um profissional (e mover os horários junto), use a tela
                    <flux:button :href="route('painel.equipe', ['tenant' => tenant('id')])" variant="subtle" size="xs" wire:navigate>Equipe</flux:button>.
                </flux:text>
            </div>

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button variant="ghost">Fechar</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

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
