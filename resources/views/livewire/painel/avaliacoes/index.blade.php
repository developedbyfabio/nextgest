<div class="flex flex-col gap-6 p-6 lg:p-8">
    <x-ng.page-header title="Últimos serviços"
        :subtitle="$podeVerTudo ? 'Atendimentos concluídos e suas avaliações' : 'Seus atendimentos concluídos e avaliações'" />

    {{-- Resumo (termômetro) do escopo/filtros atuais --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="ng-surface flex flex-col gap-1 p-4">
            <span class="text-xs font-medium" style="color: var(--cor-texto-suave);">Média</span>
            <span class="flex items-center gap-2">
                <span class="text-2xl font-bold tabular-nums" style="color: var(--cor-texto);">{{ $resumo['avaliados'] > 0 ? number_format($resumo['media'], 1, ',', '.') : '—' }}</span>
                @if ($resumo['avaliados'] > 0)
                    <x-portal.estrelas :nota="(int) round($resumo['media'])" />
                @endif
            </span>
        </div>
        <div class="ng-surface flex flex-col gap-1 p-4">
            <span class="text-xs font-medium" style="color: var(--cor-texto-suave);">Avaliações</span>
            <span class="text-2xl font-bold tabular-nums" style="color: var(--cor-texto);">{{ $resumo['avaliados'] }}</span>
        </div>
        <div class="ng-surface flex flex-col gap-1 p-4">
            <span class="text-xs font-medium" style="color: var(--cor-texto-suave);">Atendimentos concluídos</span>
            <span class="text-2xl font-bold tabular-nums" style="color: var(--cor-texto);">{{ $resumo['concluidos'] }}</span>
        </div>
        <div class="ng-surface flex flex-col gap-1 p-4">
            <span class="text-xs font-medium" style="color: var(--cor-texto-suave);">Taxa de avaliação</span>
            <span class="text-2xl font-bold tabular-nums" style="color: var(--cor-texto);">{{ $resumo['taxa'] }}%</span>
        </div>
    </div>

    {{-- (filtros entram aqui — T3) --}}

    @if ($atendimentos->isEmpty())
        <x-ng.empty themed icon="star" title="Nenhum atendimento concluído"
            text="Quando um atendimento for concluído, ele aparece aqui — com a avaliação do cliente, se houver." />
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Data</flux:table.column>
                <flux:table.column>Serviço</flux:table.column>
                <flux:table.column>Profissional</flux:table.column>
                @if ($podeVerTudo)
                    <flux:table.column>Cliente</flux:table.column>
                @endif
                <flux:table.column>Avaliação</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($atendimentos as $a)
                    <flux:table.row :key="$a->id">
                        <flux:table.cell class="whitespace-nowrap">{{ $a->data_hora_inicio->format('d/m/Y H:i') }}</flux:table.cell>
                        <flux:table.cell>{{ $a->itens->map(fn ($i) => $i->servico?->nome)->filter()->join(', ') ?: '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $a->profissional?->name ?? '—' }}</flux:table.cell>
                        @if ($podeVerTudo)
                            <flux:table.cell variant="strong">{{ $a->cliente?->nome ?? '—' }}</flux:table.cell>
                        @endif
                        <flux:table.cell>
                            @if ($a->avaliacao)
                                <div class="flex flex-col gap-0.5">
                                    <x-portal.estrelas :nota="$a->avaliacao->nota" />
                                    @if ($a->avaliacao->comentario)
                                        <span class="max-w-xs truncate text-xs italic text-zinc-500 dark:text-zinc-400" title="{{ $a->avaliacao->comentario }}">“{{ $a->avaliacao->comentario }}”</span>
                                    @endif
                                </div>
                            @else
                                <flux:badge color="zinc" size="sm">Sem avaliação</flux:badge>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        <div>{{ $atendimentos->links() }}</div>
    @endif
</div>
