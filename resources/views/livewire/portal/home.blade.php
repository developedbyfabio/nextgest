<div class="flex flex-col gap-6">
    @auth('cliente')
        @php($cardStyle = 'background-color: var(--cor-superficie); border-color: color-mix(in srgb, var(--cor-texto) 10%, transparent);')
        @php($statusInfo = [
            'pendente' => ['Pendente', 'amber'],
            'confirmado' => ['Confirmado', 'green'],
            'em_andamento' => ['Em andamento', 'blue'],
            'concluido' => ['Concluído', 'green'],
            'cancelado' => ['Cancelado', 'zinc'],
            'nao_compareceu' => ['Faltou', 'red'],
        ])

        {{-- Saudação + ação principal --}}
        <div class="flex flex-col gap-3">
            <div>
                <flux:heading size="lg">Olá, {{ \Illuminate\Support\Str::of($cliente->nome)->explode(' ')->first() }}</flux:heading>
                <flux:text class="mt-1" style="color: var(--cor-texto-suave);">Agende seu horário em poucos toques.</flux:text>
            </div>

            <flux:button :href="route('cliente.agendar', ['tenant' => tenant('id')])" variant="primary" icon="plus" class="w-full" wire:navigate>
                Novo agendamento
            </flux:button>
        </div>

        {{-- Próximos agendamentos --}}
        <section class="flex flex-col gap-3">
            <flux:heading size="sm">Próximos agendamentos</flux:heading>

            @forelse ($proximos as $agendamento)
                @php([$rotulo, $cor] = $statusInfo[$agendamento->status] ?? ['—', 'zinc'])
                <div class="flex flex-col gap-2 rounded-xl border p-4" style="{{ $cardStyle }}" wire:key="prox-{{ $agendamento->id }}">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <flux:text class="font-semibold capitalize">
                                {{ $agendamento->data_hora_inicio->translatedFormat('D, d/m · H:i') }}
                            </flux:text>
                            <flux:text class="text-sm" style="color: var(--cor-texto-suave);">
                                {{ $agendamento->itens->map(fn ($i) => $i->servico?->nome)->filter()->join(', ') ?: 'Serviço' }}
                            </flux:text>
                            @if ($agendamento->profissional)
                                <flux:text class="text-sm" style="color: var(--cor-texto-suave);">
                                    com {{ $agendamento->profissional->name }}
                                </flux:text>
                            @endif
                        </div>
                        <flux:badge :color="$cor" size="sm">{{ $rotulo }}</flux:badge>
                    </div>

                    @if ($podeCancelar($agendamento))
                        <flux:button wire:click="cancelar({{ $agendamento->id }})" wire:confirm="Cancelar este agendamento?" size="sm" variant="subtle" class="self-end">
                            Cancelar
                        </flux:button>
                    @endif
                </div>
            @empty
                <x-ng.empty icon="calendar-days" title="Nenhum agendamento futuro" text="Que tal marcar o próximo?" />
            @endforelse
        </section>

        {{-- Histórico --}}
        @if ($historico->isNotEmpty())
            <section class="flex flex-col gap-3">
                <flux:heading size="sm">Histórico</flux:heading>

                @foreach ($historico as $agendamento)
                    @php([$rotulo, $cor] = $statusInfo[$agendamento->status] ?? ['—', 'zinc'])
                    <div class="flex items-center justify-between gap-2 rounded-xl border px-4 py-3" style="{{ $cardStyle }} opacity: 0.9;" wire:key="hist-{{ $agendamento->id }}">
                        <div class="min-w-0">
                            <flux:text class="text-sm font-medium capitalize">
                                {{ $agendamento->data_hora_inicio->translatedFormat('d/m/Y · H:i') }}
                            </flux:text>
                            <flux:text class="truncate text-xs" style="color: var(--cor-texto-suave);">
                                {{ $agendamento->itens->map(fn ($i) => $i->servico?->nome)->filter()->join(', ') ?: 'Serviço' }}
                            </flux:text>
                        </div>
                        <flux:badge :color="$cor" size="sm">{{ $rotulo }}</flux:badge>
                    </div>
                @endforeach
            </section>
        @endif

        {{-- Meus dados --}}
        <section class="flex flex-col gap-3">
            <flux:heading size="sm">Meus dados</flux:heading>

            <div class="flex flex-col overflow-hidden rounded-xl border" style="{{ $cardStyle }}">
                @php($dados = [['user', 'Nome', $cliente->nome], ['phone', 'Telefone', $cliente->telefone], ['envelope', 'E-mail', $cliente->email]])
                @foreach ($dados as [$icone, $rotulo, $valor])
                    <div @class(['flex items-center gap-3 px-4 py-3', 'border-t' => ! $loop->first]) style="border-color: color-mix(in srgb, var(--cor-texto) 8%, transparent);">
                        <flux:icon :name="$icone" class="size-5 shrink-0" style="color: var(--cor-principal);" />
                        <div class="min-w-0">
                            <flux:text class="text-xs" style="color: var(--cor-texto-suave);">{{ $rotulo }}</flux:text>
                            <flux:text class="truncate font-medium">{{ $valor ?: '—' }}</flux:text>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Clube de assinatura (em breve) --}}
        <section
            class="flex items-center gap-4 rounded-xl border p-4"
            style="border-color: color-mix(in srgb, var(--cor-principal) 25%, transparent); background-color: color-mix(in srgb, var(--cor-principal) 8%, var(--cor-superficie));">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl" style="background-color: var(--cor-principal); color: var(--cor-sobre-principal);">
                <flux:icon name="star" variant="solid" class="size-6" />
            </span>
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <flux:heading size="sm">Clube de assinatura</flux:heading>
                    <flux:badge size="sm" color="zinc">Em breve</flux:badge>
                </div>
                <flux:text class="text-sm" style="color: var(--cor-texto-suave);">Planos e benefícios exclusivos para clientes fiéis.</flux:text>
            </div>
        </section>
    @else
        {{-- Visitante: identidade do estabelecimento + chamada para agendar --}}
        <div class="flex flex-col items-center gap-4 rounded-2xl border px-6 py-10 text-center"
            style="border-color: color-mix(in srgb, var(--cor-texto) 8%, transparent); background-color: color-mix(in srgb, var(--cor-principal) 6%, var(--cor-superficie));">
            <div class="flex size-16 items-center justify-center rounded-2xl shadow-sm" style="background-color: var(--cor-principal); color: var(--cor-sobre-principal);">
                <flux:icon name="scissors" class="size-8" />
            </div>
            <div>
                <flux:heading size="xl">{{ tenant('nome') }}</flux:heading>
                <flux:text class="mt-1" style="color: var(--cor-texto-suave);">{{ $descricao ?: 'Agende seu horário online, em poucos toques.' }}</flux:text>
            </div>
        </div>

        <div class="flex flex-col gap-3">
            <flux:text class="text-center text-sm font-medium" style="color: var(--cor-texto-suave);">Como funciona</flux:text>
            <div class="grid grid-cols-3 gap-2 text-center">
                @php($passos = [['user-plus', 'Crie sua conta'], ['scissors', 'Escolha o serviço'], ['calendar-days', 'Marque o horário']])
                @foreach ($passos as $i => [$icone, $texto])
                    <div class="flex flex-col items-center gap-1 rounded-xl border p-3" style="border-color: color-mix(in srgb, var(--cor-texto) 8%, transparent);">
                        <flux:icon :name="$icone" class="size-5" style="color: var(--cor-principal);" />
                        <span class="text-xs" style="color: var(--cor-texto-suave);">{{ $texto }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex flex-col gap-2">
            <flux:button :href="route('cliente.registrar', ['tenant' => tenant('id')])" variant="primary" icon="calendar-days" class="w-full" wire:navigate>
                Criar conta e agendar
            </flux:button>
            <flux:button :href="route('cliente.login', ['tenant' => tenant('id')])" variant="ghost" class="w-full" wire:navigate>
                Já tenho conta
            </flux:button>
        </div>
    @endauth
</div>
