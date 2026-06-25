<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Admin' }} · Nextgest</title>
    <link rel="icon" type="image/png" href="{{ asset('nextgest-logo.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
{{-- Identidade da landing (Fase 0): paleta de marca (violeta→azul), slate p/ texto e
     fundo escuro #0B1120; dark/light pelo MESMO mecanismo da landing ($flux.appearance,
     persistido pelo Flux). --}}
<body class="min-h-screen bg-white text-slate-900 antialiased dark:bg-[#0B1120] dark:text-slate-100">
    {{-- Header glassmorphism (mesmo padrão da landing). --}}
    <flux:header sticky class="border-b border-slate-200/70 bg-white/80 px-4 backdrop-blur-md dark:border-slate-800/70 dark:bg-[#0B1120]/80 sm:px-6">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5" wire:navigate aria-label="Nextgest Admin — início">
            <img src="{{ asset('nextgest-logo.png') }}" alt="Nextgest" class="size-8 shrink-0 object-contain" />
            <span class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">Nextgest</span>
            <span class="rounded-md bg-gradient-to-r from-violet-600 to-blue-600 px-1.5 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-white">Admin</span>
        </a>

        <flux:navbar class="ms-6 max-lg:hidden">
            <flux:navbar.item :href="route('admin.dashboard')" :current="request()->routeIs('admin.dashboard')" wire:navigate>Início</flux:navbar.item>
            <flux:navbar.item :href="route('admin.tenants')" :current="request()->routeIs('admin.tenants')" wire:navigate>Estabelecimentos</flux:navbar.item>
        </flux:navbar>

        <flux:spacer />

        {{-- Mesmo alternador de tema da landing (sol/lua, $flux.appearance). --}}
        <x-landing.tema-toggle />

        <flux:dropdown position="bottom" align="end">
            <flux:profile :name="auth('admin')->user()?->name" :initials="\Illuminate\Support\Str::of(auth('admin')->user()?->name)->explode(' ')->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('')" />
            <flux:menu>
                <flux:menu.item icon="user">{{ auth('admin')->user()?->email }}</flux:menu.item>

                <flux:menu.separator />

                {{-- x-model DIRETO em $flux.appearance (mesmo estado do toggle do header). --}}
                <flux:menu.radio.group x-data x-model="$flux.appearance" heading="Tema">
                    <flux:menu.radio value="light" icon="sun">Claro</flux:menu.radio>
                    <flux:menu.radio value="dark" icon="moon">Escuro</flux:menu.radio>
                    <flux:menu.radio value="system" icon="computer-desktop">Sistema</flux:menu.radio>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" variant="danger" class="w-full">
                        Sair
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    <flux:main container>
        {{ $slot }}
    </flux:main>

    @fluxScripts
</body>
</html>
