<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
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

    <flux:sidebar sticky stashable class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

        <flux:brand :href="route('painel.dashboard', ['tenant' => $tenantId])" name="Nextgest" class="px-2 dark:hidden" />
        <flux:brand :href="route('painel.dashboard', ['tenant' => $tenantId])" name="Nextgest" class="hidden px-2 dark:flex" />

        <flux:navlist variant="outline">
            <flux:navlist.item icon="home" :href="route('painel.dashboard', ['tenant' => $tenantId])" :current="request()->routeIs('painel.dashboard')" wire:navigate>
                Início
            </flux:navlist.item>

            <flux:navlist.group heading="Operação" expandable :expanded="true">
                <flux:navlist.item icon="calendar-days">Agendamentos</flux:navlist.item>
                <flux:navlist.item icon="scissors">Serviços</flux:navlist.item>
                <flux:navlist.item icon="users">Clientes</flux:navlist.item>
                <flux:navlist.item icon="shopping-bag">Vendas</flux:navlist.item>
            </flux:navlist.group>

            <flux:navlist.group heading="Gestão" expandable :expanded="true">
                <flux:navlist.item icon="identification">Equipe</flux:navlist.item>
                <flux:navlist.item icon="star">Clube</flux:navlist.item>
                <flux:navlist.item icon="rectangle-stack">Kanban</flux:navlist.item>
                <flux:navlist.item icon="cog-6-tooth">Configurações</flux:navlist.item>
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

    @fluxScripts
</body>
</html>
