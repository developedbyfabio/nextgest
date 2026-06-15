<div class="flex flex-col gap-6">
    <x-ng.page-header :title="$tenant->nome" :subtitle="'/'.$tenant->slug">
        <x-slot:actions>
            <flux:button :href="route('admin.tenants')" variant="ghost" icon="arrow-left" wire:navigate>Voltar</flux:button>
            <flux:button :href="route('tenant.home', ['tenant' => $tenant->id])" target="_blank" variant="ghost" icon="arrow-top-right-on-square">Abrir portal</flux:button>
            <flux:button wire:click="impersonatar" variant="primary" icon="lifebuoy">Entrar no painel (suporte)</flux:button>
        </x-slot:actions>
    </x-ng.page-header>

    @if (session('onboarding_sucesso'))
        <flux:callout variant="success" icon="check-circle">
            <flux:callout.heading>{{ session('onboarding_sucesso') }}</flux:callout.heading>
            <flux:callout.text>Banco provisionado, Dono criado e aparência aplicada. Use "Abrir portal" para conferir.</flux:callout.text>
        </flux:callout>
    @endif

    <flux:callout icon="lock-closed">
        <flux:callout.heading>Dados privados do estabelecimento</flux:callout.heading>
        <flux:callout.text>
            Clientes, equipe e agenda são isolados em cada tenant. Aqui você vê só um
            resumo de alto nível. Para inspecionar os dados, use "Entrar no painel".
        </flux:callout.text>
    </flux:callout>

    {{-- Status / metadados --}}
    <div class="flex flex-wrap items-center gap-3">
        @if ($tenant->ativo)
            <flux:badge color="green">Ativo</flux:badge>
        @else
            <flux:badge color="zinc">Inativo</flux:badge>
        @endif
        @if ($tenant->segmento)
            <flux:badge color="blue">{{ \App\Livewire\Admin\OnboardingEstabelecimento::SEGMENTOS[$tenant->segmento] ?? $tenant->segmento }}</flux:badge>
        @endif
        <flux:text class="text-sm text-zinc-500">Criado em {{ $tenant->created_at?->format('d/m/Y H:i') }}</flux:text>
    </div>

    {{-- Contagens --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        @php($cards = [
            ['Equipe', $resumo['equipe'], 'identification'],
            ['Profissionais', $resumo['profissionais'], 'scissors'],
            ['Clientes', $resumo['clientes'], 'users'],
            ['Serviços', $resumo['servicos'], 'rectangle-stack'],
            ['Agendamentos', $resumo['agendamentos'], 'calendar-days'],
        ])
        @foreach ($cards as [$rotulo, $valor, $icone])
            <flux:card class="flex flex-col gap-1">
                <flux:icon :name="$icone" class="size-5 text-indigo-600 dark:text-indigo-400" />
                <flux:text class="text-sm text-zinc-500">{{ $rotulo }}</flux:text>
                <flux:heading size="xl">{{ $valor }}</flux:heading>
            </flux:card>
        @endforeach
    </div>

    {{-- Donos --}}
    <div class="flex flex-col gap-2">
        <flux:heading size="lg">Donos</flux:heading>
        @forelse ($resumo['donos'] as $dono)
            <flux:card class="flex items-center justify-between">
                <div>
                    <flux:text class="font-medium">{{ $dono->name }}</flux:text>
                    <flux:text class="text-sm text-zinc-500">{{ $dono->email }}</flux:text>
                </div>
                <flux:badge color="indigo" size="sm">Dono</flux:badge>
            </flux:card>
        @empty
            <x-ng.empty icon="user" title="Sem dono" text="Crie um Dono na lista de estabelecimentos." />
        @endforelse
    </div>
</div>
