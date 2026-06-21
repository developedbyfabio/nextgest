@php($temTenant = tenancy()->initialized)
@php($aparencia = $temTenant ? \App\Support\Aparencia::doTenant() : null)
<!DOCTYPE html>
{{-- No tenant, aplica o tamanho base (rem) da marca no <html>. --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"@if ($temTenant) style="font-size: {{ $aparencia['tamanho_base'] }};"@endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Tenant: título do estabelecimento; central (admin): marca Nextgest. --}}
    <title>{{ $title ?? 'Acesso' }} · {{ $temTenant ? tenant('nome') : 'Nextgest' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Etapa D: respeita o MODO claro/escuro/sistema (Flux), tenant ou central.
         No tenant, a marca entra como acento + logo + tipografia; superfícies pelos
         tokens de claro/escuro. --}}
    @fluxAppearance
    {{-- Tipografia da marca: no tenant, carrega a fonte (Google) escolhida, se houver. --}}
    @if ($temTenant){!! \App\Support\Aparencia::linkFonteGoogle($aparencia) !!}@endif
</head>

@php($logoUrl = $temTenant ? \App\Support\Aparencia::urlArquivo($aparencia['logo']) : null)
@php($marca = $temTenant ? tenant('nome') : ($brand ?? 'Nextgest'))

<body
    class="min-h-screen antialiased"
    style="background-color: var(--cor-fundo); color: var(--cor-texto);@if ($temTenant) {{ \App\Support\Aparencia::cssVarsAcento($aparencia) }}@endif"
>
    <div class="grid min-h-screen lg:grid-cols-2">
        {{-- Painel de marca (oculto no mobile) --}}
        @if ($temTenant)
            <div class="relative hidden flex-col justify-between overflow-hidden p-10 lg:flex"
                style="background-color: var(--cor-principal); color: var(--cor-sobre-principal);">
                <div class="absolute -right-24 -top-24 size-96 rounded-full opacity-20"
                    style="background-color: var(--cor-sobre-principal); filter: blur(64px);"></div>

                <div class="relative flex items-center gap-3">
                    @if ($logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ $marca }}" class="size-10 rounded-lg bg-white/10 object-contain p-1" />
                    @else
                        <flux:icon name="calendar-days" class="size-7" />
                    @endif
                    <span class="text-xl font-semibold tracking-tight">{{ $marca }}</span>
                </div>

                <div class="relative">
                    <h1 class="text-3xl font-semibold leading-tight">Seu horário, do seu jeito.</h1>
                    <p class="mt-3 max-w-md" style="color: color-mix(in srgb, var(--cor-sobre-principal) 80%, transparent);">
                        Agende em poucos toques e acompanhe tudo num só lugar.
                    </p>
                </div>

                <div class="relative text-sm" style="color: color-mix(in srgb, var(--cor-sobre-principal) 70%, transparent);">
                    {{ $marca }}
                </div>
            </div>
        @else
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
        @endif

        {{-- Formulário --}}
        <div class="flex flex-col items-center justify-center px-4 py-10 sm:px-8">
            <div class="mb-6 flex items-center gap-2 lg:hidden">
                @if ($temTenant && $logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $marca }}" class="size-8 rounded-lg object-contain" />
                @elseif ($temTenant)
                    <flux:icon name="calendar-days" class="size-7" style="color: var(--cor-principal);" />
                @else
                    <flux:icon name="calendar-days" class="size-7 text-indigo-500" />
                @endif
                <span class="text-xl font-semibold tracking-tight">{{ $marca }}</span>
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
