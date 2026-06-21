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
                <div class="ng-fade-in flex gap-3 rounded-xl border p-4 transition duration-150 ease-out hover:shadow-md" style="{{ $cardStyle }}" wire:key="prox-{{ $agendamento->id }}">
                    {{-- Faixa de data compacta --}}
                    <div class="flex shrink-0 flex-col items-center justify-center rounded-lg px-3 py-2 text-center" style="background-color: color-mix(in srgb, var(--cor-principal) 10%, var(--cor-superficie)); color: var(--cor-principal);">
                        <span class="text-lg font-bold leading-none">{{ $agendamento->data_hora_inicio->format('d') }}</span>
                        <span class="text-[0.65rem] font-medium uppercase">{{ $agendamento->data_hora_inicio->translatedFormat('M') }}</span>
                    </div>

                    <div class="flex min-w-0 flex-1 flex-col gap-2">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <flux:text class="font-semibold capitalize">
                                    {{ $agendamento->data_hora_inicio->translatedFormat('D · H:i') }}
                                </flux:text>
                                <flux:text class="text-sm" style="color: var(--cor-texto-suave);">
                                    {{ $agendamento->itens->map(fn ($i) => $i->servico?->nome)->filter()->join(', ') ?: 'Serviço' }}
                                </flux:text>
                                @if ($agendamento->profissional)
                                    <flux:text class="flex items-center gap-1 text-sm" style="color: var(--cor-texto-suave);">
                                        <flux:icon name="user" class="size-3.5" /> {{ $agendamento->profissional->name }}
                                    </flux:text>
                                @endif
                            </div>
                            <flux:badge :color="$cor" size="sm">{{ $rotulo }}</flux:badge>
                        </div>

                        @if ($podeCancelar($agendamento))
                            <flux:button wire:click="pedirCancelamento({{ $agendamento->id }})" size="sm" variant="subtle" class="self-end">
                                Cancelar
                            </flux:button>
                        @endif
                    </div>
                </div>
            @empty
                <x-ng.empty themed icon="calendar-days" title="Nenhum agendamento futuro" text="Que tal marcar o próximo?">
                    <flux:button :href="route('cliente.agendar', ['tenant' => tenant('id')])" size="sm" variant="primary" icon="plus" class="mt-2" wire:navigate>
                        Agendar agora
                    </flux:button>
                </x-ng.empty>
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

        {{-- Modal de confirmação de cancelamento (sem confirm nativo) --}}
        <flux:modal name="cancelar-agendamento" class="max-w-sm">
            <div class="flex flex-col gap-4">
                <div class="flex items-center gap-3">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-red-500/15">
                        <flux:icon name="exclamation-triangle" class="size-6" />
                    </span>
                    <div>
                        <flux:heading size="lg">Cancelar agendamento?</flux:heading>
                        <flux:text class="mt-1">Esta ação não pode ser desfeita.</flux:text>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">Voltar</flux:button>
                    </flux:modal.close>
                    @if ($cancelandoId)
                        <flux:button wire:click="cancelar({{ $cancelandoId }})" variant="danger" icon="x-mark">
                            <span wire:loading.remove wire:target="cancelar">Sim, cancelar</span>
                            <span wire:loading wire:target="cancelar">Cancelando…</span>
                        </flux:button>
                    @endif
                </div>
            </div>
        </flux:modal>
    @else
        {{-- Visitante: identidade do estabelecimento + chamada para agendar --}}
        <div class="ng-fade-in flex flex-col items-center gap-4 rounded-2xl border px-6 py-10 text-center"
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
                    <div class="flex flex-col items-center gap-2 rounded-xl border p-3" style="border-color: color-mix(in srgb, var(--cor-texto) 8%, transparent);">
                        <span class="flex size-9 items-center justify-center rounded-full text-sm font-bold" style="background-color: color-mix(in srgb, var(--cor-principal) 12%, transparent); color: var(--cor-principal);">{{ $i + 1 }}</span>
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
