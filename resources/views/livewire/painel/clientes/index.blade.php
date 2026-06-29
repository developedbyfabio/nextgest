@php($alvos = 'busca,visitaFiltro,clubeFiltro,page')
@php($colspan = $clubeAtivo ? 5 : 4)
<div class="flex flex-col gap-6 p-6 lg:p-8">
    <x-ng.page-header title="Clientes" subtitle="Base de clientes do estabelecimento — última visita, contato e Clube" />

    {{-- Busca + filtros (server-side) --}}
    <div class="flex flex-wrap items-end gap-3">
        <flux:input wire:model.live.debounce.300ms="busca" icon="magnifying-glass" placeholder="Buscar por nome" class="min-w-52 flex-1" />
        <flux:select wire:model.live="visitaFiltro" label="Última visita" class="min-w-44">
            <flux:select.option value="todos">Todas</flux:select.option>
            <flux:select.option value="ate30">Até 30 dias</flux:select.option>
            <flux:select.option value="de31a90">31 a 90 dias</flux:select.option>
            <flux:select.option value="mais90">Mais de 90 dias</flux:select.option>
            <flux:select.option value="nunca">Nunca veio</flux:select.option>
        </flux:select>
        @if ($clubeAtivo)
            <flux:select wire:model.live="clubeFiltro" label="Clube" class="min-w-40">
                <flux:select.option value="todos">Todos</flux:select.option>
                <flux:select.option value="assinantes">Assinantes</flux:select.option>
                <flux:select.option value="normais">Sem Clube</flux:select.option>
            </flux:select>
        @endif
    </div>

    {{-- Loading (skeleton) --}}
    <div wire:loading.delay.flex wire:target="{{ $alvos }}" class="flex-col gap-2">
        @for ($i = 0; $i < 6; $i++)
            <div class="ng-skeleton-portal h-12 w-full"></div>
        @endfor
    </div>

    <div wire:loading.remove.delay wire:target="{{ $alvos }}" class="flex flex-col gap-4">
        @if ($clientes->isEmpty())
            <x-ng.empty themed icon="users"
                title="{{ $busca !== '' || $visitaFiltro !== 'todos' || $clubeFiltro !== 'todos' ? 'Nenhum cliente com esses filtros' : 'Nenhum cliente ainda' }}"
                text="{{ $busca !== '' || $visitaFiltro !== 'todos' || $clubeFiltro !== 'todos' ? 'Ajuste a busca ou os filtros para ver mais clientes.' : 'Os clientes aparecem aqui conforme se cadastram ou são agendados.' }}" />
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Cliente</flux:table.column>
                    <flux:table.column>Telefone</flux:table.column>
                    <flux:table.column>Última visita</flux:table.column>
                    @if ($clubeAtivo)<flux:table.column>Clube</flux:table.column>@endif
                    <flux:table.column />
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($clientes as $cliente)
                        @php($uv = $cliente->ultima_visita ? \Illuminate\Support\Carbon::parse($cliente->ultima_visita) : null)
                        @php($dias = $uv ? (int) $uv->copy()->startOfDay()->diffInDays(\Illuminate\Support\Carbon::today()) : null)
                        @php($rotuloVisita = $uv === null ? 'Nunca veio' : ($dias === 0 ? 'Hoje' : ($dias === 1 ? 'Ontem' : 'há '.$dias.' dias')))
                        @php($corVisita = $uv === null ? 'zinc' : ($dias <= 30 ? 'green' : ($dias <= 90 ? 'amber' : 'red')))
                        @php($aberto = $clienteAbertoId === $cliente->id)
                        <flux:table.row :key="'cli-'.$cliente->id">
                            <flux:table.cell variant="strong">
                                <div class="flex flex-col">
                                    <span>{{ $cliente->nome }}</span>
                                    @if ($cliente->email)
                                        <span class="text-xs font-normal text-zinc-500">{{ $cliente->email }}</span>
                                    @endif
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>{{ $cliente->telefone ?: '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex flex-col gap-0.5">
                                    <flux:badge size="sm" :color="$corVisita">{{ $rotuloVisita }}</flux:badge>
                                    @if ($uv)
                                        <span class="text-xs text-zinc-400">{{ $uv->format('d/m/Y') }}</span>
                                    @endif
                                </div>
                            </flux:table.cell>
                            @if ($clubeAtivo)
                                <flux:table.cell>
                                    @if ((bool) $cliente->assinante)
                                        <flux:badge size="sm" color="purple" icon="ticket">Assinante</flux:badge>
                                    @else
                                        <span class="text-xs text-zinc-400">—</span>
                                    @endif
                                </flux:table.cell>
                            @endif
                            <flux:table.cell class="text-right">
                                <div class="flex flex-wrap items-center justify-end gap-1">
                                    <flux:button wire:click="abrirEditar({{ $cliente->id }})" size="sm" variant="ghost" icon="pencil-square">Editar</flux:button>
                                    @if ($whatsappAtivo)
                                        <flux:button wire:click="abrirWhatsapp({{ $cliente->id }})" size="sm" variant="ghost" icon="chat-bubble-left-right">WhatsApp</flux:button>
                                    @endif
                                    <flux:button wire:click="alternarDetalhe({{ $cliente->id }})" size="sm" variant="ghost"
                                        :icon="$aberto ? 'chevron-up' : 'chevron-down'">{{ $aberto ? 'Fechar' : 'Ver' }}</flux:button>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>

                        {{-- Detalhe (só leitura): últimos agendamentos do cliente aberto. --}}
                        @if ($aberto)
                            <flux:table.row :key="'det-'.$cliente->id">
                                <flux:table.cell colspan="{{ $colspan }}" class="bg-zinc-50 dark:bg-zinc-800/40">
                                    <div class="flex flex-col gap-3 py-2">
                                        <flux:heading size="sm">Últimos agendamentos</flux:heading>
                                        @forelse ($detalhe ?? [] as $ag)
                                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                                                <span class="w-32 shrink-0 tabular-nums text-zinc-500">{{ $ag['data']->format('d/m/Y H:i') }}</span>
                                                <span class="min-w-40 flex-1 font-medium">{{ $ag['servicos'] ?: 'Serviço não informado' }}</span>
                                                <span class="text-zinc-500">{{ $ag['profissional'] ?? '—' }}</span>
                                                <flux:badge size="sm" :color="$statusCor[$ag['status']] ?? 'zinc'">{{ $statusLabel[$ag['status']] ?? $ag['status'] }}</flux:badge>
                                            </div>
                                        @empty
                                            <flux:text class="text-sm text-zinc-500">Este cliente ainda não tem agendamentos.</flux:text>
                                        @endforelse
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endif
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <div>{{ $clientes->links() }}</div>
        @endif
    </div>

    {{-- Modal: editar cliente (gated por ver_clientes; valida nome/email/telefone BR). --}}
    <flux:modal name="editar-cliente" class="md:w-96">
        <form wire:submit="salvarEditar" class="flex flex-col gap-4">
            <flux:heading size="lg">Editar cliente</flux:heading>
            <flux:input wire:model="editNome" label="Nome" required />
            <flux:input wire:model="editEmail" type="email" label="E-mail (opcional)" placeholder="email@exemplo.com" />
            <flux:input wire:model="editTelefone" label="Telefone (WhatsApp)" placeholder="(DDD) 9 0000-0000" required />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" icon="check">Salvar</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal: WhatsApp avulso (só com o recurso ligado). O envio passa pelos freios
         anti-ban (EnvioAvulso); opt-out exige confirmação (D65). --}}
    @if ($whatsappAtivo)
        <flux:modal name="whatsapp-cliente" class="md:w-[30rem]">
            <div class="flex flex-col gap-4">
                <div>
                    <flux:heading size="lg">Enviar WhatsApp</flux:heading>
                    <flux:subheading>Para {{ $waNome }}</flux:subheading>
                </div>

                @unless ($waConectado)
                    <flux:callout variant="warning" icon="exclamation-triangle">
                        O WhatsApp pode não estar conectado. Se o envio falhar, conecte na aba WhatsApp.
                    </flux:callout>
                @endunless

                @if ($waOptout)
                    <flux:callout variant="warning" icon="bell-slash">
                        Este cliente optou por <strong>não</strong> receber mensagens. Ao enviar, será pedida uma confirmação.
                    </flux:callout>
                @endif

                <flux:textarea wire:model="msgTexto" label="Mensagem" rows="4"
                    placeholder="Escreva a mensagem que será enviada ao cliente..." />

                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                    <flux:button wire:click="tentarEnviar" variant="primary" icon="paper-airplane">Enviar</flux:button>
                </div>
            </div>
        </flux:modal>

        {{-- Confirmação D65 para enviar a cliente em opt-out. --}}
        <x-ng.confirmar name="confirmar-optout-wa" tom="amber" icone="bell-slash"
            titulo="Cliente em opt-out"
            texto="Este cliente optou por não receber mensagens. Confirmar o envio mesmo assim?">
            <flux:button wire:click="confirmarEnvioOptout" variant="primary" icon="paper-airplane">Enviar mesmo assim</flux:button>
        </x-ng.confirmar>
    @endif
</div>
