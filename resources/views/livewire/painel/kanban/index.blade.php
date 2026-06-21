<div class="flex flex-col gap-6 p-6 lg:p-8" style="{{ \App\Support\Aparencia::cssVarsAcento() }}">
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

    {{-- Loading: skeleton de colunas ao trocar de quadro / gerar do dia --}}
    <div wire:loading.flex.delay wire:target="trocarQuadro, gerarCartoesDoDia" class="gap-4 overflow-x-auto pb-4">
        @for ($i = 0; $i < 3; $i++)
            <div class="ng-surface-muted flex w-80 shrink-0 flex-col gap-2 rounded-xl p-3">
                <div class="ng-skeleton-portal h-5 w-32"></div>
                @for ($j = 0; $j < 3; $j++)
                    <div class="ng-skeleton-portal h-20 w-full"></div>
                @endfor
            </div>
        @endfor
    </div>

    {{-- Quadro: colunas com rolagem horizontal + snap (responsivo no celular) --}}
    <div wire:loading.remove.delay wire:target="trocarQuadro, gerarCartoesDoDia"
        class="flex snap-x snap-mandatory gap-4 overflow-x-auto scroll-smooth pb-4">
        @forelse ($quadro->colunas as $coluna)
            <div wire:key="coluna-{{ $coluna->id }}"
                class="ng-surface-muted flex w-[85vw] max-w-xs shrink-0 snap-start flex-col rounded-xl sm:w-80">
                {{-- Cabeçalho da coluna: acento da marca + contador --}}
                <div class="flex items-center justify-between border-b px-3 py-2"
                    style="border-top: 3px solid var(--cor-principal); border-radius: 0.85rem 0.85rem 0 0; border-bottom-color: color-mix(in srgb, var(--cor-texto) 10%, transparent);">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold" style="color: var(--cor-texto);">{{ $coluna->nome }}</span>
                        <span class="inline-flex min-w-5 items-center justify-center rounded-full px-1.5 text-xs font-semibold"
                            style="background-color: color-mix(in srgb, var(--cor-principal) 15%, transparent); color: var(--cor-principal);">{{ $coluna->cartoes->count() }}</span>
                    </div>
                    @if ($podeGerir)
                        <flux:dropdown position="bottom" align="end">
                            <flux:button variant="ghost" size="xs" icon="ellipsis-horizontal" inset />
                            <flux:menu>
                                <flux:menu.item icon="pencil-square" wire:click="editarColuna({{ $coluna->id }})">Renomear</flux:menu.item>
                                <flux:menu.item icon="trash" variant="danger" wire:click="pedirRemoverColuna({{ $coluna->id }})">Remover coluna</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    @endif
                </div>

                {{-- Lista de cartões (Sortable; arraste pelo handle) --}}
                <div class="flex min-h-20 flex-1 flex-col gap-2 p-2"
                    x-data="kanbanColuna" data-coluna-id="{{ $coluna->id }}" wire:key="lista-{{ $coluna->id }}">
                    @forelse ($coluna->cartoes as $c)
                        <div wire:key="cartao-{{ $c->id }}" data-cartao-id="{{ $c->id }}"
                            class="ng-surface group p-3 shadow-sm transition hover:shadow"
                            style="border-left: 3px solid var(--cor-principal);">
                            <div class="flex items-start gap-2">
                                <button type="button" data-kanban-handle aria-label="Arrastar"
                                    class="ng-kanban-handle -ms-1 mt-0.5 shrink-0 rounded p-0.5"
                                    style="color: var(--cor-texto-suave);">
                                    <flux:icon name="bars-2" class="size-4" />
                                </button>

                                <span class="min-w-0 flex-1 text-sm font-medium" style="color: var(--cor-texto);">{{ $c->titulo }}</span>

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
                                        <flux:menu.item icon="archive-box" variant="danger" wire:click="pedirArquivarCartao({{ $c->id }})">Arquivar</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </div>

                            @if ($c->descricao)
                                <p class="mt-1 line-clamp-2 ps-6 text-xs" style="color: var(--cor-texto-suave);">{{ $c->descricao }}</p>
                            @endif

                            <div class="mt-2 flex flex-wrap items-center gap-1 ps-6">
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
                        {{-- Coluna vazia: orientação (e alvo de drop) --}}
                        <div class="flex flex-col items-center gap-1 rounded-lg border border-dashed py-6 text-center"
                            style="border-color: color-mix(in srgb, var(--cor-texto) 15%, transparent); color: var(--cor-texto-suave);">
                            <flux:icon name="inbox" class="size-5" />
                            <span class="text-xs">Arraste cartões para cá</span>
                        </div>
                    @endforelse
                </div>

                <button type="button" wire:click="novoCartao({{ $coluna->id }})"
                    class="m-2 flex items-center justify-center gap-1 rounded-lg border border-dashed py-2 text-sm transition hover:opacity-80"
                    style="border-color: color-mix(in srgb, var(--cor-texto) 20%, transparent); color: var(--cor-texto-suave);">
                    <flux:icon name="plus" class="size-4" /> Adicionar cartão
                </button>
            </div>
        @empty
            <div class="w-full">
                <x-ng.empty themed icon="view-columns" title="Quadro sem colunas"
                    text="{{ $podeGerir ? 'Crie a primeira coluna para começar.' : 'Peça a um gestor para configurar as colunas.' }}">
                    @if ($podeGerir)
                        <flux:button wire:click="novaColuna" variant="primary" size="sm" icon="plus" class="mt-2">Nova coluna</flux:button>
                    @endif
                </x-ng.empty>
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

    {{-- Modal: confirmar arquivamento de cartão (sem confirm nativo) --}}
    <flux:modal name="arquivar-cartao" class="max-w-sm">
        <div class="flex flex-col gap-4">
            <div class="flex items-center gap-3">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-500/15">
                    <flux:icon name="archive-box" class="size-6" />
                </span>
                <div>
                    <flux:heading size="lg">Arquivar cartão?</flux:heading>
                    <flux:text class="mt-1">Ele sai do quadro, mas não é apagado (pode ser restaurado por um gestor).</flux:text>
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Voltar</flux:button>
                </flux:modal.close>
                @if ($confirmarCartao)
                    <flux:button wire:click="removerCartao({{ $confirmarCartao }})" variant="primary" icon="archive-box">Arquivar</flux:button>
                @endif
            </div>
        </div>
    </flux:modal>

    {{-- Modal: confirmar remoção de coluna (sem confirm nativo) --}}
    <flux:modal name="remover-coluna" class="max-w-sm">
        <div class="flex flex-col gap-4">
            <div class="flex items-center gap-3">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-red-500/15">
                    <flux:icon name="exclamation-triangle" class="size-6" />
                </span>
                <div>
                    <flux:heading size="lg">Remover coluna?</flux:heading>
                    <flux:text class="mt-1">A coluna e os cartões dela serão removidos. Esta ação não pode ser desfeita.</flux:text>
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Voltar</flux:button>
                </flux:modal.close>
                @if ($confirmarColuna)
                    <flux:button wire:click="removerColuna({{ $confirmarColuna }})" variant="danger" icon="trash">Remover</flux:button>
                @endif
            </div>
        </div>
    </flux:modal>
</div>
