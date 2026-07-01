@php($aparencia = \App\Support\Aparencia::doTenant())
@php($logoUrl = \App\Support\Aparencia::urlArquivo($aparencia['logo']))
@php($fundoUrl = \App\Support\Aparencia::urlArquivo($aparencia['fundo_imagem']))
<!DOCTYPE html>
{{-- font-size base no <html>: faz o "tamanho base" escalar a UI inteira (os
     utilitários do Tailwind são rem, relativos ao html). --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" style="font-size: {{ $aparencia['tamanho_base'] }};">
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
    {{-- Tipografia da marca: carrega a fonte (Google) escolhida pelo tenant, se houver. --}}
    {!! \App\Support\Aparencia::linkFonteGoogle($aparencia) !!}
    {{-- Favicon do tenant (D90) — ícone da aba; fallback pro padrão do Nextgest. --}}
    {!! \App\Support\Aparencia::linkFavicon($aparencia) !!}
</head>
<body
    class="min-h-screen antialiased"
    style="{{ \App\Support\Aparencia::cssVarsAcento($aparencia) }}; background-color: var(--cor-fundo); color: var(--cor-texto);@if ($fundoUrl) background-image: url('{{ $fundoUrl }}'); background-size: cover; background-position: center; background-attachment: fixed;@endif"
>
    @php($tenantId = tenant('id'))

    {{-- Portal do cliente: mobile-first. Coluna estreita centralizada.
         Com imagem de fundo, a coluna fica TRANSLÚCIDA (a foto aparece atrás de
         tudo, como na prévia) e os blocos com .ng-leitura ganham superfície de
         leitura (ng-com-fundo). Sem fundo, a coluna é a superfície sólida. --}}
    <div @class(['mx-auto flex min-h-screen w-full max-w-md flex-col shadow-sm', 'ng-com-fundo' => $fundoUrl])
        @style(['background-color: var(--cor-superficie)' => ! $fundoUrl])>
        <x-portal.cabecalho :nome="tenant('nome')" :logoUrl="$logoUrl" :aparencia="$aparencia" :href="route('tenant.home', ['tenant' => $tenantId])">
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
        </x-portal.cabecalho>

        <main class="flex-1 px-4 py-5">
            {{ $slot }}
        </main>

        <x-portal.rodape />
    </div>

    <flux:toast position="top center" />

    @fluxScripts
</body>
</html>
