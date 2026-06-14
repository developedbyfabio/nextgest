<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nextgest — agendamento sem atrito para o seu negócio</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="min-h-screen bg-white text-zinc-900 antialiased dark:bg-zinc-950 dark:text-white">
    {{-- Topo --}}
    <header class="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
        <div class="flex items-center gap-2">
            <flux:icon name="calendar-days" class="size-7 text-indigo-600 dark:text-indigo-400" />
            <span class="text-xl font-semibold tracking-tight">Nextgest</span>
        </div>
        <flux:button href="#contato" variant="ghost" size="sm">Contato</flux:button>
    </header>

    {{-- Hero --}}
    <main class="mx-auto max-w-6xl px-6">
        <section class="relative overflow-hidden rounded-3xl border border-zinc-200 bg-gradient-to-br from-indigo-50 to-white px-6 py-16 text-center dark:border-zinc-800 dark:from-indigo-950/40 dark:to-zinc-950 sm:px-12 sm:py-24">
            <div class="absolute -right-24 -top-24 size-96 rounded-full bg-indigo-500/10 blur-3xl"></div>
            <div class="relative mx-auto max-w-2xl">
                <flux:badge color="indigo" size="sm">SaaS de agendamento multi-estabelecimento</flux:badge>
                <h1 class="mt-5 text-4xl font-semibold leading-tight tracking-tight sm:text-5xl">
                    Agendamento sem atrito para o seu negócio
                </h1>
                <p class="mx-auto mt-4 max-w-xl text-lg text-zinc-600 dark:text-zinc-300">
                    Barbearias, salões e profissionais autônomos: agenda, equipe, clientes e
                    vendas em um só lugar — o cliente marca sozinho pelo celular.
                </p>
                <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                    <flux:button href="#contato" variant="primary" icon="sparkles">Quero conhecer</flux:button>
                    <flux:button :href="route('admin.login')" variant="outline">Acesso administrativo</flux:button>
                </div>
            </div>
        </section>

        {{-- Recursos --}}
        <section class="grid gap-6 py-16 sm:grid-cols-3">
            @php($recursos = [
                ['calendar-days', 'Agenda inteligente', 'Disponibilidade calculada por profissional, com bloqueios e sem choque de horário.'],
                ['device-phone-mobile', 'Portal do cliente', 'Seu cliente agenda em poucos toques, direto do navegador do celular.'],
                ['users', 'Equipe e permissões', 'Papéis por função (dono, gerente, recepção, profissional) e multiunidade.'],
            ])
            @foreach ($recursos as [$icone, $titulo, $texto])
                <flux:card class="flex flex-col gap-2">
                    <flux:icon :name="$icone" class="size-6 text-indigo-600 dark:text-indigo-400" />
                    <flux:heading size="lg">{{ $titulo }}</flux:heading>
                    <flux:text class="text-zinc-600 dark:text-zinc-300">{{ $texto }}</flux:text>
                </flux:card>
            @endforeach
        </section>

        {{-- CTA / contato --}}
        <section id="contato" class="mb-20 rounded-3xl bg-zinc-900 px-6 py-12 text-center text-white dark:bg-zinc-900 sm:px-12">
            <flux:heading size="xl" class="text-white">Vamos colocar sua agenda no piloto automático?</flux:heading>
            <flux:text class="mx-auto mt-2 max-w-xl text-zinc-300">
                Fale com a gente e comece a testar com o seu estabelecimento.
            </flux:text>
            <div class="mt-6">
                <flux:button href="mailto:contato@nextgest.com.br" variant="primary" icon="envelope">contato@nextgest.com.br</flux:button>
            </div>
        </section>
    </main>

    <footer class="border-t border-zinc-100 py-6 text-center text-sm text-zinc-400 dark:border-zinc-800">
        © {{ date('Y') }} Nextgest
    </footer>

    @fluxScripts
</body>
</html>
