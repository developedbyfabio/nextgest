<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? tenant('nome') }} · Nextgest</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased">
    @php($tenantId = tenant('id'))

    {{-- Portal do cliente: mobile-first. Conteúdo numa coluna estreita centralizada. --}}
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col bg-white shadow-sm">
        <header class="sticky top-0 z-10 flex items-center justify-between border-b border-zinc-100 bg-white/90 px-4 py-3 backdrop-blur">
            <a href="{{ route('tenant.home', ['tenant' => $tenantId]) }}" class="flex items-center gap-2" wire:navigate>
                <flux:icon name="calendar-days" class="size-6 text-zinc-900" />
                <span class="text-base font-semibold">{{ tenant('nome') }}</span>
            </a>

            @auth('cliente')
                <flux:dropdown position="bottom" align="end">
                    <flux:button variant="ghost" size="sm" icon="user-circle" />
                    <flux:menu>
                        <flux:menu.item icon="user">{{ auth('cliente')->user()->nome }}</flux:menu.item>
                        <flux:menu.separator />
                        <form method="POST" action="{{ route('cliente.logout', ['tenant' => $tenantId]) }}">
                            @csrf
                            <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" variant="danger" class="w-full">
                                Sair
                            </flux:menu.item>
                        </form>
                    </flux:menu>
                </flux:dropdown>
            @else
                <flux:button :href="route('cliente.login', ['tenant' => $tenantId])" size="sm" variant="primary" wire:navigate>
                    Entrar
                </flux:button>
            @endauth
        </header>

        <main class="flex-1 px-4 py-5">
            {{ $slot }}
        </main>

        <footer class="border-t border-zinc-100 px-4 py-3 text-center text-xs text-zinc-400">
            Powered by Nextgest
        </footer>
    </div>

    @fluxScripts
</body>
</html>
