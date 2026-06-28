<div class="flex flex-col gap-6 p-6 lg:p-8">
    <x-ng.page-header title="WhatsApp" subtitle="Consentimentos (não enviar)" />

    @include('livewire.painel.whatsapp._abas', ['ativa' => 'optout'])

    <div class="ng-surface flex flex-col gap-2 p-4 text-sm" style="color: var(--cor-texto-suave);">
        <p>Dois controles <strong>independentes</strong> por cliente:</p>
        <p><strong style="color: var(--cor-texto);">Tudo (transacional):</strong> bloqueia <strong>todas</strong>
            as mensagens — inclusive lembrete e avaliação. Use quando o cliente não quer contato nenhum.</p>
        <p><strong style="color: var(--cor-texto);">Marketing:</strong> bloqueia só os envios em massa
            (notícias/avisos). Os <strong>lembretes e avaliações continuam</strong>.</p>
    </div>

    {{-- Buscar um cliente para ajustar os consentimentos --}}
    <div class="ng-surface flex flex-col gap-3 p-5">
        <flux:heading size="sm" style="color: var(--cor-texto);">Buscar cliente</flux:heading>
        <flux:input wire:model.live.debounce.300ms="busca" icon="magnifying-glass"
            placeholder="Buscar por nome ou telefone (mín. 2 letras)" class="max-w-md" />

        @if (trim($busca) !== '' && $resultados->isEmpty())
            <flux:text class="text-sm" style="color: var(--cor-texto-suave);">Nenhum cliente encontrado.</flux:text>
        @endif

        @if ($resultados->isNotEmpty())
            <div class="flex flex-col divide-y" style="border-color: var(--cor-borda);">
                @foreach ($resultados as $c)
                    <div class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <span class="block truncate font-medium" style="color: var(--cor-texto);">{{ $c->nome }}</span>
                            <span class="block text-xs" style="color: var(--cor-texto-suave);">{{ $c->telefone ?: 'sem telefone' }}</span>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-medium" style="color: var(--cor-texto-suave);">Tudo</span>
                                @include('livewire.painel.whatsapp._optout-controle', ['c' => $c, 'tipo' => 'geral'])
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-medium" style="color: var(--cor-texto-suave);">Marketing</span>
                                @include('livewire.painel.whatsapp._optout-controle', ['c' => $c, 'tipo' => 'marketing'])
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Clientes com alguma restrição --}}
    @if ($restritos->isEmpty())
        <x-ng.empty themed icon="check-circle" title="Ninguém com restrição"
            text="Todos os clientes com telefone podem receber as mensagens (transacional e marketing)." />
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Cliente</flux:table.column>
                <flux:table.column>Telefone</flux:table.column>
                <flux:table.column>Tudo (transacional)</flux:table.column>
                <flux:table.column>Marketing</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($restritos as $c)
                    <flux:table.row :key="$c->id">
                        <flux:table.cell variant="strong">{{ $c->nome }}</flux:table.cell>
                        <flux:table.cell>{{ $c->telefone ?: '—' }}</flux:table.cell>
                        <flux:table.cell>@include('livewire.painel.whatsapp._optout-controle', ['c' => $c, 'tipo' => 'geral'])</flux:table.cell>
                        <flux:table.cell>@include('livewire.painel.whatsapp._optout-controle', ['c' => $c, 'tipo' => 'marketing'])</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        <div>{{ $restritos->links() }}</div>
    @endif

    {{-- Confirmação de risco (D65): liberar = volta a receber (consentimento). --}}
    <x-ng.confirmar name="optout-liberar" tom="amber" icone="arrow-uturn-left"
        titulo="Voltar a enviar para este cliente?"
        :texto="($confirmarNome ?: 'O cliente').($confirmarTipo === 'geral'
            ? ' voltará a receber TODAS as mensagens (lembrete, avaliação e marketing).'
            : ' voltará a receber as mensagens de marketing (broadcast).')">
        @if ($confirmarId)
            <flux:button wire:click="liberar({{ $confirmarId }}, '{{ $confirmarTipo }}')" variant="primary" icon="arrow-uturn-left">Liberar</flux:button>
        @endif
    </x-ng.confirmar>
</div>
