<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Acesso' }} · Nextgest</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="min-h-screen bg-white text-zinc-900 antialiased dark:bg-zinc-950 dark:text-white">
    <div class="grid min-h-screen lg:grid-cols-2">
        {{-- Marca --}}
        <div class="relative hidden flex-col justify-between overflow-hidden bg-zinc-900 p-10 text-white lg:flex">
            <div class="absolute inset-0 bg-gradient-to-br from-indigo-600/30 via-zinc-900 to-zinc-900"></div>
            <div class="absolute -right-24 -top-24 size-96 rounded-full bg-indigo-500/20 blur-3xl"></div>

            <div class="relative flex items-center gap-2">
                <flux:icon name="calendar-days" class="size-7 text-indigo-400" />
                <span class="text-xl font-semibold tracking-tight">Nextgest</span>
            </div>

            <div class="relative">
                <h1 class="text-3xl font-semibold leading-tight">Agendamento sem atrito para o seu negócio.</h1>
                <p class="mt-3 max-w-md text-zinc-300">Agenda, equipe, clientes e vendas — tudo num só lugar, em qualquer tela.</p>
            </div>

            <div class="relative text-sm text-zinc-400">© {{ date('Y') }} Nextgest</div>
        </div>

        {{-- Formulário --}}
        <div class="flex flex-col items-center justify-center px-4 py-10 sm:px-8">
            <div class="mb-6 flex items-center gap-2 lg:hidden">
                <flux:icon name="calendar-days" class="size-7 text-indigo-500" />
                <span class="text-xl font-semibold tracking-tight">{{ $brand ?? 'Nextgest' }}</span>
            </div>

            <div class="w-full max-w-sm">
                {{ $slot }}
            </div>
        </div>
    </div>

    <flux:toast />

    @fluxScripts
</body>
</html>
