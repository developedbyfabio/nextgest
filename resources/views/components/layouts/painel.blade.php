<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Painel' }} · {{ tenant('nome') ?? 'Nextgest' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="min-h-screen bg-white dark:bg-zinc-800">
    @php($tenantId = tenant('id'))

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

    <flux:sidebar sticky stashable class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

        <flux:brand :href="route('painel.dashboard', ['tenant' => $tenantId])" name="Nextgest" class="px-2 dark:hidden" />
        <flux:brand :href="route('painel.dashboard', ['tenant' => $tenantId])" name="Nextgest" class="hidden px-2 dark:flex" />

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

                <flux:menu.radio.group x-data="{ appearance: $flux.appearance }" x-model="appearance" heading="Tema">
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

    <flux:header sticky class="lg:hidden border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
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
