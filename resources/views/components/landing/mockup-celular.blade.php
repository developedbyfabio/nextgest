{{--
    Mockup de smartphone (HTML/CSS, sem imagem, estático) ESPELHANDO a tela REAL do
    portal de agendamento — passo de Data e horário (App\Livewire\Portal\Agendar,
    livewire/portal/agendar.blade.php, passo 4). Mesmas seções/rótulos do app real:
    cabeçalho do salão → "Novo agendamento" + "Passo 3 de 3 · Data e horário" →
    barra de progresso (3 cheios) → "Quando?" → campo "Dia" → "Horários
    disponíveis" (grade, um selecionado) → card do slot → Voltar/Confirmar.

    A landing é o site da MARCA (sem tema de tenant): o accent "principal" do portal
    é representado aqui pelo degradê de marca (violeta→azul). NÃO emite --cor-* (o
    teste de tema exige que a landing não tenha --cor-principal).
--}}
<div {{ $attributes->class('relative mx-auto w-[16.5rem] select-none sm:w-[17.5rem]') }} aria-hidden="true">
    {{-- Brilho de marca atrás do aparelho --}}
    <div class="absolute -inset-6 -z-10 rounded-[3rem] bg-gradient-to-br from-violet-600/30 via-indigo-600/20 to-blue-600/20 blur-2xl"></div>

    {{-- Moldura --}}
    <div class="rounded-[2.5rem] border-[10px] border-slate-900 bg-white shadow-2xl shadow-indigo-900/20 dark:border-slate-700 dark:bg-slate-900">
        <div class="relative overflow-hidden rounded-[1.7rem]">
            {{-- Notch --}}
            <div class="absolute left-1/2 top-0 z-10 h-5 w-28 -translate-x-1/2 rounded-b-2xl bg-slate-900 dark:bg-slate-700"></div>

            {{-- Cabeçalho do salão (x-portal.cabecalho real: barra de superfície,
                 logo/ícone na cor da marca + nome + ação à direita). --}}
            <div class="flex items-center justify-between gap-2 border-b border-slate-200 bg-white/95 px-4 pb-2.5 pt-7 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex min-w-0 items-center gap-2">
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-violet-600 to-blue-600 text-white">
                        <flux:icon name="scissors" class="size-4" />
                    </span>
                    <span class="truncate text-sm font-semibold text-slate-900 dark:text-white">Barbearia do Zé</span>
                </div>
                <flux:icon name="user-circle" class="size-6 shrink-0 text-slate-300 dark:text-slate-600" />
            </div>

            {{-- Corpo (fundo da página) --}}
            <div class="space-y-3.5 bg-slate-50 px-4 py-3.5 dark:bg-slate-950/40">
                {{-- Cabeçalho do passo --}}
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="text-[0.95rem] font-semibold text-slate-900 dark:text-white">Novo agendamento</div>
                        <div class="mt-0.5 text-[0.7rem] text-slate-500 dark:text-slate-400">Passo 3 de 3 · Data e horário</div>
                    </div>
                    <flux:icon name="x-mark" class="size-4 shrink-0 text-slate-400" />
                </div>

                {{-- Barra de progresso (3 passos — no último, todos cheios) --}}
                <div class="flex items-center gap-1.5">
                    <div class="h-1.5 flex-1 rounded-full bg-gradient-to-r from-violet-600 to-indigo-600"></div>
                    <div class="h-1.5 flex-1 rounded-full bg-gradient-to-r from-indigo-600 to-indigo-600"></div>
                    <div class="h-1.5 flex-1 rounded-full bg-gradient-to-r from-indigo-600 to-blue-600"></div>
                </div>

                <div class="text-sm font-semibold text-slate-700 dark:text-slate-200">Quando?</div>

                {{-- Campo "Dia" --}}
                <div>
                    <div class="mb-1 text-[0.7rem] font-medium text-slate-500 dark:text-slate-400">Dia</div>
                    <div class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                        <flux:icon name="calendar-days" class="size-4 text-slate-400" />
                        12/07/2026
                    </div>
                </div>

                {{-- Horários disponíveis --}}
                <div>
                    <div class="mb-1.5 text-[0.7rem] font-medium text-slate-500 dark:text-slate-400">Horários disponíveis</div>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach (['09:00', '09:30', '10:00', '10:30', '11:00', '11:30'] as $hora)
                            @if ($hora === '10:30')
                                <span class="rounded-lg border border-transparent bg-gradient-to-r from-violet-600 to-blue-600 py-1.5 text-center text-xs font-semibold text-white shadow-md shadow-indigo-500/30">{{ $hora }}</span>
                            @else
                                <span class="rounded-lg border border-slate-200 bg-white py-1.5 text-center text-xs font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">{{ $hora }}</span>
                            @endif
                        @endforeach
                    </div>
                </div>

                {{-- Card do horário selecionado (aparece quando há slot escolhido) --}}
                <div class="flex items-center gap-2.5 rounded-xl border border-indigo-300/70 bg-indigo-50/70 p-2.5 dark:border-indigo-500/30 dark:bg-indigo-500/10">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-violet-600 to-blue-600 text-white">
                        <flux:icon name="check" class="size-4" />
                    </span>
                    <div class="min-w-0 leading-tight">
                        <div class="text-xs font-semibold text-slate-900 dark:text-white">Sex, 12/07 às 10:30</div>
                        <div class="text-[0.7rem] text-slate-500 dark:text-slate-400">50 min · R$ 70,00</div>
                    </div>
                </div>

                {{-- Ações --}}
                <div class="flex gap-2 pt-0.5">
                    <span class="flex-1 rounded-lg border border-slate-200 py-2 text-center text-xs font-semibold text-slate-600 dark:border-slate-700 dark:text-slate-300">Voltar</span>
                    <span class="flex flex-1 items-center justify-center gap-1 rounded-lg bg-gradient-to-r from-violet-600 via-indigo-600 to-blue-600 py-2 text-center text-xs font-semibold text-white shadow-md shadow-indigo-500/30">
                        <flux:icon name="check" class="size-3.5" /> Confirmar
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>
