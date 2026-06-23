@php($tenantId = tenant('id'))
@php($aparencia = \App\Support\Aparencia::doTenant())
@php($logoUrl = \App\Support\Aparencia::urlArquivo($aparencia['logo']))
<!DOCTYPE html>
{{-- font-size base no <html>: o "tamanho base" do tenant escala a UI (rem). --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" style="font-size: {{ $aparencia['tamanho_base'] }};">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Painel' }} · {{ tenant('nome') ?? 'Nextgest' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Etapa D: o painel respeita o MODO claro/escuro/sistema (Flux). A marca do
         estabelecimento entra como ACENTO + logo + tipografia (constante nos dois
         modos); as superfícies vêm dos tokens de claro/escuro. --}}
    @fluxAppearance
    {{-- Tipografia da marca: carrega a fonte (Google) escolhida pelo tenant, se houver. --}}
    {!! \App\Support\Aparencia::linkFonteGoogle($aparencia) !!}
</head>
{{-- Marca = acento/tipografia (alimenta os componentes Flux: botões/foco/estado
     ativo); as superfícies seguem o modo via tokens (--cor-fundo/--cor-texto). --}}
<body class="min-h-screen" style="{{ \App\Support\Aparencia::cssVarsAcento($aparencia) }}; background-color: var(--cor-fundo); color: var(--cor-texto);">

    @if (session('suporte_ativo'))
        <div class="sticky top-0 z-50 flex flex-wrap items-center justify-between gap-2 bg-amber-500 px-4 py-2 text-sm font-medium text-amber-950">
            <span class="flex items-center gap-2">
                <flux:icon name="lifebuoy" class="size-4" />
                Modo suporte — acessando como {{ auth('web')->user()?->name }} · {{ tenant('nome') }}
            </span>
            <form method="POST" action="{{ route('painel.suporte.sair', ['tenant' => $tenantId]) }}">
                @csrf
                <flux:button type="submit" size="sm" variant="primary">Sair do suporte</flux:button>
            </form>
        </div>
    @endif

    {{-- Sidebar do painel (D36). Colapsável (reaproveita o Flux nativo): no DESKTOP recolhe
         para uma faixa só com ícones e o conteúdo ao lado se alarga (grid `min-content`);
         no MOBILE vira um drawer sobreposto com overlay. Cabeçalho e rodapé FIXOS — só a
         navlist do meio rola (flex + `min-h-0`). Cantos arredondados SÓ à direita (o lado
         esquerdo encosta na borda da tela). NÃO usa `persist` de propósito: o Flux só grava
         o estado recolhido em localStorage quando `persist` está ligado; aqui o estado fica
         por sessão de navegação (regra do projeto: sem localStorage/sessionStorage). --}}
    <flux:sidebar collapsible
        class="flex flex-col overflow-hidden rounded-e-2xl border-e lg:sticky lg:top-0 lg:h-dvh"
        style="background-color: color-mix(in srgb, var(--cor-texto) 3%, var(--cor-superficie)); border-color: color-mix(in srgb, var(--cor-texto) 10%, transparent);">

        {{-- Cabeçalho FIXO: marca + hambúrguer na MESMA linha (à direita), na cor de acento.
             Recolhido (desktop), some a marca e o hambúrguer centraliza para reexpandir. --}}
        <flux:sidebar.header class="shrink-0">
            <a href="{{ route('painel.dashboard', ['tenant' => $tenantId]) }}" class="flex min-w-0 items-center gap-2 in-data-flux-sidebar-collapsed-desktop:hidden" wire:navigate>
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ tenant('nome') }}" class="size-8 shrink-0 rounded-lg object-contain" />
                @else
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg" style="background-color: var(--cor-principal); color: var(--cor-sobre-principal);">
                        <flux:icon name="calendar-days" class="size-5" />
                    </span>
                @endif
                <span class="truncate font-semibold" style="color: var(--cor-texto);">{{ tenant('nome') }}</span>
            </a>

            {{-- Hambúrguer: recolhe/expande (desktop) ou abre/fecha o drawer (mobile) — o mesmo
                 evento `flux-sidebar-toggle` decide pelo viewport. Cor = acento da aparência. --}}
            <flux:sidebar.toggle icon="bars-3"
                class="shrink-0 in-data-flux-sidebar-collapsed-desktop:mx-auto"
                style="color: var(--cor-principal);" />
        </flux:sidebar.header>

        {{-- ÚNICA área que rola (header/rodapé ficam fixos). `min-h-0` deixa o flex encolher
             e o overflow acontecer AQUI, não no container (evita rolagem dupla). --}}
        <flux:navlist variant="outline" class="min-h-0 flex-1 overflow-y-auto">
            {{-- "Início" é item de TOPO da sidebar: usa flux:sidebar.item (não navlist.item).
                 Só o sidebar.item traz as classes de estado RECOLHIDO (w-10 + justify-center +
                 oculta-texto + tooltip); o navlist.item não tem nenhuma, então no modo faixa
                 de ícones ele ficava com largura cheia e texto visível — "espremido". Assim o
                 Início acompanha os cabeçalhos de grupo (os outros itens de topo) e recolhe
                 igual a eles. --}}
            <flux:sidebar.item icon="home" :href="route('painel.dashboard', ['tenant' => $tenantId])" :current="request()->routeIs('painel.dashboard')" wire:navigate>
                Início
            </flux:sidebar.item>

            <flux:sidebar.group heading="Operação" icon="squares-2x2" expandable :expanded="true">
                @canany(['ver_agenda', 'ver_agenda_propria'])
                    <flux:navlist.item icon="calendar-days" :href="route('painel.agenda', ['tenant' => $tenantId])" :current="request()->routeIs('painel.agenda')" wire:navigate>Agendamentos</flux:navlist.item>
                @endcanany
                @can('editar_servico')
                    <flux:navlist.item icon="scissors" :href="route('painel.servicos', ['tenant' => $tenantId])" :current="request()->routeIs('painel.servicos')" wire:navigate>Serviços</flux:navlist.item>
                @endcan
                @canany(['editar_produto', 'gerir_estoque'])
                    <flux:navlist.item icon="cube" :href="route('painel.produtos', ['tenant' => $tenantId])" :current="request()->routeIs('painel.produtos')" wire:navigate>Produtos</flux:navlist.item>
                @endcanany
                @can('criar_venda')
                    <flux:navlist.item icon="shopping-cart" :href="route('painel.vendas', ['tenant' => $tenantId])" :current="request()->routeIs('painel.vendas') || request()->routeIs('painel.vendas.detalhe')" wire:navigate>Comandas</flux:navlist.item>
                @endcan
                @can('gerir_agenda')
                    <flux:navlist.item icon="no-symbol" :href="route('painel.bloqueios', ['tenant' => $tenantId])" :current="request()->routeIs('painel.bloqueios')" wire:navigate>Bloqueios</flux:navlist.item>
                    <flux:navlist.item icon="clock" :href="route('painel.funcionamento', ['tenant' => $tenantId])" :current="request()->routeIs('painel.funcionamento')" wire:navigate>Funcionamento</flux:navlist.item>
                @endcan
                @can('ver_kanban_atendimento')
                    <flux:navlist.item icon="view-columns" :href="route('painel.kanban', ['tenant' => $tenantId])" :current="request()->routeIs('painel.kanban')" wire:navigate>Kanban</flux:navlist.item>
                @endcan
            </flux:sidebar.group>

            <flux:sidebar.group heading="Gestão" icon="cog-6-tooth" expandable :expanded="true">
                @can('gerir_unidades')
                    <flux:navlist.item icon="building-storefront" :href="route('painel.unidades', ['tenant' => $tenantId])" :current="request()->routeIs('painel.unidades')" wire:navigate>Unidades</flux:navlist.item>
                @endcan
                @can('editar_usuario')
                    <flux:navlist.item icon="identification" :href="route('painel.equipe', ['tenant' => $tenantId])" :current="request()->routeIs('painel.equipe') || request()->routeIs('painel.equipe.horarios')" wire:navigate>Equipe</flux:navlist.item>
                @endcan
                @can('ver_financeiro')
                    <flux:navlist.item icon="wallet" :href="route('painel.comissoes', ['tenant' => $tenantId])" :current="request()->routeIs('painel.comissoes')" wire:navigate>Comissões</flux:navlist.item>
                @endcan
                @can('ver_indicadores')
                    <flux:navlist.item icon="chart-bar" :href="route('painel.indicadores', ['tenant' => $tenantId])" :current="request()->routeIs('painel.indicadores')" wire:navigate>Indicadores</flux:navlist.item>
                @endcan
                @recurso('clube')
                    @can('gerenciar_clube')
                        <flux:navlist.item icon="ticket" :href="route('painel.clube', ['tenant' => $tenantId])" :current="request()->routeIs('painel.clube')" wire:navigate>Clube de Assinatura</flux:navlist.item>
                    @endcan
                @endrecurso
                @can('editar_permissoes')
                    <flux:navlist.item icon="shield-check" :href="route('painel.papeis', ['tenant' => $tenantId])" :current="request()->routeIs('painel.papeis')" wire:navigate>Papéis e permissões</flux:navlist.item>
                @endcan
                @can('gerir_aparencia')
                    <flux:navlist.item icon="paint-brush" :href="route('painel.aparencia', ['tenant' => $tenantId])" :current="request()->routeIs('painel.aparencia')" wire:navigate>Aparência</flux:navlist.item>
                @endcan
                @if (auth('web')->user()?->hasAnyPermission(\App\Enums\Integracao::permissoes()))
                    <flux:navlist.item icon="puzzle-piece" :href="route('painel.integracoes', ['tenant' => $tenantId])" :current="request()->routeIs('painel.integracoes*')" wire:navigate>Integrações</flux:navlist.item>
                @endif
            </flux:sidebar.group>

            @can('ver_financeiro')
                <flux:sidebar.group heading="Financeiro" icon="banknotes" expandable :expanded="true">
                    <flux:navlist.item icon="currency-dollar" :href="route('painel.financeiro', ['tenant' => $tenantId])" :current="request()->routeIs('painel.financeiro')" wire:navigate>Visão financeira</flux:navlist.item>
                </flux:sidebar.group>
            @endcan
        </flux:navlist>

        {{-- Rodapé FIXO: usuário + ações (perfil/senha/2FA/tema/sair). Aparece também no
             drawer mobile; recolhido no desktop mostra só o avatar (flux:sidebar.profile). --}}
        <flux:dropdown position="top" align="start" class="shrink-0">
            <flux:sidebar.profile :name="auth('web')->user()?->name" :initials="\Illuminate\Support\Str::of(auth('web')->user()?->name)->explode(' ')->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('')" />

            <flux:menu>
                {{-- Cabeçalho do dropdown: NOME em destaque + e-mail menor abaixo (Item 4).
                     Antes o e-mail era um flux:menu.item inerte (parecia clicável e não fazia
                     nada). Cores zinc do Flux para contrastar na superfície do popover (claro/escuro). --}}
                <div class="flex flex-col px-2 py-1.5">
                    <span class="truncate text-sm font-semibold text-zinc-800 dark:text-white">{{ auth('web')->user()?->name }}</span>
                    <span class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ auth('web')->user()?->email }}</span>
                </div>

                <flux:menu.separator />

                {{-- Alterar a própria senha (abre o modal embutido x-livewire abaixo). --}}
                <flux:menu.item icon="key" x-on:click="$flux.modal('alterar-senha').show()">Alterar senha</flux:menu.item>

                {{-- 2FA (TOTP): opcional e SÓ Dono (permissão gerenciar_2fa_proprio, D39). --}}
                @if (auth('web')->user()?->can('gerenciar_2fa_proprio'))
                    <flux:menu.item icon="shield-check" x-on:click="$flux.modal('dois-fatores').show()">Autenticação em duas etapas</flux:menu.item>
                @endif

                <flux:menu.separator />

                {{-- x-model DIRETO em $flux.appearance (não uma cópia local, que ficaria inerte). --}}
                <flux:menu.radio.group x-data x-model="$flux.appearance" heading="Tema">
                    <flux:menu.radio value="light" icon="sun">Claro</flux:menu.radio>
                    <flux:menu.radio value="dark" icon="moon">Escuro</flux:menu.radio>
                    <flux:menu.radio value="system" icon="computer-desktop">Sistema</flux:menu.radio>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <form method="POST" action="{{ route('painel.logout', ['tenant' => $tenantId]) }}">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" variant="danger" class="w-full">
                        Sair
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:sidebar>

    <flux:header sticky class="lg:hidden border-b" style="background-color: color-mix(in srgb, var(--cor-texto) 3%, var(--cor-superficie)); border-color: color-mix(in srgb, var(--cor-texto) 10%, transparent);">
        {{-- Abre o drawer (mesmo evento do hambúrguer do cabeçalho). Cor = acento. --}}
        <flux:sidebar.toggle class="lg:hidden" icon="bars-3" inset="left" style="color: var(--cor-principal);" />
        <a href="{{ route('painel.dashboard', ['tenant' => $tenantId]) }}" class="flex min-w-0 items-center gap-2" wire:navigate>
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ tenant('nome') }}" class="size-7 shrink-0 rounded-lg object-contain" />
            @endif
            <span class="truncate font-semibold" style="color: var(--cor-texto);">{{ tenant('nome') }}</span>
        </a>
        <flux:spacer />
        <form method="POST" action="{{ route('painel.logout', ['tenant' => $tenantId]) }}">
            @csrf
            <flux:button type="submit" variant="subtle" size="sm" icon="arrow-right-start-on-rectangle">Sair</flux:button>
        </form>
    </flux:header>

    <flux:main>
        {{ $slot }}
    </flux:main>

    {{-- Modal de alterar senha (self-service, todos os papéis) — aberto pelo menu de perfil. --}}
    <livewire:painel.alterar-senha />

    {{-- Modal de 2FA (TOTP) — SÓ Dono. Embutido só para quem pode gerir (senão o gate
         do componente abortaria 403 em toda página do painel para os demais papéis). --}}
    @if (auth('web')->user()?->can('gerenciar_2fa_proprio'))
        <flux:modal name="dois-fatores" class="md:w-[34rem]">
            <livewire:painel.seguranca.dois-fatores />
        </flux:modal>
    @endif

    <flux:toast position="top right" />

    @fluxScripts
</body>
</html>
