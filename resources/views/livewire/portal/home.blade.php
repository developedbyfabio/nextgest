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
                    <div class="flex flex-col gap-2 rounded-xl border px-4 py-3" style="{{ $cardStyle }} opacity: 0.95;" wire:key="hist-{{ $agendamento->id }}">
                        <div class="flex items-center justify-between gap-2">
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

                        {{-- Avaliação (D51): só para atendimento concluído. Avaliado →
                             mostra a nota (read-only); não avaliado → botão "Avaliar"
                             (abre o MESMO modal do popup). --}}
                        @if ($agendamento->status === 'concluido')
                            <div class="border-t pt-2" style="border-color: color-mix(in srgb, var(--cor-texto) 8%, transparent);">
                                @if ($agendamento->avaliacao)
                                    <div class="flex flex-col gap-1">
                                        <x-portal.estrelas :nota="$agendamento->avaliacao->nota" />
                                        @if ($agendamento->avaliacao->comentario)
                                            <flux:text class="text-xs italic" style="color: var(--cor-texto-suave);">“{{ $agendamento->avaliacao->comentario }}”</flux:text>
                                        @endif
                                    </div>
                                @else
                                    <flux:button wire:click="abrirAvaliacao({{ $agendamento->id }})" size="sm" variant="subtle" icon="star" class="self-start">
                                        Avaliar atendimento
                                    </flux:button>
                                @endif
                            </div>
                        @endif
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

        {{-- Modal de AVALIAÇÃO (D51) — usado tanto pelo POPUP (abre no load) quanto
             pelo botão "Avaliar" do histórico. Mesmo modal/ação para os dois. --}}
        <flux:modal wire:model.self="mostrarAvaliacao" class="max-w-sm">
            <div class="flex flex-col gap-4">
                <div>
                    <flux:heading size="lg">Como foi seu atendimento?</flux:heading>
                    @if ($avaliando)
                        <flux:text class="mt-1 text-sm" style="color: var(--cor-texto-suave);">
                            {{ $avaliando->itens->map(fn ($i) => $i->servico?->nome)->filter()->join(', ') ?: 'Serviço' }}
                            · {{ $avaliando->data_hora_inicio->translatedFormat('d/m/Y') }}
                            @if ($avaliando->profissional) · {{ $avaliando->profissional->name }} @endif
                        </flux:text>
                    @endif
                </div>

                {{-- Estrelas clicáveis (preenche até a clicada/hover; acessível). --}}
                <div x-data="{ hover: 0 }" class="flex items-center justify-center gap-1.5" role="radiogroup" aria-label="Sua nota de 1 a 5">
                    @for ($i = 1; $i <= 5; $i++)
                        <button type="button" wire:click="$set('nota', {{ $i }})"
                            @mouseenter="hover = {{ $i }}" @mouseleave="hover = 0"
                            role="radio" :aria-checked="$wire.nota === {{ $i }}" aria-label="{{ $i }} de 5"
                            class="rounded-md p-1 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-accent)]">
                            <svg viewBox="0 0 24 24" fill="currentColor" class="size-10 transition"
                                :class="(hover || $wire.nota) >= {{ $i }} ? 'text-amber-400' : 'text-zinc-300 dark:text-zinc-600'">
                                <path d="M11.48 3.5a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.563.563 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .32-.988l5.519-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z"/>
                            </svg>
                        </button>
                    @endfor
                </div>
                <flux:error name="nota" />

                <flux:textarea wire:model="comentario" label="Comentário (opcional)" rows="3" placeholder="Conte como foi o atendimento…" />

                <div class="flex justify-end gap-2">
                    <flux:button wire:click="ignorarAvaliacao" variant="ghost">Ignorar</flux:button>
                    <flux:button wire:click="salvarAvaliacao" variant="primary" icon="star" :disabled="$nota === null">
                        <span wire:loading.remove wire:target="salvarAvaliacao">Avaliar</span>
                        <span wire:loading wire:target="salvarAvaliacao">Salvando…</span>
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @else
        {{-- Visitante: usa o MESMO componente da prévia (tela 1 do carrossel):
             capa com imagem de cabeçalho + passos + chamadas. O fundo entra pelo
             layout do portal; a legibilidade sobre o fundo vem do .ng-leitura. --}}
        @php($ap = \App\Support\Aparencia::doTenant())
        @php($headerUrl = \App\Support\Aparencia::urlArquivo($ap['header_imagem']))
        <x-portal.tela-inicio
            :nome="tenant('nome')"
            :descricao="$descricao"
            :header-url="$headerUrl"
            :aparencia="$ap"
            :logo-url="\App\Support\Aparencia::urlArquivo($ap['logo'])"
            :registrar-href="route('cliente.registrar', ['tenant' => tenant('id')])"
            :login-href="route('cliente.login', ['tenant' => tenant('id')])"
        />
    @endauth
</div>
