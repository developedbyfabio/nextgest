<div class="flex flex-col gap-6 p-6 lg:p-8" style="{{ \App\Support\Aparencia::cssVars() }}">
    {{-- Cabeçalho: abas de quadro + ações --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-2">
            <flux:button wire:click="trocarQuadro('atendimento')" size="sm"
                :variant="$tipo === 'atendimento' ? 'primary' : 'ghost'" icon="queue-list">Atendimento</flux:button>
            @if ($mostrarCRM)
                <flux:button wire:click="trocarQuadro('crm')" size="sm"
                    :variant="$tipo === 'crm' ? 'primary' : 'ghost'" icon="users">CRM</flux:button>
            @endif
        </div>

        <div class="flex items-center gap-2">
            @if ($tipo === 'atendimento')
                <flux:button wire:click="gerarCartoesDoDia" variant="ghost" size="sm" icon="calendar-days">Gerar do dia</flux:button>
            @endif
            @if ($podeGerir)
                <flux:button wire:click="novaColuna" variant="primary" size="sm" icon="plus">Nova coluna</flux:button>
            @endif
        </div>
    </div>

    {{-- Quadro: colunas com rolagem horizontal --}}
    <div class="flex gap-4 overflow-x-auto pb-4">
        @forelse ($quadro->colunas as $coluna)
            <div wire:key="coluna-{{ $coluna->id }}" class="flex w-72 shrink-0 flex-col rounded-xl bg-zinc-50 dark:bg-zinc-900/60">
                {{-- Cabeçalho da coluna --}}
                <div class="flex items-center justify-between border-b border-zinc-200 px-3 py-2 dark:border-zinc-700"
                    style="border-top: 3px solid var(--cor-principal); border-radius: 0.75rem 0.75rem 0 0;">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold">{{ $coluna->nome }}</span>
                        <flux:badge size="sm" color="zinc">{{ $coluna->cartoes->count() }}</flux:badge>
                    </div>
                    @if ($podeGerir)
                        <flux:dropdown position="bottom" align="end">
                            <flux:button variant="ghost" size="xs" icon="ellipsis-horizontal" inset />
                            <flux:menu>
                                <flux:menu.item icon="pencil-square" wire:click="editarColuna({{ $coluna->id }})">Renomear</flux:menu.item>
                                <flux:menu.item icon="trash" variant="danger"
                                    wire:click="removerColuna({{ $coluna->id }})"
                                    wire:confirm="Remover a coluna e seus cartões?">Remover coluna</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    @endif
                </div>

                {{-- Lista de cartões (Sortable) --}}
                <div class="flex min-h-16 flex-col gap-2 p-2"
                    x-data="kanbanColuna" data-coluna-id="{{ $coluna->id }}" wire:key="lista-{{ $coluna->id }}">
                    @forelse ($coluna->cartoes as $c)
                        <div wire:key="cartao-{{ $c->id }}" data-cartao-id="{{ $c->id }}"
                            class="group cursor-grab rounded-lg border border-zinc-200 bg-white p-3 shadow-sm transition hover:shadow dark:border-zinc-700 dark:bg-zinc-800"
                            style="border-left: 3px solid var(--cor-principal);">
                            <div class="flex items-start justify-between gap-2">
                                <span class="text-sm font-medium">{{ $c->titulo }}</span>
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button variant="ghost" size="xs" icon="ellipsis-vertical" inset />
                                    <flux:menu>
                                        <flux:menu.item icon="pencil-square" wire:click="editarCartao({{ $c->id }})">Editar</flux:menu.item>
                                        <flux:menu.submenu heading="Mover para" icon="arrows-right-left">
                                            @foreach ($quadro->colunas as $destino)
                                                <flux:menu.item wire:click="moverCartaoParaColuna({{ $c->id }}, {{ $destino->id }})"
                                                    :disabled="$destino->id === $coluna->id">{{ $destino->nome }}</flux:menu.item>
                                            @endforeach
                                        </flux:menu.submenu>
                                        <flux:menu.separator />
                                        <flux:menu.item icon="trash" variant="danger"
                                            wire:click="removerCartao({{ $c->id }})" wire:confirm="Remover este cartão?">Remover</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </div>

                            @if ($c->descricao)
                                <p class="mt-1 line-clamp-2 text-xs text-zinc-500">{{ $c->descricao }}</p>
                            @endif

                            <div class="mt-2 flex flex-wrap items-center gap-1">
                                @if ($c->cliente)
                                    <flux:badge size="sm" color="blue" icon="user">{{ $c->cliente->nome }}</flux:badge>
                                @endif
                                @if ($c->agendamento)
                                    <flux:badge size="sm" color="purple" icon="calendar-days">{{ $c->agendamento->data_hora_inicio->format('d/m H:i') }}</flux:badge>
                                @endif
                                @if ($c->responsavel)
                                    <flux:badge size="sm" color="zinc">{{ $c->responsavel->name }}</flux:badge>
                                @endif
                                @if ($c->prazo)
                                    <flux:badge size="sm" :color="$c->prazo->isPast() ? 'red' : 'amber'" icon="clock">{{ $c->prazo->format('d/m') }}</flux:badge>
                                @endif
                                @if ($c->valor_estimado)
                                    <flux:badge size="sm" color="green">R$ {{ number_format((float) $c->valor_estimado, 2, ',', '.') }}</flux:badge>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-zinc-200 py-6 text-center text-xs text-zinc-400 dark:border-zinc-700">
                            Sem cartões
                        </div>
                    @endforelse
                </div>

                <button type="button" wire:click="novoCartao({{ $coluna->id }})"
                    class="m-2 flex items-center justify-center gap-1 rounded-lg border border-dashed border-zinc-300 py-2 text-sm text-zinc-500 transition hover:border-zinc-400 hover:text-zinc-700 dark:border-zinc-600 dark:hover:text-zinc-300">
                    <flux:icon name="plus" class="size-4" /> Adicionar cartão
                </button>
            </div>
        @empty
            <div class="w-full">
                <x-ng.empty icon="view-columns" title="Quadro sem colunas"
                    text="{{ $podeGerir ? 'Crie a primeira coluna para começar.' : 'Peça a um gestor para configurar as colunas.' }}" />
            </div>
        @endforelse
    </div>

    {{-- Modal: cartão --}}
    <flux:modal wire:model.self="mostrarCartao" class="md:w-[32rem]">
        <form wire:submit="salvarCartao" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ $cartaoId ? 'Editar cartão' : 'Novo cartão' }}</flux:heading>

            <flux:input wire:model="titulo" label="Título" required />
            <flux:textarea wire:model="descricao" label="Descrição" rows="2" />

            <div class="grid grid-cols-2 gap-3">
                <flux:select wire:model="colunaId" label="Coluna">
                    @foreach ($quadro->colunas as $coluna)
                        <flux:select.option value="{{ $coluna->id }}">{{ $coluna->nome }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="responsavelId" label="Responsável" placeholder="—">
                    <flux:select.option value="">—</flux:select.option>
                    @foreach ($equipe as $u)
                        <flux:select.option value="{{ $u->id }}">{{ $u->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="clienteId" label="Cliente (opcional)" placeholder="—">
                    <flux:select.option value="">—</flux:select.option>
                    @foreach ($clientes as $cli)
                        <flux:select.option value="{{ $cli->id }}">{{ $cli->nome }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="agendamentoId" label="Agendamento (opcional)" placeholder="—">
                    <flux:select.option value="">—</flux:select.option>
                    @foreach ($agendamentos as $ag)
                        <flux:select.option value="{{ $ag->id }}">{{ $ag->cliente?->nome }} · {{ $ag->data_hora_inicio->format('d/m H:i') }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input type="date" wire:model="prazo" label="Prazo (opcional)" />
                <flux:input type="number" step="0.01" wire:model="valorEstimado" label="Valor estimado (opcional)" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Salvar</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal: coluna --}}
    <flux:modal wire:model.self="mostrarColuna" class="md:w-96">
        <form wire:submit="salvarColuna" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ $colunaEditId ? 'Renomear coluna' : 'Nova coluna' }}</flux:heading>
            <flux:input wire:model="nomeColuna" label="Nome" required />
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Salvar</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
