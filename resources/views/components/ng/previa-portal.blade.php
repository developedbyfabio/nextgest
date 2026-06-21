@props([
    'aparencia' => [],
    'nome' => 'Seu Estabelecimento',
])

@php($a = array_merge(\App\Support\Aparencia::PADRAO, $aparencia))
@php($logoUrl = $a['logo_url'] ?? null)
@php($headerUrl = $a['header_url'] ?? null)
@php($fundoUrl = $a['fundo_url'] ?? null)

{{--
    Prévia FIEL do portal do cliente: renderiza as MESMAS telas/componentes que o
    cliente vê (x-portal.cabecalho / capa / como-funciona / servico), num carrossel
    estilo Instagram dentro de uma moldura de celular. A marca (acento + secundária
    + tipografia) entra inline por cssVarsAcento; as superfícies seguem o alternador
    claro/escuro PRÓPRIO (.ng-previa / .ng-previa.is-dark), independente do painel.
    Dados de exemplo, somente leitura. Reutilizado na edição e no onboarding.
--}}
<div x-data="{ dark: false, tela: 0, total: 4, telas: ['Início', 'Login', 'Cliente', 'Agendar'] }" class="flex flex-col items-center gap-3" wire:key="previa-portal">
    {{-- Alternador claro/escuro — afeta SÓ a prévia, todas as telas. --}}
    <div class="inline-flex items-center gap-1 rounded-full border border-zinc-200 bg-white p-0.5 text-xs dark:border-zinc-700 dark:bg-zinc-800">
        <button type="button" @click="dark = false"
            class="flex items-center gap-1 rounded-full px-2.5 py-1 font-medium transition"
            :class="!dark ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-500'">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5"><path d="M10 2a.75.75 0 0 1 .75.75v1.5a.75.75 0 0 1-1.5 0v-1.5A.75.75 0 0 1 10 2ZM10 15a.75.75 0 0 1 .75.75v1.5a.75.75 0 0 1-1.5 0v-1.5A.75.75 0 0 1 10 15ZM10 7a3 3 0 1 0 0 6 3 3 0 0 0 0-6ZM15.657 5.404a.75.75 0 1 0-1.06-1.06l-1.061 1.06a.75.75 0 0 0 1.06 1.06l1.06-1.06ZM6.464 14.596a.75.75 0 1 0-1.06-1.06l-1.06 1.06a.75.75 0 0 0 1.06 1.06l1.06-1.06ZM18 10a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1 0-1.5h1.5A.75.75 0 0 1 18 10ZM5 10a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1 0-1.5h1.5A.75.75 0 0 1 5 10ZM14.596 15.657a.75.75 0 0 0 1.06-1.06l-1.06-1.061a.75.75 0 1 0-1.06 1.06l1.06 1.06ZM5.404 6.464a.75.75 0 0 0 1.06-1.06l-1.06-1.06a.75.75 0 1 0-1.061 1.06l1.06 1.06Z"/></svg> Claro
        </button>
        <button type="button" @click="dark = true"
            class="flex items-center gap-1 rounded-full px-2.5 py-1 font-medium transition"
            :class="dark ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-500'">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5"><path fill-rule="evenodd" d="M7.455 2.004a.75.75 0 0 1 .26.77 7 7 0 0 0 9.958 7.967.75.75 0 0 1 1.067.853A8.5 8.5 0 1 1 6.647 1.921a.75.75 0 0 1 .808.083Z" clip-rule="evenodd"/></svg> Escuro
        </button>
    </div>

    {{-- Moldura de celular (maior) com o carrossel das telas do cliente. --}}
    <div class="relative">
        <div
            {{ $attributes->class('ng-previa relative mx-auto w-[20rem] max-w-full overflow-hidden rounded-[2.4rem] border-[7px] border-zinc-800 shadow-2xl dark:border-zinc-700') }}
            :class="{ 'is-dark': dark }"
            style="height: 40rem; {{ \App\Support\Aparencia::cssVarsAcento($a) }}; background-color: var(--cor-fundo); color: var(--cor-texto);@if ($fundoUrl) background-image: url('{{ $fundoUrl }}'); background-size: cover; background-position: center;@endif"
        >
            {{-- Trilho do carrossel: cada tela ocupa 100% da largura da moldura;
                 desloca 100% por tela (cada slide é w-full shrink-0). O scrim de
                 leitura (.ng-com-fundo) vai em CADA tela (sobre a foto da moldura),
                 para o texto ficar legível (a foto da marca aparece ~18% atrás). --}}
            <div class="flex h-full transition-transform duration-300 ease-out" :style="`transform: translateX(-${tela * 100}%)`">

                {{-- TELA 0 — Portal deslogado (MESMO componente da home real) --}}
                <div @class(['flex h-full w-full shrink-0 flex-col overflow-y-auto', 'ng-com-fundo' => $fundoUrl])>
                    <x-portal.cabecalho :nome="$nome" :logoUrl="$logoUrl">
                        <span class="rounded-md px-2.5 py-1 text-xs font-medium" style="background-color: var(--cor-principal); color: var(--cor-sobre-principal);">Entrar</span>
                    </x-portal.cabecalho>
                    <div class="flex flex-1 flex-col p-4">
                        <x-portal.tela-inicio :nome="$nome" :header-url="$headerUrl" />
                    </div>
                </div>

                {{-- TELA 1 — Login do cliente --}}
                <div @class(['flex h-full w-full shrink-0 flex-col overflow-y-auto', 'ng-com-fundo' => $fundoUrl])>
                    <x-portal.cabecalho :nome="$nome" :logoUrl="$logoUrl">
                        <span class="rounded-md px-2.5 py-1 text-xs font-medium" style="background-color: var(--cor-principal); color: var(--cor-sobre-principal);">Entrar</span>
                    </x-portal.cabecalho>
                    <div class="flex flex-1 flex-col gap-4 p-4">
                        <div>
                            <div class="text-lg font-semibold">Entrar</div>
                            <div class="text-sm" style="color: var(--cor-texto-suave);">Acesse para agendar em {{ $nome }}</div>
                        </div>
                        @foreach (['E-mail' => 'voce@exemplo.com', 'Senha' => '••••••••'] as $rotulo => $ph)
                            <div class="flex flex-col gap-1">
                                <span class="text-sm font-medium">{{ $rotulo }}</span>
                                <div class="rounded-lg border px-3 py-2 text-sm" style="border-color: color-mix(in srgb, var(--cor-texto) 16%, transparent); background-color: var(--cor-superficie); color: var(--cor-texto-suave);">{{ $ph }}</div>
                            </div>
                        @endforeach
                        <button type="button" class="w-full rounded-lg px-4 py-2.5 text-sm font-semibold" style="background-color: var(--cor-principal); color: var(--cor-sobre-principal);">Entrar</button>
                        <div class="flex items-center gap-2 text-xs" style="color: var(--cor-texto-suave);"><span class="h-px flex-1" style="background-color: color-mix(in srgb, var(--cor-texto) 12%, transparent);"></span>ou<span class="h-px flex-1" style="background-color: color-mix(in srgb, var(--cor-texto) 12%, transparent);"></span></div>
                        <button type="button" class="w-full rounded-lg px-4 py-2.5 text-sm font-medium" style="color: var(--cor-texto); background-color: color-mix(in srgb, var(--cor-texto) 6%, transparent);">Criar uma conta</button>
                    </div>
                </div>

                {{-- TELA 2 — Home do cliente logado --}}
                <div @class(['flex h-full w-full shrink-0 flex-col overflow-y-auto', 'ng-com-fundo' => $fundoUrl])>
                    <x-portal.cabecalho :nome="$nome" :logoUrl="$logoUrl">
                        <span class="flex size-7 items-center justify-center rounded-full" style="background-color: color-mix(in srgb, var(--cor-texto) 8%, transparent); color: var(--cor-texto-suave);">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-5"><path fill-rule="evenodd" d="M18.685 19.097A9.723 9.723 0 0 0 21.75 12c0-5.385-4.365-9.75-9.75-9.75S2.25 6.615 2.25 12a9.723 9.723 0 0 0 3.065 7.097A9.716 9.716 0 0 0 12 21.75a9.716 9.716 0 0 0 6.685-2.653Zm-12.54-1.285A7.486 7.486 0 0 1 12 15a7.486 7.486 0 0 1 5.855 2.812A8.224 8.224 0 0 1 12 20.25a8.224 8.224 0 0 1-5.855-2.438ZM15.75 9a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" clip-rule="evenodd"/></svg>
                        </span>
                    </x-portal.cabecalho>
                    <div class="flex flex-1 flex-col gap-4 p-4">
                        <div>
                            <div class="text-xl font-semibold">Olá, Ana</div>
                            <div class="text-sm" style="color: var(--cor-texto-suave);">Agende seu horário em poucos toques.</div>
                        </div>
                        <button type="button" class="flex w-full items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold" style="background-color: var(--cor-principal); color: var(--cor-sobre-principal);">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
                            Novo agendamento
                        </button>
                        <div class="text-sm font-semibold">Próximos agendamentos</div>
                        <div class="flex gap-3 rounded-xl border p-4" style="border-color: color-mix(in srgb, var(--cor-texto) 10%, transparent); background-color: var(--cor-superficie);">
                            <div class="flex shrink-0 flex-col items-center justify-center rounded-lg px-3 py-2 text-center" style="background-color: color-mix(in srgb, var(--cor-principal) 10%, var(--cor-superficie)); color: var(--cor-principal);">
                                <span class="text-lg font-bold leading-none">12</span>
                                <span class="text-[0.65rem] font-medium uppercase">jul</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="font-semibold capitalize">sex · 14:30</div>
                                <div class="text-sm" style="color: var(--cor-texto-suave);">Corte masculino</div>
                            </div>
                            <span class="self-start rounded-md px-2 py-0.5 text-xs font-medium" style="background-color: color-mix(in srgb, var(--cor-principal) 14%, transparent); color: var(--cor-principal);">Confirmado</span>
                        </div>
                    </div>
                </div>

                {{-- TELA 3 — Fluxo de agendamento (passo serviços) --}}
                <div @class(['flex h-full w-full shrink-0 flex-col overflow-y-auto', 'ng-com-fundo' => $fundoUrl])>
                    <x-portal.cabecalho :nome="$nome" :logoUrl="$logoUrl">
                        <span class="rounded-md px-2.5 py-1 text-xs font-medium" style="background-color: var(--cor-principal); color: var(--cor-sobre-principal);">Entrar</span>
                    </x-portal.cabecalho>
                    <div class="flex flex-1 flex-col gap-4 p-4">
                        <div>
                            <div class="text-lg font-semibold">Novo agendamento</div>
                            <div class="text-sm" style="color: var(--cor-texto-suave);">Passo 1 de 3 · Serviços</div>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="h-1.5 flex-1 rounded-full" style="background-color: var(--cor-principal);"></div>
                            <div class="h-1.5 flex-1 rounded-full" style="background-color: color-mix(in srgb, var(--cor-texto) 12%, transparent);"></div>
                            <div class="h-1.5 flex-1 rounded-full" style="background-color: color-mix(in srgb, var(--cor-texto) 12%, transparent);"></div>
                        </div>
                        <x-portal.servico nome="Corte masculino" :duracao="30" :preco="45" :selecionado="true" />
                        <x-portal.servico nome="Barba" :duracao="20" :preco="30" />
                        <x-portal.servico nome="Corte + barba" :duracao="50" :preco="70" />
                        <div class="mt-auto flex items-center justify-between rounded-xl px-4 py-3 text-sm" style="background-color: color-mix(in srgb, var(--cor-principal) 8%, var(--cor-superficie));">
                            <span style="color: var(--cor-texto-suave);">30 min</span>
                            <span class="text-base font-semibold">R$ 45,00</span>
                        </div>
                        <button type="button" class="w-full rounded-lg px-4 py-2.5 text-sm font-semibold" style="background-color: var(--cor-principal); color: var(--cor-sobre-principal);">Continuar</button>
                    </div>
                </div>

            </div>

            {{-- Setas --}}
            <button type="button" @click="tela = Math.max(0, tela - 1)" x-show="tela > 0"
                class="absolute left-1.5 top-1/2 z-10 flex size-8 -translate-y-1/2 items-center justify-center rounded-full bg-black/40 text-white backdrop-blur transition hover:bg-black/60" aria-label="Anterior">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5"><path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 0 1 0 1.06L9.06 10l3.73 3.71a.75.75 0 1 1-1.06 1.06l-4.25-4.24a.75.75 0 0 1 0-1.06l4.25-4.24a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/></svg>
            </button>
            <button type="button" @click="tela = Math.min(total - 1, tela + 1)" x-show="tela < total - 1"
                class="absolute right-1.5 top-1/2 z-10 flex size-8 -translate-y-1/2 items-center justify-center rounded-full bg-black/40 text-white backdrop-blur transition hover:bg-black/60" aria-label="Próxima">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 0-1.06L10.94 10 7.21 6.29a.75.75 0 1 1 1.06-1.06l4.25 4.24a.75.75 0 0 1 0 1.06l-4.25 4.24a.75.75 0 0 1-1.06 0Z" clip-rule="evenodd"/></svg>
            </button>
        </div>
    </div>

    {{-- Dots + nome da tela --}}
    <div class="flex items-center gap-2">
        <template x-for="i in total" :key="i">
            <button type="button" @click="tela = i - 1"
                class="size-2 rounded-full transition"
                :class="tela === i - 1 ? 'bg-zinc-800 dark:bg-zinc-200' : 'bg-zinc-300 dark:bg-zinc-600'"
                :aria-label="telas[i - 1]"></button>
        </template>
    </div>
    <flux:text class="text-center text-xs text-zinc-500">
        <span x-text="telas[tela]"></span> · alterna claro/escuro acima · atualiza enquanto você edita.
    </flux:text>
</div>
