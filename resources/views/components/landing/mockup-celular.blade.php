{{--
    Mockup de smartphone (HTML/CSS, sem imagem) mostrando o "momento mágico": o
    cliente escolhendo serviço e horário no portal. Puramente visual/estático.
    Marca = degradê violeta→índigo→azul. Responsivo e nítido.
--}}
<div {{ $attributes->class('relative mx-auto w-[16.5rem] select-none sm:w-[17.5rem]') }} aria-hidden="true">
    {{-- Brilho de marca atrás do aparelho --}}
    <div class="absolute -inset-6 -z-10 rounded-[3rem] bg-gradient-to-br from-violet-600/30 via-indigo-600/20 to-blue-600/20 blur-2xl"></div>

    {{-- Moldura --}}
    <div class="rounded-[2.5rem] border-[10px] border-slate-900 bg-white shadow-2xl shadow-indigo-900/20 dark:border-slate-700 dark:bg-slate-900">
        <div class="relative overflow-hidden rounded-[1.7rem]">
            {{-- Notch --}}
            <div class="absolute left-1/2 top-0 z-10 h-5 w-28 -translate-x-1/2 rounded-b-2xl bg-slate-900 dark:bg-slate-700"></div>

            {{-- Cabeçalho do salão (degradê de marca) --}}
            <div class="bg-gradient-to-br from-violet-600 via-indigo-600 to-blue-600 px-4 pb-5 pt-7 text-white">
                <div class="flex items-center gap-2.5">
                    <span class="flex size-9 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/25 backdrop-blur">
                        <flux:icon name="scissors" class="size-5" />
                    </span>
                    <div class="leading-tight">
                        <div class="text-sm font-semibold">Barbearia do Zé</div>
                        <div class="flex items-center gap-1 text-[0.7rem] text-white/80">
                            <flux:icon name="map-pin" class="size-3" /> Unidade Centro
                        </div>
                    </div>
                </div>
            </div>

            {{-- Conteúdo --}}
            <div class="space-y-4 bg-white px-4 py-4 dark:bg-slate-900">
                {{-- Serviço --}}
                <div>
                    <div class="mb-2 text-[0.7rem] font-semibold uppercase tracking-wide text-slate-400">Serviço</div>
                    <div class="flex items-center justify-between rounded-xl border-2 border-indigo-500 bg-indigo-50/60 px-3 py-2.5 dark:bg-indigo-500/10">
                        <div>
                            <div class="text-sm font-semibold text-slate-900 dark:text-white">Corte + Barba</div>
                            <div class="text-[0.7rem] text-slate-500 dark:text-slate-400">50 min</div>
                        </div>
                        <span class="text-sm font-semibold text-indigo-600 dark:text-indigo-300">R$ 70</span>
                    </div>
                </div>

                {{-- Horários --}}
                <div>
                    <div class="mb-2 text-[0.7rem] font-semibold uppercase tracking-wide text-slate-400">Horários — hoje</div>
                    <div class="grid grid-cols-3 gap-2">
                        @php($slots = ['09:00', '09:30', '10:00', '10:30', '11:00', '11:30'])
                        @foreach ($slots as $hora)
                            @if ($hora === '10:30')
                                <span class="rounded-lg bg-gradient-to-r from-violet-600 to-blue-600 py-1.5 text-center text-xs font-semibold text-white shadow-md shadow-indigo-500/30">{{ $hora }}</span>
                            @else
                                <span class="rounded-lg border border-slate-200 py-1.5 text-center text-xs font-medium text-slate-600 dark:border-slate-700 dark:text-slate-300">{{ $hora }}</span>
                            @endif
                        @endforeach
                    </div>
                </div>

                {{-- Confirmar --}}
                <button type="button" tabindex="-1"
                    class="flex w-full items-center justify-center gap-1.5 rounded-xl bg-gradient-to-r from-violet-600 via-indigo-600 to-blue-600 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-500/25">
                    Confirmar agendamento
                    <flux:icon name="check" class="size-4" />
                </button>
            </div>
        </div>
    </div>
</div>
