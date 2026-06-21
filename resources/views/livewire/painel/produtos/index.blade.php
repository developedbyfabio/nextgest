@php($alvosFiltro = 'busca,categoriaFiltro,statusFiltro,page')
<div class="flex flex-col gap-6 p-6 lg:p-8">
    <x-ng.page-header title="Produtos" subtitle="Catálogo, categorias e estoque por unidade">
        <x-slot:actions>
            @can('editar_produto')
                <flux:button wire:click="abrirCategorias" variant="ghost" icon="tag">Categorias</flux:button>
            @endcan
            @can('criar_produto')
                <flux:button wire:click="novo" variant="primary" icon="plus">Novo produto</flux:button>
            @endcan
        </x-slot:actions>
    </x-ng.page-header>

    {{-- Filtros --}}
    <div class="flex flex-wrap items-end gap-3">
        <flux:input wire:model.live.debounce.300ms="busca" icon="magnifying-glass" placeholder="Buscar por nome ou SKU" class="min-w-56 flex-1" />
        <flux:select wire:model.live="categoriaFiltro" label="Categoria" class="min-w-44">
            <flux:select.option value="">Todas</flux:select.option>
            @foreach ($categorias as $cat)
                <flux:select.option value="{{ $cat->id }}">{{ $cat->nome }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="statusFiltro" label="Status" class="min-w-40">
            <flux:select.option value="ativos">Ativos</flux:select.option>
            <flux:select.option value="inativos">Inativos</flux:select.option>
            <flux:select.option value="todos">Todos</flux:select.option>
        </flux:select>
    </div>

    {{-- Loading (skeleton) --}}
    <div wire:loading.delay.flex wire:target="{{ $alvosFiltro }}" class="flex-col gap-2">
        @for ($i = 0; $i < 6; $i++)
            <div class="ng-skeleton-portal h-12 w-full"></div>
        @endfor
    </div>

    <div wire:loading.remove.delay wire:target="{{ $alvosFiltro }}" class="flex flex-col gap-4">
        @if ($produtos->isEmpty())
            <x-ng.empty themed icon="cube" title="Nenhum produto encontrado"
                text="{{ $busca || $categoriaFiltro || $statusFiltro !== 'ativos' ? 'Ajuste a busca ou os filtros.' : 'Cadastre o primeiro produto do catálogo.' }}">
                @can('criar_produto')
                    <flux:button wire:click="novo" variant="primary" size="sm" icon="plus" class="mt-2">Novo produto</flux:button>
                @endcan
            </x-ng.empty>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Produto</flux:table.column>
                    <flux:table.column>Categoria</flux:table.column>
                    <flux:table.column>Preço</flux:table.column>
                    <flux:table.column>Estoque</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column />
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($produtos as $produto)
                        <flux:table.row :key="$produto->id">
                            <flux:table.cell variant="strong">
                                {{ $produto->nome }}
                                @if ($produto->sku)
                                    <flux:text class="text-xs" style="color: var(--cor-texto-suave);">SKU {{ $produto->sku }}</flux:text>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $produto->categoria?->nome ?? '—' }}</flux:table.cell>
                            <flux:table.cell>R$ {{ number_format((float) $produto->preco_venda, 2, ',', '.') }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($produto->controla_estoque)
                                    <flux:badge size="sm" :color="$produto->estoque_total > 0 ? 'green' : 'red'">{{ $produto->estoque_total }} un.</flux:badge>
                                @else
                                    <flux:text class="text-xs" style="color: var(--cor-texto-suave);">não controla</flux:text>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="$produto->ativo ? 'green' : 'zinc'" size="sm">{{ $produto->ativo ? 'Ativo' : 'Inativo' }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="text-right whitespace-nowrap">
                                @if ($produto->controla_estoque && $podeGerirEstoque)
                                    <flux:button wire:click="abrirEstoque({{ $produto->id }})" size="sm" variant="ghost" icon="archive-box">Estoque</flux:button>
                                @endif
                                @can('editar_produto')
                                    <flux:button wire:click="editar({{ $produto->id }})" size="sm" variant="ghost" icon="pencil-square">Editar</flux:button>
                                    @if ($produto->ativo)
                                        <flux:button wire:click="pedirInativar({{ $produto->id }})" size="sm" variant="subtle" icon="eye-slash">Inativar</flux:button>
                                    @else
                                        <flux:button wire:click="reativar({{ $produto->id }})" size="sm" variant="subtle" icon="eye">Reativar</flux:button>
                                    @endif
                                @endcan
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <div>{{ $produtos->links() }}</div>
        @endif
    </div>

    {{-- Modal: produto --}}
    <flux:modal wire:model.self="mostrarForm" class="md:w-[34rem]">
        <form wire:submit="salvar" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ $editandoId ? 'Editar produto' : 'Novo produto' }}</flux:heading>

            <flux:input wire:model="nome" label="Nome" placeholder="Ex.: Pomada modeladora" required />
            <flux:textarea wire:model="descricao" label="Descrição" placeholder="Opcional" rows="2" />

            <div class="grid grid-cols-2 gap-3">
                <flux:select wire:model="categoriaId" label="Categoria" placeholder="—">
                    <flux:select.option value="">Sem categoria</flux:select.option>
                    @foreach ($categorias as $cat)
                        <flux:select.option value="{{ $cat->id }}">{{ $cat->nome }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="sku" label="SKU / código" placeholder="Opcional" />
                <flux:input wire:model="precoVenda" type="number" step="0.01" min="0" label="Preço de venda (R$)" required />
                <flux:input wire:model="precoCusto" type="number" step="0.01" min="0" label="Preço de custo (R$)" placeholder="Opcional" />
                <flux:input wire:model="percentualComissao" type="number" step="0.01" min="0" max="100" label="Comissão padrão (%)" placeholder="Opcional" />
            </div>

            <flux:switch wire:model="controlaEstoque" label="Controla estoque" description="Dá baixa ao vender e permite gerir quantidade por unidade." />
            <flux:switch wire:model="ativo" label="Ativo" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Salvar</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal: categorias --}}
    <flux:modal wire:model.self="mostrarCategorias" class="md:w-96">
        <div class="flex flex-col gap-4">
            <flux:heading size="lg">Categorias</flux:heading>

            <form wire:submit="salvarCategoria" class="flex items-end gap-2">
                <flux:input wire:model="categoriaNome" label="{{ $categoriaEditId ? 'Renomear' : 'Nova categoria' }}" class="flex-1" />
                <flux:button type="submit" variant="primary" icon="{{ $categoriaEditId ? 'check' : 'plus' }}" />
            </form>

            <div class="flex flex-col divide-y" style="border-color: color-mix(in srgb, var(--cor-texto) 10%, transparent);">
                @forelse ($categorias as $cat)
                    <div wire:key="cat-{{ $cat->id }}" class="flex items-center justify-between gap-2 py-2">
                        <span class="flex items-center gap-2">
                            <span style="color: var(--cor-texto);">{{ $cat->nome }}</span>
                            @unless ($cat->ativo)<flux:badge size="sm" color="zinc">Inativa</flux:badge>@endunless
                        </span>
                        <span class="flex items-center gap-1">
                            <flux:button wire:click="editarCategoria({{ $cat->id }})" size="xs" variant="ghost" icon="pencil-square" inset />
                            <flux:button wire:click="alternarCategoria({{ $cat->id }})" size="xs" variant="ghost" :icon="$cat->ativo ? 'eye-slash' : 'eye'" inset />
                        </span>
                    </div>
                @empty
                    <flux:text class="py-2 text-sm" style="color: var(--cor-texto-suave);">Nenhuma categoria ainda.</flux:text>
                @endforelse
            </div>

            <div class="flex justify-end">
                <flux:modal.close><flux:button variant="ghost">Fechar</flux:button></flux:modal.close>
            </div>
        </div>
    </flux:modal>

    {{-- Modal: estoque por unidade --}}
    <flux:modal wire:model.self="mostrarEstoque" class="md:w-[34rem]">
        <div class="flex flex-col gap-4">
            <flux:heading size="lg">Estoque por unidade</flux:heading>

            {{-- Estoque atual por filial --}}
            <div class="ng-surface flex flex-col divide-y" style="border-color: color-mix(in srgb, var(--cor-texto) 10%, transparent);">
                @forelse ($unidades as $u)
                    <div wire:key="estu-{{ $u->id }}" class="flex items-center justify-between px-4 py-2">
                        <span style="color: var(--cor-texto);">{{ $u->nome }}</span>
                        <flux:badge size="sm" :color="(int) ($estoquePorUnidade[$u->id]->quantidade ?? 0) > 0 ? 'green' : 'red'">
                            {{ (int) ($estoquePorUnidade[$u->id]->quantidade ?? 0) }} un.
                        </flux:badge>
                    </div>
                @empty
                    <div class="px-4 py-3 text-sm" style="color: var(--cor-texto-suave);">Nenhuma unidade ativa.</div>
                @endforelse
            </div>

            {{-- Registrar movimentação --}}
            <form wire:submit="registrarMovimentacao" class="flex flex-col gap-3">
                <div class="grid grid-cols-2 gap-3">
                    <flux:select wire:model="movUnidadeId" label="Unidade">
                        @foreach ($unidades as $u)
                            <flux:select.option value="{{ $u->id }}">{{ $u->nome }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model="movTipo" label="Tipo">
                        <flux:select.option value="entrada">Entrada (somar)</flux:select.option>
                        <flux:select.option value="ajuste">Ajuste (definir total)</flux:select.option>
                    </flux:select>
                    <flux:input wire:model="movQuantidade" type="number" min="0" label="Quantidade" required />
                    <flux:input wire:model="movMotivo" label="Motivo" placeholder="Opcional" />
                </div>
                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary" icon="plus">Registrar</flux:button>
                </div>
            </form>

            {{-- Histórico recente --}}
            <div class="flex flex-col gap-2">
                <flux:heading size="sm" style="color: var(--cor-texto);">Movimentações recentes</flux:heading>
                @forelse ($movimentacoes as $m)
                    <div wire:key="mov-{{ $m->id }}" class="flex items-center justify-between rounded-lg px-3 py-2 text-sm ng-surface-muted">
                        <span class="flex flex-col">
                            <span style="color: var(--cor-texto);" class="capitalize">{{ $m->tipo }} · {{ $m->unidade?->nome }}</span>
                            <span class="text-xs" style="color: var(--cor-texto-suave);">{{ $m->created_at->format('d/m/Y H:i') }}{{ $m->motivo ? ' · '.$m->motivo : '' }}</span>
                        </span>
                        <flux:badge size="sm" :color="$m->quantidade >= 0 ? 'green' : 'red'">{{ $m->quantidade >= 0 ? '+' : '' }}{{ $m->quantidade }}</flux:badge>
                    </div>
                @empty
                    <flux:text class="text-sm" style="color: var(--cor-texto-suave);">Sem movimentações ainda.</flux:text>
                @endforelse
            </div>

            <div class="flex justify-end">
                <flux:modal.close><flux:button variant="ghost">Fechar</flux:button></flux:modal.close>
            </div>
        </div>
    </flux:modal>

    {{-- Modal: confirmar inativação --}}
    <flux:modal name="inativar-produto" class="max-w-sm">
        <div class="flex flex-col gap-4">
            <div class="flex items-center gap-3">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-500/15">
                    <flux:icon name="eye-slash" class="size-6" />
                </span>
                <div>
                    <flux:heading size="lg">Inativar produto?</flux:heading>
                    <flux:text class="mt-1">Ele sai das listas de venda, mas não é apagado (pode reativar depois).</flux:text>
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Voltar</flux:button></flux:modal.close>
                @if ($confirmarInativar)
                    <flux:button wire:click="inativar({{ $confirmarInativar }})" variant="primary" icon="eye-slash">Inativar</flux:button>
                @endif
            </div>
        </div>
    </flux:modal>
</div>
