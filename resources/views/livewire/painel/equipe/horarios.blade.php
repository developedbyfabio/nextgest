<div class="flex flex-col gap-6 p-6 lg:p-8">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Horários de trabalho</flux:heading>
            <flux:subheading>{{ $profissional->name }}</flux:subheading>
        </div>
        <flux:button :href="route('painel.equipe', ['tenant' => tenant('id')])" variant="ghost" icon="arrow-left" wire:navigate>Voltar</flux:button>
    </div>

    <form wire:submit="salvar" class="flex flex-col gap-4">
        @foreach ($dias as $diaNum => $diaNome)
            <flux:card class="flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ $diaNome }}</flux:heading>
                    <flux:button type="button" wire:click="adicionarFaixa({{ $diaNum }})" size="sm" variant="subtle" icon="plus">Adicionar faixa</flux:button>
                </div>

                @php($temFaixa = false)
                @foreach ($faixas as $index => $faixa)
                    @if ((int) $faixa['dia_semana'] === $diaNum)
                        @php($temFaixa = true)
                        <div class="flex flex-wrap items-end gap-3" wire:key="faixa-{{ $index }}">
                            @if ($unidades->count() > 1)
                                <flux:select wire:model="faixas.{{ $index }}.unidade_id" label="Unidade" class="min-w-44">
                                    @foreach ($unidades as $unidade)
                                        <flux:select.option value="{{ $unidade->id }}">{{ $unidade->nome }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            @endif

                            <flux:input type="time" wire:model="faixas.{{ $index }}.hora_inicio" label="Início" />
                            <flux:input type="time" wire:model="faixas.{{ $index }}.hora_fim" label="Fim" />

                            <flux:button type="button" wire:click="removerFaixa({{ $index }})" size="sm" variant="subtle" icon="trash" />

                            @error("faixas.$index.hora_fim")
                                <flux:text class="w-full text-sm text-red-600">{{ $message }}</flux:text>
                            @enderror
                        </div>
                    @endif
                @endforeach

                @unless ($temFaixa)
                    <flux:text class="text-sm text-zinc-500">Folga (sem faixas).</flux:text>
                @endunless
            </flux:card>
        @endforeach

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">Salvar horários</flux:button>
        </div>
    </form>
</div>
