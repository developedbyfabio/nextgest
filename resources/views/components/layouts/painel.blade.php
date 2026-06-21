@php($tenantId = tenant('id'))
@php($aparencia = \App\Support\Aparencia::doTenant())
@php($logoUrl = \App\Support\Aparencia::urlArquivo($aparencia['logo']))
@php($temaEscuro = \App\Support\Aparencia::superficieEscura($aparencia))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => $temaEscuro])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Painel' }} · {{ tenant('nome') ?? 'Nextgest' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- O painel reflete o TEMA do estabelecimento (como o portal): cores e
         superfície vêm das CSS vars de Aparencia. O modo escuro do Flux é ligado
         pela classe `dark` no <html> quando a superfície da marca é escura, para
         os componentes Flux acompanharem. Não seguimos o tema do sistema. --}}
</head>
{{-- Superfície/identidade da marca em todo o shell; o accent já alimenta os
     componentes Flux (botões/foco/estado ativo). --}}
<body class="min-h-screen" style="{{ \App\Support\Aparencia::cssVars($aparencia) }}; background-color: var(--cor-fundo); color: var(--cor-texto);">

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

    <flux:sidebar sticky stashable class="ng-surface border-e" style="background-color: color-mix(in srgb, var(--cor-texto) 3%, var(--cor-superficie)); border-color: color-mix(in srgb, var(--cor-texto) 10%, transparent);">
        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

        {{-- Marca do estabelecimento (logo enviado ou ícone na cor da marca). --}}
        <a href="{{ route('painel.dashboard', ['tenant' => $tenantId]) }}" class="flex items-center gap-2 px-2 py-1" wire:navigate>
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ tenant('nome') }}" class="size-8 rounded-lg object-contain" />
            @else
                <span class="flex size-8 items-center justify-center rounded-lg" style="background-color: var(--cor-principal); color: var(--cor-sobre-principal);">
                    <flux:icon name="calendar-days" class="size-5" />
                </span>
            @endif
            <span class="truncate font-semibold" style="color: var(--cor-texto);">{{ tenant('nome') }}</span>
        </a>

        <flux:navlist variant="outline">
            <flux:navlist.item icon="home" :href="route('painel.dashboard', ['tenant' => $tenantId])" :current="request()->routeIs('painel.dashboard')" wire:navigate>
                Início
            </flux:navlist.item>

            <flux:navlist.group heading="Operação" expandable :expanded="true">
                @canany(['ver_agenda', 'ver_agenda_propria'])
                    <flux:navlist.item icon="calendar-days" :href="route('painel.agenda', ['tenant' => $tenantId])" :current="request()->routeIs('painel.agenda')" wire:navigate>Agendamentos</flux:navlist.item>
                @endcanany
                @can('editar_servico')
                    <flux:navlist.item icon="scissors" :href="route('painel.servicos', ['tenant' => $tenantId])" :current="request()->routeIs('painel.servicos')" wire:navigate>Serviços</flux:navlist.item>
                @endcan
                @can('gerir_agenda')
                    <flux:navlist.item icon="no-symbol" :href="route('painel.bloqueios', ['tenant' => $tenantId])" :current="request()->routeIs('painel.bloqueios')" wire:navigate>Bloqueios</flux:navlist.item>
                @endcan
                @can('ver_kanban_atendimento')
                    <flux:navlist.item icon="view-columns" :href="route('painel.kanban', ['tenant' => $tenantId])" :current="request()->routeIs('painel.kanban')" wire:navigate>Kanban</flux:navlist.item>
                @endcan
            </flux:navlist.group>

            <flux:navlist.group heading="Gestão" expandable :expanded="true">
                @can('gerir_unidades')
                    <flux:navlist.item icon="building-storefront" :href="route('painel.unidades', ['tenant' => $tenantId])" :current="request()->routeIs('painel.unidades')" wire:navigate>Unidades</flux:navlist.item>
                @endcan
                @can('editar_usuario')
                    <flux:navlist.item icon="identification" :href="route('painel.equipe', ['tenant' => $tenantId])" :current="request()->routeIs('painel.equipe') || request()->routeIs('painel.equipe.horarios')" wire:navigate>Equipe</flux:navlist.item>
                @endcan
                @can('editar_permissoes')
                    <flux:navlist.item icon="shield-check" :href="route('painel.papeis', ['tenant' => $tenantId])" :current="request()->routeIs('painel.papeis')" wire:navigate>Papéis e permissões</flux:navlist.item>
                @endcan
                @can('gerir_aparencia')
                    <flux:navlist.item icon="paint-brush" :href="route('painel.aparencia', ['tenant' => $tenantId])" :current="request()->routeIs('painel.aparencia')" wire:navigate>Aparência</flux:navlist.item>
                @endcan
            </flux:navlist.group>
        </flux:navlist>

        <flux:spacer />

        <flux:dropdown position="top" align="start" class="max-lg:hidden">
            <flux:profile :name="auth('web')->user()?->name" :initials="\Illuminate\Support\Str::of(auth('web')->user()?->name)->explode(' ')->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('')" />

            <flux:menu>
                <flux:menu.item icon="user">{{ auth('web')->user()?->email }}</flux:menu.item>

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
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
        <flux:spacer />
        <form method="POST" action="{{ route('painel.logout', ['tenant' => $tenantId]) }}">
            @csrf
            <flux:button type="submit" variant="subtle" size="sm" icon="arrow-right-start-on-rectangle">Sair</flux:button>
        </form>
    </flux:header>

    <flux:main>
        {{ $slot }}
    </flux:main>

    <flux:toast position="top right" />

    @fluxScripts
</body>
</html>
