<div>
    @if ($resumo['mostraCasa'] || $resumo['mostraPessoal'])
        <div class="ng-surface flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:gap-8">
            {{-- Bloco da CASA (gestão: total de hoje + a confirmar) --}}
            @if ($resumo['mostraCasa'])
                <div class="flex items-center gap-3">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-full"
                          style="background-color: color-mix(in srgb, var(--cor-principal) 14%, transparent); color: var(--cor-principal);">
                        <flux:icon name="calendar-days" class="size-6" />
                    </span>
                    <div>
                        @if ($resumo['casaTotal'] > 0)
                            <flux:heading size="lg" style="color: var(--cor-texto);">
                                {{ $resumo['casaTotal'] }} {{ $resumo['casaTotal'] === 1 ? 'agendamento' : 'agendamentos' }} hoje
                            </flux:heading>
                            <flux:text class="text-sm" style="color: var(--cor-texto-suave);">
                                @if ($resumo['casaPendentes'] > 0)
                                    {{ $resumo['casaPendentes'] }} a confirmar
                                @else
                                    Tudo confirmado
                                @endif
                            </flux:text>
                        @else
                            <flux:heading size="lg" style="color: var(--cor-texto);">Nenhum agendamento para hoje</flux:heading>
                            <flux:text class="text-sm" style="color: var(--cor-texto-suave);">Dia livre na agenda.</flux:text>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Separador quando os dois blocos aparecem (Dono que também atende) --}}
            @if ($resumo['mostraCasa'] && $resumo['mostraPessoal'])
                <div class="hidden h-10 w-px sm:block" style="background-color: color-mix(in srgb, var(--cor-texto) 12%, transparent);"></div>
            @endif

            {{-- Bloco PESSOAL (profissional: seus N de hoje + próximo) --}}
            @if ($resumo['mostraPessoal'])
                <div class="flex items-center gap-3">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-full"
                          style="background-color: color-mix(in srgb, var(--cor-secundaria) 16%, transparent); color: var(--cor-secundaria);">
                        <flux:icon name="user-circle" class="size-6" />
                    </span>
                    <div>
                        @if ($resumo['meuTotal'] > 0)
                            <flux:heading size="lg" style="color: var(--cor-texto);">
                                Você tem {{ $resumo['meuTotal'] }} {{ $resumo['meuTotal'] === 1 ? 'agendamento' : 'agendamentos' }} hoje
                            </flux:heading>
                            @if ($resumo['proximo'])
                                <flux:text class="text-sm" style="color: var(--cor-texto-suave);">
                                    Próximo às {{ $resumo['proximo']->data_hora_inicio->format('H:i') }}@if ($resumo['proximo']->cliente), {{ $resumo['proximo']->cliente->nome }}@endif
                                </flux:text>
                            @else
                                <flux:text class="text-sm" style="color: var(--cor-texto-suave);">Nenhum horário restante hoje.</flux:text>
                            @endif
                        @else
                            <flux:heading size="lg" style="color: var(--cor-texto);">Nenhum agendamento seu hoje</flux:heading>
                            <flux:text class="text-sm" style="color: var(--cor-texto-suave);">Aproveite o dia livre.</flux:text>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
