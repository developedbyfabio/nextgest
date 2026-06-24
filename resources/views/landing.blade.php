<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Nextgest — Agendamento online para barbearias, salões e profissionais</title>
    <meta name="description" content="Organize agenda, equipe, clientes e vendas em uma plataforma de agendamento online simples, moderna e feita para negócios de serviços.">
    <link rel="icon" type="image/png" href="{{ asset('nextgest-logo.png') }}">

    {{-- Open Graph / social (Fase 1 — pode evoluir nas próximas fases). --}}
    <meta property="og:type" content="website">
    <meta property="og:title" content="Nextgest — Agendamento online para barbearias, salões e profissionais">
    <meta property="og:description" content="Agenda, equipe, clientes e vendas num só lugar. Seus clientes agendam pelo celular.">
    <meta property="og:image" content="{{ asset('nextgest-logo.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body id="topo" class="min-h-screen bg-white text-slate-900 antialiased dark:bg-[#0B1120] dark:text-slate-100">

    <x-landing.header />

    <main>
        {{-- ============================ HERO ============================ --}}
        <section class="relative overflow-hidden">
            {{-- Fundo: degradê suave + motivo geométrico de blocos (assinatura da marca) + brilho. --}}
            <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10">
                <div class="absolute inset-0 bg-gradient-to-b from-indigo-50/80 via-white to-white dark:from-indigo-950/30 dark:via-[#0B1120] dark:to-[#0B1120]"></div>
                {{-- Grade de blocos (derivada do "N" de blocos da logo), esmaecida nas bordas. --}}
                <div class="absolute inset-0 opacity-60 dark:opacity-100 [background-image:linear-gradient(to_right,rgba(99,102,241,0.10)_1px,transparent_1px),linear-gradient(to_bottom,rgba(99,102,241,0.10)_1px,transparent_1px)] [background-size:44px_44px] [mask-image:radial-gradient(ellipse_70%_60%_at_70%_30%,#000,transparent)] dark:[background-image:linear-gradient(to_right,rgba(129,140,248,0.10)_1px,transparent_1px),linear-gradient(to_bottom,rgba(129,140,248,0.10)_1px,transparent_1px)]"></div>
                {{-- Brilho radial de marca atrás do mockup. --}}
                <div class="absolute right-[-6rem] top-[-4rem] size-[34rem] rounded-full bg-gradient-to-br from-violet-600/25 via-indigo-600/15 to-blue-600/10 blur-3xl"></div>
            </div>

            <div class="mx-auto grid max-w-6xl items-center gap-12 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:gap-10 lg:py-24">
                {{-- Coluna texto --}}
                <div class="ng-suben text-center lg:text-left">
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-indigo-200/70 bg-gradient-to-r from-violet-600/10 via-indigo-600/10 to-blue-600/10 px-3 py-1 text-xs font-semibold text-indigo-700 dark:border-indigo-500/30 dark:text-indigo-300">
                        <span class="size-1.5 rounded-full bg-gradient-to-r from-violet-600 to-blue-600"></span>
                        SaaS de agendamento multi-estabelecimento
                    </span>

                    <h1 class="mt-5 text-4xl font-semibold leading-[1.05] tracking-tight text-slate-900 dark:text-white sm:text-5xl lg:text-6xl">
                        Sua agenda no
                        <span class="bg-gradient-to-r from-violet-600 via-indigo-600 to-blue-600 bg-clip-text text-transparent">piloto automático</span>
                    </h1>

                    <p class="mx-auto mt-5 max-w-xl text-lg leading-relaxed text-slate-600 dark:text-slate-300 lg:mx-0">
                        Seus clientes escolhem serviço, profissional e horário pelo celular. Você acompanha
                        agenda, equipe, clientes e vendas num só lugar.
                    </p>

                    <div class="mt-8 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center lg:justify-start">
                        <a href="#contato"
                            class="group inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-violet-600 via-indigo-600 to-blue-600 px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/25 transition duration-200 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-indigo-500/30 active:translate-y-0 active:scale-[0.98]">
                            Quero conhecer
                            <flux:icon name="arrow-right" class="size-4 transition group-hover:translate-x-0.5" />
                        </a>
                        <a href="#recursos"
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-6 py-3.5 text-sm font-semibold text-slate-700 transition duration-200 hover:-translate-y-0.5 hover:border-slate-400 hover:shadow-md dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-600">
                            <flux:icon name="play-circle" class="size-4" />
                            Ver demonstração
                        </a>
                    </div>

                    {{-- Prova social leve / reforço --}}
                    <div class="mt-8 flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-sm text-slate-500 dark:text-slate-400 lg:justify-start">
                        <span class="inline-flex items-center gap-1.5"><flux:icon name="check-circle" class="size-4 text-indigo-600 dark:text-indigo-400" /> Sem choque de horário</span>
                        <span class="inline-flex items-center gap-1.5"><flux:icon name="check-circle" class="size-4 text-indigo-600 dark:text-indigo-400" /> Multiunidade</span>
                        <span class="inline-flex items-center gap-1.5"><flux:icon name="check-circle" class="size-4 text-indigo-600 dark:text-indigo-400" /> Funciona no celular</span>
                    </div>
                </div>

                {{-- Coluna visual: mockup do celular --}}
                <div class="ng-suben-2 flex justify-center lg:justify-end">
                    <x-landing.mockup-celular />
                </div>
            </div>
        </section>

        {{-- ===================== FAIXA DE DESTAQUES ===================== --}}
        <section id="recursos" class="mx-auto max-w-6xl scroll-mt-20 px-4 py-16 sm:px-6">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="text-3xl font-semibold tracking-tight text-slate-900 dark:text-white sm:text-4xl">
                    Tudo que o seu negócio precisa
                </h2>
                <p class="mt-3 text-lg text-slate-600 dark:text-slate-300">
                    Do agendamento do cliente à gestão da equipe — simples para quem usa, completo para quem administra.
                </p>
            </div>

            <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <x-landing.card-destaque icone="calendar-days" titulo="Agenda inteligente">
                    Disponibilidade calculada por profissional, com bloqueios e horários de trabalho — sem choque de agenda.
                </x-landing.card-destaque>
                <x-landing.card-destaque icone="device-phone-mobile" titulo="Portal do cliente">
                    Seu cliente agenda em poucos toques, direto do navegador do celular — escolhe serviço, profissional e horário.
                </x-landing.card-destaque>
                <x-landing.card-destaque icone="users" titulo="Equipe e permissões">
                    Papéis por função (dono, gerente, recepção, profissional) e suporte a múltiplas unidades.
                </x-landing.card-destaque>
            </div>
        </section>
    </main>

    <x-landing.footer />

    <x-landing.botoes-flutuantes />

    @fluxScripts
</body>
</html>
