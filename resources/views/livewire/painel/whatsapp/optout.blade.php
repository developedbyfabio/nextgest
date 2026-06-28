<div class="flex flex-col gap-6 p-6 lg:p-8">
    <x-ng.page-header title="WhatsApp" subtitle="Opt-out (não enviar)" />

    @include('livewire.painel.whatsapp._abas', ['ativa' => 'optout'])

    <div class="ng-surface p-4 text-sm" style="color: var(--cor-texto-suave);">
        Clientes marcados aqui <strong>não recebem</strong> nenhuma mensagem automática (lembrete,
        avaliação etc.). Respeite o pedido de quem não quer ser contatado.
    </div>

    {{-- Adicionar ao opt-out --}}
    <div class="ng-surface flex flex-col gap-3 p-5">
        <flux:heading size="sm" style="color: var(--cor-texto);">Adicionar cliente ao opt-out</flux:heading>
        <flux:input wire:model.live.debounce.300ms="busca" icon="magnifying-glass"
            placeholder="Buscar por nome ou telefone (mín. 2 letras)" class="max-w-md" />

        @if (trim($busca) !== '' && $resultados->isEmpty())
            <flux:text class="text-sm" style="color: var(--cor-texto-suave);">Nenhum cliente encontrado (ou já está no opt-out).</flux:text>
        @endif

        @if ($resultados->isNotEmpty())
            <div class="flex flex-col divide-y" style="border-color: var(--cor-borda);">
                @foreach ($resultados as $c)
                    <div class="flex items-center justify-between gap-3 py-2">
                        <div class="min-w-0">
                            <span class="block truncate font-medium" style="color: var(--cor-texto);">{{ $c->nome }}</span>
                            <span class="block text-xs" style="color: var(--cor-texto-suave);">{{ $c->telefone ?: 'sem telefone' }}</span>
                        </div>
                        <flux:button wire:click="marcar({{ $c->id }})" size="sm" variant="subtle" icon="no-symbol">Não enviar</flux:button>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Lista de opt-out --}}
    @if ($optouts->isEmpty())
        <x-ng.empty themed icon="check-circle" title="Ninguém no opt-out"
            text="Todos os clientes com telefone podem receber as mensagens automáticas." />
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Cliente</flux:table.column>
                <flux:table.column>Telefone</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($optouts as $c)
                    <flux:table.row :key="$c->id">
                        <flux:table.cell variant="strong">{{ $c->nome }}</flux:table.cell>
                        <flux:table.cell>{{ $c->telefone ?: '—' }}</flux:table.cell>
                        <flux:table.cell class="text-right">
                            <flux:button wire:click="confirmarRemocao({{ $c->id }})" size="sm" variant="ghost" icon="arrow-uturn-left">Voltar a enviar</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        <div>{{ $optouts->links() }}</div>
    @endif

    {{-- Confirmação de risco (D65): tirar do opt-out = volta a receber (consentimento). --}}
    <x-ng.confirmar name="optout-voltar" tom="amber" icone="arrow-uturn-left"
        titulo="Voltar a enviar para este cliente?"
        :texto="($confirmarNome ? $confirmarNome.' ' : 'O cliente ').'voltará a receber as mensagens automáticas (lembrete, avaliação etc.).'">
        @if ($confirmarId)
            <flux:button wire:click="desmarcar({{ $confirmarId }})" variant="primary" icon="arrow-uturn-left">Voltar a enviar</flux:button>
        @endif
    </x-ng.confirmar>
</div>
