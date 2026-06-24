{{--
    Mockup de janela de desktop (HTML/CSS, sem imagem, estático) ESPELHANDO a
    AGENDA SEMANAL real do painel (Painel\Agenda\Index, visão "semana"): controles
    (‹ Hoje › · data · [Dia][Semana]) + grade de 7 colunas (um dia cada) com
    cabeçalho (dia + data, hoje destacado) e cartões de agendamento (barra de status
    à esquerda + horário + cliente). Cores de status iguais às reais (STATUS_HEX).
    Accent = degradê de marca (a landing não emite --cor-* de tenant). No mobile,
    rola horizontalmente (como a agenda real), versão representativa.
--}}
@php
    $st = [
        'confirmado' => '#22c55e',
        'pendente' => '#f59e0b',
        'em_andamento' => '#3b82f6',
        'concluido' => '#14b8a6',
    ];
    // Semana de exemplo (qua = "hoje"). Cada item: [hora, cliente, status].
    $semana = [
        ['dia' => 'seg', 'data' => '30/06', 'hoje' => false, 'ags' => [['09:00', 'João', 'confirmado'], ['10:30', 'Pedro', 'confirmado'], ['14:00', 'Lucas', 'pendente']]],
        ['dia' => 'ter', 'data' => '01/07', 'hoje' => false, 'ags' => [['09:30', 'Marcos', 'confirmado'], ['11:00', 'André', 'em_andamento']]],
        ['dia' => 'qua', 'data' => '02/07', 'hoje' => true, 'ags' => [['09:00', 'Rafael', 'confirmado'], ['10:00', 'Bruno', 'confirmado'], ['13:30', 'Tiago', 'pendente'], ['16:00', 'Diego', 'confirmado']]],
        ['dia' => 'qui', 'data' => '03/07', 'hoje' => false, 'ags' => [['10:00', 'Felipe', 'confirmado'], ['15:00', 'Caio', 'confirmado']]],
        ['dia' => 'sex', 'data' => '04/07', 'hoje' => false, 'ags' => [['09:00', 'Gustavo', 'confirmado'], ['11:30', 'Igor', 'confirmado'], ['14:30', 'Léo', 'concluido']]],
        ['dia' => 'sáb', 'data' => '05/07', 'hoje' => false, 'ags' => [['09:00', 'Murilo', 'confirmado'], ['10:30', 'Enzo', 'confirmado']]],
        ['dia' => 'dom', 'data' => '06/07', 'hoje' => false, 'ags' => []],
    ];
@endphp

<div {{ $attributes->class('relative') }} aria-hidden="true">
    {{-- Brilho de marca atrás da janela --}}
    <div class="absolute -inset-4 -z-10 rounded-[2rem] bg-gradient-to-br from-violet-600/20 via-indigo-600/10 to-blue-600/10 blur-2xl"></div>

    {{-- Janela --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-indigo-900/10 dark:border-slate-700 dark:bg-slate-900">
        {{-- Barra de título --}}
        <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-slate-800 dark:bg-slate-950/50">
            <span class="flex gap-1.5">
                <span class="size-2.5 rounded-full bg-red-400"></span>
                <span class="size-2.5 rounded-full bg-amber-400"></span>
                <span class="size-2.5 rounded-full bg-emerald-400"></span>
            </span>
            <span class="ml-2 flex items-center gap-1.5 text-xs font-medium text-slate-500 dark:text-slate-400">
                <img src="{{ asset('nextgest-logo.png') }}" alt="" class="size-4 object-contain" /> Painel · Agenda
            </span>
        </div>

        {{-- Corpo --}}
        <div class="bg-white p-4 dark:bg-slate-900">
            {{-- Controles --}}
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <div class="flex items-center overflow-hidden rounded-lg border border-slate-200 text-xs font-medium text-slate-600 dark:border-slate-700 dark:text-slate-300">
                    <span class="flex size-7 items-center justify-center border-r border-slate-200 dark:border-slate-700"><flux:icon name="chevron-left" class="size-4" /></span>
                    <span class="px-2.5 py-1">Hoje</span>
                    <span class="flex size-7 items-center justify-center border-l border-slate-200 dark:border-slate-700"><flux:icon name="chevron-right" class="size-4" /></span>
                </div>
                <span class="hidden items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1 text-xs text-slate-600 dark:border-slate-700 dark:text-slate-300 sm:inline-flex">
                    <flux:icon name="calendar-days" class="size-4 text-slate-400" /> 30/06 – 06/07
                </span>
                <span class="flex-1"></span>
                <div class="flex items-center overflow-hidden rounded-lg border border-slate-200 text-xs font-semibold dark:border-slate-700">
                    <span class="px-3 py-1 text-slate-500 dark:text-slate-400">Dia</span>
                    <span class="bg-gradient-to-r from-violet-600 to-blue-600 px-3 py-1 text-white">Semana</span>
                </div>
            </div>

            {{-- Grade semanal: 7 colunas no desktop, scroll horizontal no mobile (como o real) --}}
            <div class="flex gap-2 overflow-x-auto pb-1 md:grid md:grid-cols-7 md:overflow-visible">
                @foreach ($semana as $col)
                    <div class="flex w-28 shrink-0 flex-col gap-1.5 rounded-xl bg-slate-50 p-1.5 dark:bg-slate-800/40 md:w-auto">
                        {{-- Cabeçalho do dia (hoje destacado em degradê de marca) --}}
                        <div @class([
                            'rounded-lg px-1 py-1 text-center',
                            'bg-gradient-to-r from-violet-600/15 to-blue-600/15' => $col['hoje'],
                        ])>
                            <span @class([
                                'text-xs font-semibold capitalize',
                                'text-indigo-600 dark:text-indigo-300' => $col['hoje'],
                                'text-slate-700 dark:text-slate-200' => ! $col['hoje'],
                            ])>{{ $col['dia'] }}</span>
                            <span class="block text-[0.65rem] text-slate-400">{{ $col['data'] }}</span>
                        </div>

                        @forelse ($col['ags'] as [$hora, $cliente, $status])
                            <div class="overflow-hidden rounded-md border-s-4 bg-white px-1.5 py-1 shadow-sm dark:bg-slate-900" style="border-color: {{ $st[$status] }};">
                                <span class="block text-[0.65rem] font-semibold tabular-nums text-slate-800 dark:text-slate-100">{{ $hora }}</span>
                                <span class="block truncate text-[0.65rem] text-slate-500 dark:text-slate-400">{{ $cliente }}</span>
                            </div>
                        @empty
                            <div class="py-2 text-center text-[0.65rem] text-slate-300 dark:text-slate-600">—</div>
                        @endforelse
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
