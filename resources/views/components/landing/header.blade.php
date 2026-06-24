{{--
    Header da landing (Fase 1): sticky com glassmorphism, navegação por âncoras
    (scroll suave), acesso administrativo, CTA de marca, toggle de tema e menu
    mobile (Alpine). Reutilizável pelas Fases 2/3. Sem Livewire.
--}}
@php($navItens = [
    '#recursos' => 'Recursos',
    '#como-funciona' => 'Como funciona',
    '#planos' => 'Planos',
    '#faq' => 'FAQ',
    '#contato' => 'Contato',
])

<header
    x-data="{ aberto: false }"
    class="sticky top-0 z-40 border-b border-slate-200/70 bg-white/80 backdrop-blur-md dark:border-slate-800/70 dark:bg-[#0B1120]/80"
>
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
        <a href="#topo" class="flex items-center gap-2.5" aria-label="Nextgest — início">
            <img src="{{ asset('nextgest-logo.png') }}" alt="Nextgest" class="size-9 shrink-0 object-contain" />
            <span class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">Nextgest</span>
        </a>

        {{-- Navegação (desktop) --}}
        <nav class="hidden items-center gap-7 lg:flex" aria-label="Navegação principal">
            @foreach ($navItens as $href => $rotulo)
                <a href="{{ $href }}" class="text-sm font-medium text-slate-600 transition-colors hover:text-slate-900 dark:text-slate-300 dark:hover:text-white">{{ $rotulo }}</a>
            @endforeach
        </nav>

        <div class="flex items-center gap-1.5 sm:gap-2.5">
            <x-landing.tema-toggle />

            <a href="{{ route('admin.login') }}"
                class="hidden rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white sm:inline-flex">
                Acesso administrativo
            </a>

            <a href="#contato"
                class="hidden rounded-lg bg-gradient-to-r from-violet-600 via-indigo-600 to-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-indigo-500/25 transition duration-200 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-indigo-500/30 active:translate-y-0 active:scale-[0.98] sm:inline-flex">
                Começar agora
            </a>

            {{-- Hambúrguer (mobile) --}}
            <button type="button" @click="aberto = ! aberto"
                class="inline-flex size-10 items-center justify-center rounded-lg text-slate-700 transition hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800 lg:hidden"
                :aria-expanded="aberto" aria-controls="menu-mobile" aria-label="Abrir menu">
                <flux:icon name="bars-3" x-show="! aberto" class="size-6" />
                <flux:icon name="x-mark" x-show="aberto" x-cloak class="size-6" />
            </button>
        </div>
    </div>

    {{-- Painel mobile --}}
    <div id="menu-mobile" x-show="aberto" x-cloak x-transition.opacity.duration.150ms
        class="border-t border-slate-200 bg-white px-4 py-4 dark:border-slate-800 dark:bg-[#0B1120] lg:hidden">
        <nav class="flex flex-col gap-1" aria-label="Navegação móvel">
            @foreach ($navItens as $href => $rotulo)
                <a href="{{ $href }}" @click="aberto = false"
                    class="rounded-lg px-3 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">{{ $rotulo }}</a>
            @endforeach
        </nav>
        <div class="mt-3 flex flex-col gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
            <a href="{{ route('admin.login') }}"
                class="rounded-lg px-3 py-2.5 text-center text-sm font-medium text-slate-700 transition hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">
                Acesso administrativo
            </a>
            <a href="#contato" @click="aberto = false"
                class="rounded-lg bg-gradient-to-r from-violet-600 via-indigo-600 to-blue-600 px-4 py-2.5 text-center text-sm font-semibold text-white shadow-lg shadow-indigo-500/25">
                Começar agora
            </a>
        </div>
    </div>
</header>
