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

    @if (session('reset_2fa_ok'))
        <flux:callout variant="success" icon="shield-check">
            <flux:callout.heading>{{ session('reset_2fa_ok') }}</flux:callout.heading>
        </flux:callout>
    @endif

    @if (session('reset_2fa_erro'))
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.heading>{{ session('reset_2fa_erro') }}</flux:callout.heading>
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

    {{-- Plano (D55): nome que dirige os recursos. Trocar reaplica o padrão do plano. --}}
    <div class="flex flex-col gap-3">
        <div>
            <flux:heading size="lg">Plano</flux:heading>
            <flux:subheading>
                @if ($plano)
                    Plano atual: <strong>{{ $planos[$plano]['nome'] ?? $plano }}</strong>. Trocar redefine os recursos para o padrão do plano.
                @else
                    Plano: <strong>não definido</strong> (recursos personalizados). Escolha um plano para padronizar os recursos — nada muda até aplicar.
                @endif
            </flux:subheading>
        </div>

        <flux:card class="flex flex-col gap-4">
            <div class="grid gap-4 sm:grid-cols-3">
                @foreach ($planos as $chave => $p)
                    <button type="button" wire:click="$set('plano', '{{ $chave }}')"
                        @class([
                            'ng-card-interactive flex flex-col gap-3 p-4 text-left',
                            'ring-2 ring-indigo-500' => $plano === $chave,
                        ])>
                        <div class="flex items-center justify-between gap-2">
                            <flux:heading size="sm">{{ $p['nome'] }}</flux:heading>
                            @if ($plano === $chave)
                                <flux:icon name="check-circle" class="size-5 shrink-0 text-indigo-500" />
                            @endif
                        </div>
                        <div class="text-xl font-bold tracking-tight">
                            R$ {{ number_format($p['preco_mes'], 2, ',', '.') }}<span class="text-sm font-normal text-zinc-500">/mês</span>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            @forelse ($p['recursos'] as $slug)
                                <flux:badge color="green" size="sm">{{ \App\Enums\Recurso::from($slug)->rotulo() }}</flux:badge>
                            @empty
                                <flux:badge color="zinc" size="sm">Sem módulos extras</flux:badge>
                            @endforelse
                        </div>
                    </button>
                @endforeach
            </div>

            <flux:callout icon="exclamation-triangle">
                <flux:callout.text>
                    Aplicar um plano <strong>redefine</strong> os recursos para o padrão do plano. Rebaixar
                    apenas esconde o acesso aos módulos retirados — os dados (ex.: clube) permanecem no estabelecimento.
                </flux:callout.text>
            </flux:callout>

            <flux:error name="plano" />

            <div class="flex justify-end border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:button wire:click="trocarPlano" wire:confirm="Aplicar este plano? Os recursos serão redefinidos para o padrão do plano." variant="primary" icon="check">Aplicar plano</flux:button>
            </div>
        </flux:card>
    </div>

    {{-- Ajuste fino de recursos (módulos à la carte) — flag no banco central, por
         estabelecimento. Independente do plano: aqui dá pra ligar/desligar um módulo
         pontual. Atenção: TROCAR O PLANO redefine os recursos para o padrão do plano. --}}
    <div class="flex flex-col gap-3">
        <div>
            <flux:heading size="lg">Ajuste fino de recursos</flux:heading>
            <flux:subheading>Ligue ou desligue módulos pontualmente. Trocar o plano acima redefine os recursos para o padrão do plano.</flux:subheading>
        </div>

        <flux:card class="flex flex-col gap-4">
            @foreach (\App\Enums\Recurso::cases() as $recurso)
                <flux:switch
                    wire:model="recursos.{{ $recurso->value }}"
                    :label="$recurso->rotulo()"
                    :description="$recurso->descricao()"
                />
            @endforeach

            <div class="flex justify-end border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:button wire:click="salvarRecursos" variant="primary" icon="check">Salvar recursos</flux:button>
            </div>
        </flux:card>
    </div>

    {{-- Donos --}}
    <div class="flex flex-col gap-2">
        <flux:heading size="lg">Donos</flux:heading>
        @forelse ($resumo['donos'] as $dono)
            <flux:card class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <flux:text class="font-medium">{{ $dono->name }}</flux:text>
                    <flux:text class="text-sm text-zinc-500">{{ $dono->email }}</flux:text>
                </div>
                <div class="flex items-center gap-2">
                    <flux:badge color="indigo" size="sm">Dono</flux:badge>
                    @if ($dono->two_factor_confirmed_at)
                        <flux:badge color="green" size="sm" icon="shield-check">2FA ativo</flux:badge>
                        <flux:button
                            size="xs"
                            variant="subtle"
                            icon="shield-exclamation"
                            wire:click="confirmarReset({{ $dono->id }})"
                        >
                            Resetar 2FA
                        </flux:button>
                    @else
                        <flux:badge color="zinc" size="sm">Sem 2FA</flux:badge>
                    @endif
                </div>
            </flux:card>
        @empty
            <x-ng.empty icon="user" title="Sem dono" text="Crie um Dono na lista de estabelecimentos." />
        @endforelse
    </div>

    {{-- Confirmação do RESET de 2FA (último recurso). Modal, não wire:confirm (D27). --}}
    <flux:modal name="resetar-2fa" class="md:w-96">
        <div class="flex flex-col gap-4">
            <div>
                <flux:heading size="lg">Resetar 2FA do Dono?</flux:heading>
                <flux:subheading>
                    Desativa a autenticação em duas etapas deste Dono. Use só quando ele perdeu
                    o app E os códigos de recuperação. Ele volta a entrar só com a senha e pode
                    reativar depois.
                </flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button wire:click="resetar2fa" variant="danger">Resetar 2FA</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
