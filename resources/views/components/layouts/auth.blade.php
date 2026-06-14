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
<body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased">
    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
        <div class="mb-6 flex items-center gap-2">
            <flux:icon name="calendar-days" class="size-7 text-zinc-900" />
            <span class="text-xl font-semibold tracking-tight">{{ $brand ?? 'Nextgest' }}</span>
        </div>

        <div class="w-full max-w-sm rounded-xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8">
            {{ $slot }}
        </div>

        @isset($below)
            <div class="mt-6 w-full max-w-sm text-center text-sm text-zinc-500">
                {{ $below }}
            </div>
        @endisset
    </div>

    @fluxScripts
</body>
</html>
