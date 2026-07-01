@php($aparencia = \App\Support\Aparencia::doTenant())
<!DOCTYPE html>
{{-- Layout fino para a AUTENTICAÇÃO do portal do cliente (login/registro, guard
     `cliente`). Diferente do `auth.blade.php` (genérico, 2 colunas próprias, usado
     por admin/painel/staff): aqui o corpo é só um container de tela cheia; quem
     monta o layout responsivo (2 colunas + fundo) é o componente x-portal.auth,
     a MESMA fonte de verdade usada na prévia. --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" style="font-size: {{ $aparencia['tamanho_base'] }};">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Acesso' }} · {{ tenant('nome') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Respeita o MODO claro/escuro/sistema (Flux). A marca entra como acento +
         logo + tipografia; as superfícies vêm dos tokens de claro/escuro. --}}
    @fluxAppearance
    {{-- Tipografia da marca: carrega a fonte (Google) escolhida pelo tenant, se houver. --}}
    {!! \App\Support\Aparencia::linkFonteGoogle($aparencia) !!}
    {{-- Favicon do tenant (D90) — ícone da aba; fallback pro padrão do Nextgest. --}}
    {!! \App\Support\Aparencia::linkFavicon($aparencia) !!}
</head>
<body
    class="flex min-h-screen flex-col antialiased"
    style="{{ \App\Support\Aparencia::cssVarsAcento($aparencia) }}; background-color: var(--cor-fundo); color: var(--cor-texto);"
>
    {{ $slot }}

    {{-- Rodapé com os links legais do tenant (D93) — mesmo partial da home. --}}
    <x-portal.rodape />

    <flux:toast />

    @fluxScripts
</body>
</html>
