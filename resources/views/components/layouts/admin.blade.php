<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Admin' }} · Nextgest</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="min-h-screen bg-white dark:bg-zinc-800">
    <flux:header sticky class="border-b border-zinc-200 bg-zinc-50 px-6 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:brand :href="route('admin.dashboard')" name="Nextgest Admin" />
        <flux:spacer />
        <flux:dropdown position="bottom" align="end">
            <flux:profile :name="auth('admin')->user()?->name" :initials="\Illuminate\Support\Str::of(auth('admin')->user()?->name)->explode(' ')->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('')" />
            <flux:menu>
                <flux:menu.item icon="user">{{ auth('admin')->user()?->email }}</flux:menu.item>
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
