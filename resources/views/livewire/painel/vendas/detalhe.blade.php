@php($statusCor = ['aberta' => 'amber', 'paga' => 'green', 'cancelada' => 'zinc'])
<div class="flex flex-col gap-6 p-6 lg:p-8">
    {{-- Cabeçalho --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-start gap-3">
            <flux:button :href="route('painel.vendas', ['tenant' => tenant('id')])" variant="ghost" size="sm" icon="arrow-left" wire:navigate aria-label="Voltar" />
            <div>
                <div class="flex items-center gap-2">
                    <flux:heading size="xl" style="color: var(--cor-texto);">Comanda #{{ $venda->id }}</flux:heading>
                    <flux:badge :color="$statusCor[$venda->status] ?? 'zinc'">{{ \App\Models\Venda::STATUS_LABEL[$venda->status] ?? $venda->status }}</flux:badge>
                </div>
                <flux:subheading style="color: var(--cor-texto-suave);">
                    {{ $venda->cliente?->nome ?? 'Balcão (anônimo)' }} · {{ $venda->unidade?->nome }} · {{ $venda->data->format('d/m/Y H:i') }}
                    @if ($venda->agendamento_id) · <span style="color: var(--cor-principal);">de atendimento</span> @endif
                </flux:subheading>
            </div>
        </div>

        <div class="flex items-center gap-2">
            @if ($editavel)
                <flux:button wire:click="pedirCancelar" variant="ghost">Cancelar</flux:button>
                <flux:button wire:click="pedirPagar" variant="primary" icon="banknotes" :disabled="$venda->itens->isEmpty()">Fechar e pagar</flux:button>
            @elseif ($venda->status === 'paga')
                <flux:button wire:click="pedirCancelar" variant="ghost" icon="arrow-uturn-left">Cancelar (estorna)</flux:button>
            @endif
        </div>
    </div>

    {{-- Cliente (travado na finalização) + quem vendeu/atendeu --}}
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="ng-surface flex items-start gap-3 p-4">
            <flux:icon name="user" class="mt-0.5 size-5 shrink-0" style="color: var(--cor-principal);" />
            <div class="min-w-0">
                <div class="text-xs" style="color: var(--cor-texto-suave);">Cliente</div>
                <div class="flex items-center gap-1.5 font-medium" style="color: var(--cor-texto);">
                    <span class="truncate">{{ $venda->cliente?->nome ?? 'Balcão (anônimo)' }}</span>
                    @if ($venda->agendamento_id)
                        <flux:icon name="lock-closed" class="size-3.5 shrink-0" style="color: var(--cor-texto-suave);" />
                    @endif
                </div>
                @if ($venda->agendamento_id)
                    <div class="text-xs" style="color: var(--cor-texto-suave);">Do atendimento — não editável.</div>
                @endif
            </div>
        </div>

        <div class="ng-surface flex flex-col gap-1 p-4">
            <div class="text-xs" style="color: var(--cor-texto-suave);">Quem vendeu/atendeu</div>
            @if ($editavel && ! $venda->agendamento_id)
                <flux:select wire:model.live="vendedorId" placeholder="— não definido —">
                    <flux:select.option value="">— não definido —</flux:select.option>
                    @foreach ($profissionais as $p)
                        <flux:select.option value="{{ $p->id }}">{{ $p->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <div class="text-xs" style="color: var(--cor-texto-suave);">Pré-preenche o profissional dos itens novos (comissão por item).</div>
            @else
                <div class="flex items-center gap-1.5 font-medium" style="color: var(--cor-texto);">
                    <span class="truncate">{{ $venda->profissional?->name ?? '— não definido —' }}</span>
                    @if ($venda->agendamento_id)
                        <flux:icon name="lock-closed" class="size-3.5 shrink-0" style="color: var(--cor-texto-suave);" />
                    @endif
                </div>
                @if ($venda->agendamento_id)
                    <div class="text-xs" style="color: var(--cor-texto-suave);">Quem atendeu — não editável.</div>
                @endif
            @endif
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Itens --}}
        <div class="flex flex-col gap-3 lg:col-span-2">
            <div class="flex items-center justify-between">
                <flux:heading size="sm" style="color: var(--cor-texto);">Itens</flux:heading>
                @if ($editavel)
                    <flux:button wire:click="abrirItem" size="sm" variant="primary" icon="plus">Adicionar item</flux:button>
                @endif
            </div>

            @forelse ($venda->itens as $item)
                <div wire:key="item-{{ $item->id }}" class="ng-surface flex items-center justify-between gap-3 p-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <flux:badge size="sm" :color="$item->tipo === 'produto' ? 'blue' : 'purple'" :icon="$item->tipo === 'produto' ? 'cube' : 'scissors'">{{ ucfirst($item->tipo) }}</flux:badge>
                            <span class="font-medium" style="color: var(--cor-texto);">{{ $item->descricao }}</span>
                        </div>
                        <div class="mt-1 text-sm" style="color: var(--cor-texto-suave);">
                            {{ $item->quantidade }} × R$ {{ number_format((float) $item->preco_unitario, 2, ',', '.') }}
                            @if ($item->profissional) · {{ $item->profissional->name }} @endif
                            @if ($item->valor_comissao) · <span style="color: var(--cor-texto-suave);">comissão R$ {{ number_format((float) $item->valor_comissao, 2, ',', '.') }}</span> @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-2 whitespace-nowrap">
                        <span class="font-semibold" style="color: var(--cor-texto);">R$ {{ number_format((float) $item->subtotal, 2, ',', '.') }}</span>
                        @if ($editavel)
                            <flux:button wire:click="removerItem({{ $item->id }})" size="xs" variant="subtle" icon="trash" inset aria-label="Remover item" />
                        @endif
                    </div>
                </div>
            @empty
                <x-ng.empty themed icon="shopping-cart" title="Comanda vazia" text="{{ $editavel ? 'Adicione produtos e serviços.' : 'Nenhum item.' }}" />
            @endforelse
        </div>

        {{-- Resumo / totais --}}
        <div class="flex h-fit flex-col gap-3 ng-surface p-5">
            <flux:heading size="sm" style="color: var(--cor-texto);">Resumo</flux:heading>

            <div class="flex items-center justify-between text-sm">
                <span style="color: var(--cor-texto-suave);">Subtotal</span>
                <span style="color: var(--cor-texto);">R$ {{ number_format((float) $venda->valor_bruto, 2, ',', '.') }}</span>
            </div>

            @if ($editavel)
                <flux:input wire:model.blur="desconto" type="number" step="0.01" min="0" label="Desconto (R$)" />
            @else
                <div class="flex items-center justify-between text-sm">
                    <span style="color: var(--cor-texto-suave);">Desconto</span>
                    <span style="color: var(--cor-texto);">− R$ {{ number_format((float) $venda->desconto, 2, ',', '.') }}</span>
                </div>
            @endif

            <div class="flex items-center justify-between border-t pt-3" style="border-color: color-mix(in srgb, var(--cor-texto) 12%, transparent);">
                <span class="font-semibold" style="color: var(--cor-texto);">Total</span>
                <span class="text-xl font-bold" style="color: var(--cor-principal);">R$ {{ number_format((float) $venda->valor_total, 2, ',', '.') }}</span>
            </div>

            @if ($venda->status === 'paga' && $comissaoTotal > 0)
                <div class="flex items-center justify-between text-xs" style="color: var(--cor-texto-suave);">
                    <span>Comissão total</span>
                    <span>R$ {{ number_format($comissaoTotal, 2, ',', '.') }}</span>
                </div>
            @endif

            {{-- Pagamentos registrados --}}
            @if ($venda->pagamentos->isNotEmpty())
                <div class="mt-1 flex flex-col gap-1 border-t pt-3" style="border-color: color-mix(in srgb, var(--cor-texto) 12%, transparent);">
                    <flux:text class="text-xs font-medium" style="color: var(--cor-texto-suave);">Pagamentos</flux:text>
                    @foreach ($venda->pagamentos as $pg)
                        <div class="flex items-center justify-between text-sm" wire:key="pg-{{ $pg->id }}">
                            <span class="flex items-center gap-2" style="color: var(--cor-texto);">
                                {{ $metodos[$pg->metodo] ?? $pg->metodo }}
                                @if ($pg->status !== 'aprovado')
                                    <flux:badge size="sm" color="zinc">{{ $pg->status }}</flux:badge>
                                @endif
                            </span>
                            <span style="color: var(--cor-texto);">R$ {{ number_format((float) $pg->valor, 2, ',', '.') }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Modal: adicionar item --}}
    <flux:modal wire:model.self="mostrarItem" class="md:w-[30rem]">
        <form wire:submit="adicionarItem" class="flex flex-col gap-4">
            <flux:heading size="lg">Adicionar item</flux:heading>

            <flux:radio.group wire:model.live="tipoItem" label="Tipo" variant="segmented">
                <flux:radio value="produto" label="Produto" />
                <flux:radio value="servico" label="Serviço" />
            </flux:radio.group>

            @if ($tipoItem === 'produto')
                <flux:select wire:model="itemRefId" label="Produto" placeholder="Escolha um produto">
                    @foreach ($produtos as $p)
                        <flux:select.option value="{{ $p->id }}">{{ $p->nome }} — R$ {{ number_format((float) $p->preco_venda, 2, ',', '.') }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="itemQtd" type="number" min="1" label="Quantidade" />
            @else
                <flux:select wire:model="itemRefId" label="Serviço" placeholder="Escolha um serviço">
                    @foreach ($servicos as $s)
                        <flux:select.option value="{{ $s->id }}">{{ $s->nome }} — R$ {{ number_format((float) $s->preco, 2, ',', '.') }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:select wire:model="itemProfissionalId" label="Profissional (opcional)" placeholder="—">
                <flux:select.option value="">—</flux:select.option>
                @foreach ($profissionais as $prof)
                    <flux:select.option value="{{ $prof->id }}">{{ $prof->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" icon="plus">Adicionar</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal: fechar e pagar (presencial) --}}
    <flux:modal name="pagar-comanda" class="md:w-[30rem]">
        <div class="flex flex-col gap-4">
            <flux:heading size="lg">Fechar e pagar</flux:heading>

            <div class="ng-surface flex items-center justify-between p-3">
                <flux:text style="color: var(--cor-texto-suave);">Total da comanda</flux:text>
                <span class="text-lg font-bold" style="color: var(--cor-principal);">R$ {{ number_format($totalVenda, 2, ',', '.') }}</span>
            </div>

            {{-- Formas de pagamento (1+; soma deve = total) --}}
            <div class="flex flex-col gap-2">
                @foreach ($pagamentos as $i => $pg)
                    <div class="flex items-end gap-2" wire:key="pgline-{{ $i }}">
                        <flux:select wire:model.live="pagamentos.{{ $i }}.metodo" label="{{ $i === 0 ? 'Forma' : '' }}" class="flex-1">
                            @foreach ($metodos as $valor => $rotulo)
                                <flux:select.option value="{{ $valor }}">{{ $rotulo }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:input wire:model.live.debounce.400ms="pagamentos.{{ $i }}.valor" type="number" step="0.01" min="0" label="{{ $i === 0 ? 'Valor (R$)' : '' }}" class="w-32" />
                        @if (count($pagamentos) > 1)
                            <flux:button wire:click="removerFormaPagamento({{ $i }})" variant="subtle" size="sm" icon="x-mark" aria-label="Remover forma" />
                        @endif
                    </div>
                @endforeach

                <flux:button wire:click="adicionarFormaPagamento" variant="ghost" size="sm" icon="plus" class="self-start">Dividir pagamento</flux:button>
            </div>

            {{-- Somatório / validação ao vivo --}}
            <div class="flex items-center justify-between rounded-lg px-3 py-2 text-sm ng-surface-muted">
                <span style="color: var(--cor-texto-suave);">Somado</span>
                <span class="font-semibold" style="color: var(--cor-texto);">R$ {{ number_format($somaPagamentos, 2, ',', '.') }}</span>
            </div>
            @if (abs($faltaPagamento) >= 0.01)
                <flux:callout :variant="$faltaPagamento > 0 ? 'warning' : 'danger'" :icon="$faltaPagamento > 0 ? 'exclamation-triangle' : 'x-circle'">
                    <flux:callout.text>
                        @if ($faltaPagamento > 0)
                            Falta R$ {{ number_format($faltaPagamento, 2, ',', '.') }} para fechar o total.
                        @else
                            Excede R$ {{ number_format(abs($faltaPagamento), 2, ',', '.') }} — ajuste para igualar o total.
                        @endif
                    </flux:callout.text>
                </flux:callout>
            @endif

            {{-- Troco (opcional, só para dinheiro; não grava pagamento acima do total) --}}
            @if ($temDinheiro)
                <div class="flex items-end gap-3">
                    <flux:input wire:model.live.debounce.400ms="valorRecebido" type="number" step="0.01" min="0" label="Valor recebido (dinheiro)" placeholder="Opcional" class="flex-1" />
                    <div class="pb-2 text-sm whitespace-nowrap" style="color: var(--cor-texto-suave);">
                        Troco: <span class="font-semibold" style="color: var(--cor-texto);">R$ {{ number_format($troco, 2, ',', '.') }}</span>
                    </div>
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Voltar</flux:button></flux:modal.close>
                <flux:button wire:click="pagar" variant="primary" icon="check" :disabled="abs($faltaPagamento) >= 0.01">Confirmar pagamento</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal: confirmar cancelamento --}}
    <flux:modal name="cancelar-comanda" class="max-w-sm">
        <div class="flex flex-col gap-4">
            <div class="flex items-center gap-3">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-red-500/15">
                    <flux:icon name="exclamation-triangle" class="size-6" />
                </span>
                <div>
                    <flux:heading size="lg">Cancelar comanda?</flux:heading>
                    <flux:text class="mt-1">@if ($venda->status === 'paga') O estoque baixado será estornado. @endif Esta ação não pode ser desfeita.</flux:text>
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Voltar</flux:button></flux:modal.close>
                <flux:button wire:click="cancelar" variant="danger" icon="x-mark">Cancelar comanda</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
