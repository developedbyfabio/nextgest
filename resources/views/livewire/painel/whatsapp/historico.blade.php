<div class="flex flex-col gap-6 p-6 lg:p-8">
    <x-ng.page-header title="WhatsApp" subtitle="Histórico de envios" />

    @include('livewire.painel.whatsapp._abas', ['ativa' => 'historico'])

    <div class="ng-surface p-4 text-sm" style="color: var(--cor-texto-suave);">
        Registro dos envios (metadados + conteúdo). O conteúdo é apagado automaticamente após
        o prazo, mantendo os metadados. Mostra o que foi <strong>enviado</strong> — nunca o que
        o cliente respondeu ou avaliou.
    </div>

    {{-- Filtros (servidor) --}}
    <div class="flex flex-wrap items-end gap-3">
        <flux:select wire:model.live="filtroAutomacao" label="Automação" size="sm" class="w-52">
            <flux:select.option value="">Todas</flux:select.option>
            @foreach ($automacoes as $valor => $rotulo)
                <flux:select.option value="{{ $valor }}">{{ $rotulo }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filtroStatus" label="Status" size="sm" class="w-40">
            <flux:select.option value="">Todos</flux:select.option>
            <flux:select.option value="enviado">Enviado</flux:select.option>
            <flux:select.option value="falhou">Falhou</flux:select.option>
            <flux:select.option value="descartado">Descartado</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="filtroPeriodo" label="Período" size="sm" class="w-36">
            <flux:select.option value="">Qualquer data</flux:select.option>
            <flux:select.option value="dia">Hoje</flux:select.option>
            <flux:select.option value="semana">Esta semana</flux:select.option>
            <flux:select.option value="mes">Este mês</flux:select.option>
        </flux:select>

        <flux:icon name="arrow-path" wire:loading.delay wire:target="filtroAutomacao,filtroStatus,filtroPeriodo" class="mb-2 size-5 animate-spin" style="color: var(--cor-principal);" />
    </div>

    @if ($mensagens->isEmpty())
        <x-ng.empty themed icon="chat-bubble-left-right" title="Nenhuma mensagem registrada"
            text="Quando uma automação enviar (ou tentar enviar) uma mensagem, ela aparece aqui." />
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Quando</flux:table.column>
                <flux:table.column>Automação</flux:table.column>
                <flux:table.column>Para</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Conteúdo</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($mensagens as $m)
                    <flux:table.row :key="$m->id">
                        <flux:table.cell class="whitespace-nowrap">{{ $m->created_at->format('d/m/Y H:i') }}</flux:table.cell>
                        <flux:table.cell>{{ $automacoes[$m->automacao] ?? $m->automacao }}</flux:table.cell>
                        <flux:table.cell variant="strong">
                            {{ $m->cliente?->nome ?? $m->telefone ?? '—' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @php($cor = ['enviado' => 'green', 'falhou' => 'red', 'descartado' => 'amber'][$m->status] ?? 'zinc')
                            <flux:badge :color="$cor" size="sm">{{ ucfirst($m->status) }}</flux:badge>
                            @if ($m->motivo)
                                <span class="block text-xs" style="color: var(--cor-texto-suave);">{{ $m->motivo }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($m->conteudo)
                                <span class="block max-w-xs truncate text-xs" style="color: var(--cor-texto-suave);" title="{{ $m->conteudo }}">{{ $m->conteudo }}</span>
                            @elseif ($m->conteudo_expurgado_em)
                                <span class="text-xs italic" style="color: var(--cor-texto-suave);">conteúdo expurgado</span>
                            @else
                                <span class="text-xs" style="color: var(--cor-texto-suave);">—</span>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        <div>{{ $mensagens->links() }}</div>
    @endif
</div>
