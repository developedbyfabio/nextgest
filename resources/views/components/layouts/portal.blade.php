<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? tenant('nome') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Etapa D: o portal respeita o MODO claro/escuro/sistema (Flux). A marca do
         estabelecimento entra como ACENTO + logo + tipografia (constante nos dois
         modos); as superfícies vêm dos tokens de claro/escuro. --}}
    @fluxAppearance
</head>
@php($aparencia = \App\Support\Aparencia::doTenant())
@php($logoUrl = \App\Support\Aparencia::urlArquivo($aparencia['logo']))
@php($fundoUrl = \App\Support\Aparencia::urlArquivo($aparencia['fundo_imagem']))
<body
    class="min-h-screen antialiased"
    style="{{ \App\Support\Aparencia::cssVarsAcento($aparencia) }}; background-color: var(--cor-fundo); color: var(--cor-texto);@if ($fundoUrl) background-image: url('{{ $fundoUrl }}'); background-size: cover; background-position: center; background-attachment: fixed;@endif"
>
    @php($tenantId = tenant('id'))

    {{-- Portal do cliente: mobile-first. Coluna estreita centralizada. --}}
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col shadow-sm" style="background-color: var(--cor-superficie);">
        <header class="sticky top-0 z-10 flex items-center justify-between border-b px-4 py-3 backdrop-blur"
            style="background-color: color-mix(in srgb, var(--cor-superficie) 90%, transparent); border-color: color-mix(in srgb, var(--cor-texto) 10%, transparent);">
            <a href="{{ route('tenant.home', ['tenant' => $tenantId]) }}" class="flex items-center gap-2" wire:navigate>
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ tenant('nome') }}" class="size-8 rounded object-contain" />
                @else
                    <flux:icon name="calendar-days" class="size-6" style="color: var(--cor-principal);" />
                @endif
                <span class="text-base font-semibold">{{ tenant('nome') }}</span>
            </a>

            <div class="flex items-center gap-1">
                <x-ng.seletor-tema />

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
            </div>
        </header>

        <main class="flex-1 px-4 py-5">
            {{ $slot }}
        </main>

        <footer class="border-t px-4 py-3 text-center text-xs"
            style="border-color: color-mix(in srgb, var(--cor-texto) 10%, transparent); color: var(--cor-texto-suave);">
            Powered by Nextgest
        </footer>
    </div>

    <flux:toast position="top center" />

    @fluxScripts
</body>
</html>
