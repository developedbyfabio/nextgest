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
                        <a href="#contato" onclick="document.querySelector(this.getAttribute('href')) || event.preventDefault()"
                            class="group inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-violet-600 via-indigo-600 to-blue-600 px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/25 transition duration-200 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-indigo-500/30 active:translate-y-0 active:scale-[0.98]">
                            Quero conhecer
                            <flux:icon name="arrow-right" class="size-4 transition group-hover:translate-x-0.5" />
                        </a>
                        <a href="#recursos" onclick="document.querySelector(this.getAttribute('href')) || event.preventDefault()"
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

        {{-- ===================== COMO FUNCIONA ===================== --}}
        <section id="como-funciona" class="scroll-mt-24 border-y border-slate-100 bg-slate-50 py-16 dark:border-slate-800/60 dark:bg-slate-900/40 sm:py-20">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-semibold tracking-tight text-slate-900 dark:text-white sm:text-4xl">Do cadastro ao cliente agendando — em 5 passos</h2>
                    <p class="mt-3 text-lg text-slate-600 dark:text-slate-300">Você configura uma vez; depois é só compartilhar o link e acompanhar pelo painel.</p>
                </div>

                <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <x-landing.passo numero="01" icone="scissors" titulo="Cadastre seus serviços">Defina serviços, duração e preço.</x-landing.passo>
                    <x-landing.passo numero="02" icone="users" titulo="Configure equipe e horários">Profissionais, unidades e janelas de trabalho.</x-landing.passo>
                    <x-landing.passo numero="03" icone="link" titulo="Compartilhe seu link">Um link público por estabelecimento — WhatsApp, Instagram, bio.</x-landing.passo>
                    <x-landing.passo numero="04" icone="device-phone-mobile" titulo="O cliente agenda sozinho">Escolhe serviço, profissional e horário pelo celular.</x-landing.passo>
                    <x-landing.passo numero="05" icone="chart-bar" titulo="Você acompanha pelo painel">Agenda, clientes, vendas e indicadores num só lugar.</x-landing.passo>
                </div>
            </div>
        </section>

        {{-- ===================== RECURSOS (BENTO) ===================== --}}
        <section id="recursos" class="scroll-mt-24 py-16 sm:py-20">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-semibold tracking-tight text-slate-900 dark:text-white sm:text-4xl">Tudo para organizar a agenda do seu negócio</h2>
                    <p class="mt-3 text-lg text-slate-600 dark:text-slate-300">Recursos pensados para o dia a dia de quem presta serviço por horário.</p>
                </div>

                <div class="mt-12 grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <x-landing.card-bento destaque icone="calendar-days" titulo="Agenda online">Disponibilidade calculada por profissional — sem choque de horário.</x-landing.card-bento>
                    <x-landing.card-bento destaque icone="link" titulo="Link público de agendamento">Um link por estabelecimento para o cliente marcar pelo celular.</x-landing.card-bento>
                    <x-landing.card-bento icone="users" titulo="Equipe e permissões" />
                    <x-landing.card-bento icone="identification" titulo="Cadastro de clientes" />
                    <x-landing.card-bento icone="scissors" titulo="Serviços e preços" />
                    <x-landing.card-bento icone="shopping-cart" titulo="Controle de vendas (comanda)" />
                    <x-landing.card-bento destaque icone="building-storefront" titulo="Multi-estabelecimento">Várias unidades, cada uma com sua agenda, equipe e serviços.</x-landing.card-bento>
                    <x-landing.card-bento destaque icone="chart-bar" titulo="Relatórios e indicadores">Faturamento, comparecimento e retenção do seu negócio num relance.</x-landing.card-bento>
                    <x-landing.card-bento icone="no-symbol" titulo="Bloqueio de horários" />
                    <x-landing.card-bento icone="user-circle" titulo="Visão por profissional" />
                    <x-landing.card-bento icone="ticket" titulo="Clube de assinatura" />
                    <x-landing.card-bento icone="squares-2x2" titulo="Painel administrativo" />
                </div>
            </div>
        </section>

        {{-- ===================== TIPOS DE NEGÓCIO ===================== --}}
        <section id="para-quem" class="scroll-mt-24 border-y border-slate-100 bg-slate-50 py-16 dark:border-slate-800/60 dark:bg-slate-900/40 sm:py-20">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-semibold tracking-tight text-slate-900 dark:text-white sm:text-4xl">Feito para o seu tipo de negócio</h2>
                    <p class="mt-3 text-lg text-slate-600 dark:text-slate-300">Se você atende por horário, o Nextgest se encaixa na sua rotina.</p>
                </div>

                <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <x-landing.card-publico icone="scissors" titulo="Barbearias">Encaixe cortes e barbas sem buraco na agenda e sem dois clientes no mesmo horário.</x-landing.card-publico>
                    <x-landing.card-publico icone="sparkles" titulo="Salões de beleza">Serviços combinados (corte + escova + coloração) com a duração certa reservada.</x-landing.card-publico>
                    <x-landing.card-publico icone="heart" titulo="Clínicas de estética">Pacotes e retornos organizados por profissional, com o histórico de cada cliente.</x-landing.card-publico>
                    <x-landing.card-publico icone="user" titulo="Profissionais autônomos">Sua agenda num link só — o cliente marca sem interromper o seu atendimento.</x-landing.card-publico>
                    <x-landing.card-publico icone="user-group" titulo="Pequenas equipes">Papéis por função e visão por profissional: cada um enxerga o que é seu.</x-landing.card-publico>
                    <x-landing.card-publico icone="building-office-2" titulo="Múltiplas unidades">Uma conta, várias filiais — agenda, equipe e serviços separados por unidade.</x-landing.card-publico>
                </div>
            </div>
        </section>

        {{-- ===================== PREVIEW DO PAINEL (AGENDA) ===================== --}}
        <section id="painel" class="scroll-mt-24 overflow-hidden py-16 sm:py-20">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                {{-- flex-col no mobile (evita o track auto do CSS grid crescer até o
                     max-content da grade de 7 colunas); grid só no desktop. --}}
                <div class="flex flex-col gap-10 lg:grid lg:grid-cols-5 lg:items-center lg:gap-12">
                    <div class="lg:col-span-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-indigo-200/70 bg-gradient-to-r from-violet-600/10 via-indigo-600/10 to-blue-600/10 px-3 py-1 text-xs font-semibold text-indigo-700 dark:border-indigo-500/30 dark:text-indigo-300">Painel da equipe</span>
                        <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-900 dark:text-white sm:text-4xl">Sua semana inteira num relance</h2>
                        <p class="mt-3 text-lg leading-relaxed text-slate-600 dark:text-slate-300">
                            Todos os agendamentos por dia e por profissional, sem planilha nem caderninho. O horário ocupado some na hora — nada de marcação dobrada.
                        </p>
                        <ul class="mt-6 flex flex-col gap-3 text-sm text-slate-600 dark:text-slate-300">
                            <li class="flex items-center gap-2"><flux:icon name="check-circle" class="size-5 shrink-0 text-indigo-600 dark:text-indigo-400" /> Visões de semana e de dia</li>
                            <li class="flex items-center gap-2"><flux:icon name="check-circle" class="size-5 shrink-0 text-indigo-600 dark:text-indigo-400" /> Filtros por profissional, unidade e status</li>
                            <li class="flex items-center gap-2"><flux:icon name="check-circle" class="size-5 shrink-0 text-indigo-600 dark:text-indigo-400" /> Status de cada atendimento de relance</li>
                        </ul>
                    </div>
                    <div class="w-full min-w-0 lg:col-span-3">
                        <x-landing.mockup-painel />
                    </div>
                </div>
            </div>
        </section>

        {{-- ===================== PLANOS ===================== --}}
        @php
            $wa = 'https://wa.me/5541991541757?text=';
            $waBasico = $wa.rawurlencode('Olá! Vim pelo site do Nextgest e tenho interesse no plano Básico.');
            $waProfissional = $wa.rawurlencode('Olá! Vim pelo site do Nextgest e tenho interesse no plano Profissional.');
            $waNextgest = $wa.rawurlencode('Olá! Vim pelo site do Nextgest e tenho interesse no plano Nextgest (completo).');
        @endphp
        <section id="planos" class="scroll-mt-24 border-y border-slate-100 bg-slate-50 py-16 dark:border-slate-800/60 dark:bg-slate-900/40 sm:py-20">
            <div class="mx-auto max-w-6xl px-4 sm:px-6">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-semibold tracking-tight text-slate-900 dark:text-white sm:text-4xl">Planos para cada momento do seu negócio</h2>
                    <p class="mt-3 text-lg text-slate-600 dark:text-slate-300">Comece simples e evolua conforme você cresce.</p>
                </div>

                <div class="mt-12 grid items-stretch gap-6 lg:grid-cols-3 lg:gap-5">
                    <x-landing.card-plano
                        nome="Básico" precoDe="R$ 99,90" precoPor="R$ 49,90"
                        etiqueta="Preço de lançamento · 1º ano"
                        paraQuem="Para profissionais e negócios começando."
                        :inclui="['Agenda online', 'Link público de agendamento', 'Gestão de equipe e horários', 'Cadastro de clientes', 'Controle de vendas (comanda)', 'Multi-estabelecimento (filiais)']"
                        :naoInclui="['Clube de assinatura', 'Integração com WhatsApp e outras integrações']"
                        ctaTexto="Começar agora" :ctaHref="$waBasico" />

                    <x-landing.card-plano destaque badge="Mais escolhido"
                        nome="Profissional" precoDe="R$ 199,90" precoPor="R$ 99,90"
                        etiqueta="Preço de lançamento · 1º ano"
                        paraQuem="Para barbearias e salões que querem crescer."
                        :inclui="['Tudo do plano Básico', 'Clube de assinatura personalizado', 'Relatórios e indicadores']"
                        :naoInclui="['Integração com WhatsApp e outras integrações']"
                        ctaTexto="Começar agora" :ctaHref="$waProfissional" />

                    <x-landing.card-plano
                        nome="Nextgest" precoDe="R$ 299,90" precoPor="R$ 199,90"
                        etiqueta="Preço de lançamento · 1º ano"
                        paraQuem="Para operações completas e múltiplas unidades."
                        :inclui="['Tudo do plano Profissional', 'Integração com WhatsApp', 'Demais integrações', 'Recursos avançados']"
                        ctaTexto="Falar com a gente" :ctaHref="$waNextgest" />
                </div>

                <div class="mx-auto mt-10 max-w-2xl text-center">
                    <p class="text-sm text-slate-600 dark:text-slate-300">
                        <flux:icon name="wrench-screwdriver" class="mr-1 inline size-4 text-indigo-600 dark:text-indigo-400" />
                        + valor único de <strong>instalação</strong>, a combinar conforme o tamanho da equipe, número de filiais e necessidades.
                    </p>
                    <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">
                        Preço de lançamento válido para o primeiro ano. Valores podem ser reajustados depois.
                    </p>
                </div>
            </div>
        </section>

        {{-- ===================== FAQ ===================== --}}
        <section id="faq" class="scroll-mt-24 py-16 sm:py-20">
            <div class="mx-auto max-w-3xl px-4 sm:px-6">
                <div class="text-center">
                    <h2 class="text-3xl font-semibold tracking-tight text-slate-900 dark:text-white sm:text-4xl">Perguntas frequentes</h2>
                    <p class="mt-3 text-lg text-slate-600 dark:text-slate-300">O que os donos costumam perguntar antes de começar.</p>
                </div>

                <div class="mt-10 flex flex-col gap-3">
                    <x-landing.item-faq pergunta="O cliente precisa baixar aplicativo?">Não. Ele agenda pelo navegador do celular — é só abrir o seu link.</x-landing.item-faq>
                    <x-landing.item-faq pergunta="Posso usar em mais de uma unidade?">Sim. O Nextgest é multi-estabelecimento, com agenda, equipe e serviços geridos por filial.</x-landing.item-faq>
                    <x-landing.item-faq pergunta="Funciona para barbearias e salões?">Sim — e também para clínicas de estética, profissionais autônomos e pequenas equipes.</x-landing.item-faq>
                    <x-landing.item-faq pergunta="Consigo controlar os horários de cada profissional?">Sim. Você define as janelas de trabalho por profissional e por unidade; a disponibilidade é calculada sozinha.</x-landing.item-faq>
                    <x-landing.item-faq pergunta="Tem painel administrativo?">Sim. Agenda, equipe, clientes, vendas (comanda) e indicadores num só lugar.</x-landing.item-faq>
                    <x-landing.item-faq pergunta="Posso compartilhar o link no WhatsApp e Instagram?">Sim. Cada estabelecimento tem um link público de agendamento para divulgar onde quiser.</x-landing.item-faq>
                    <x-landing.item-faq pergunta="É responsivo no celular?">Sim. O portal do cliente é feito mobile-first; o painel funciona bem em qualquer tela.</x-landing.item-faq>
                    <x-landing.item-faq pergunta="Como funciona o clube de assinatura?">Disponível a partir do plano Profissional: você cria planos recorrentes, com os serviços cobertos definidos por você.</x-landing.item-faq>
                    <x-landing.item-faq pergunta="Posso pedir uma demonstração?">Claro. Fale com a gente pelo WhatsApp ou e-mail e mostramos a plataforma funcionando.</x-landing.item-faq>
                </div>
            </div>
        </section>

        {{-- ===================== CTA FINAL ===================== --}}
        <x-landing.cta-final />
    </main>

    <x-landing.footer />

    <x-landing.botoes-flutuantes />

    @fluxScripts
</body>
</html>
