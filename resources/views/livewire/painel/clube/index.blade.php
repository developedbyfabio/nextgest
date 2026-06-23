<div class="flex flex-col gap-6 p-6 lg:p-8">
    {{-- Cabeçalho --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" style="color: var(--cor-texto);">Clube de Assinatura</flux:heading>
            <flux:subheading style="color: var(--cor-texto-suave);">Planos, assinantes e indicadores de retenção.</flux:subheading>
        </div>
    </div>

    {{-- Cobrança recorrente pendente de gateway (Fase A) --}}
    <flux:callout icon="information-circle">
        <flux:callout.heading>Cobrança automática pendente de integração</flux:callout.heading>
        <flux:callout.text>
            A mensalidade ainda não é cobrada automaticamente (depende do gateway recorrente / publicação).
            Por enquanto o <strong>status do assinante é manual</strong>. Quando a integração entrar, o status passa a ser atualizado sozinho.
        </flux:callout.text>
    </flux:callout>

    {{-- Abas --}}
    <flux:navbar>
        @foreach (['visao' => 'Visão geral', 'planos' => 'Planos', 'assinantes' => 'Assinantes', 'relatorios' => 'Relatórios'] as $chave => $rotulo)
            <flux:navbar.item :current="$aba === $chave" wire:click="setAba('{{ $chave }}')" class="cursor-pointer">{{ $rotulo }}</flux:navbar.item>
        @endforeach
    </flux:navbar>

    @if ($erro)
        <div class="ng-surface flex flex-col items-center gap-3 px-6 py-12 text-center">
            <span class="flex size-12 items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-red-500/15">
                <flux:icon name="exclamation-triangle" class="size-6" />
            </span>
            <flux:heading size="sm" style="color: var(--cor-texto);">Não foi possível carregar o Clube</flux:heading>
            <flux:button wire:click="$refresh" variant="primary" icon="arrow-path" size="sm">Tentar de novo</flux:button>
        </div>
    @else
        {{-- ========================= VISÃO GERAL ========================= --}}
        @if ($aba === 'visao')
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-ng.indicador titulo="Assinantes ativos" :valor="$ativos" icone="user-group" />
                <x-ng.indicador titulo="Novos no mês" :valor="$novosMes" icone="arrow-trending-up" />
                <x-ng.indicador titulo="Cancelamentos no mês" :valor="$canceladosMes" sub="churn do mês" icone="arrow-trending-down" />
                <x-ng.indicador titulo="Inadimplentes" :valor="$inadimplentes?->total() ?? 0" sub="ver lista p/ cobrança" icone="exclamation-triangle" />
            </div>

            {{-- Evolução (entradas × saídas por mês) --}}
            <div class="ng-surface flex flex-col gap-3 p-5">
                <flux:heading size="lg" style="color: var(--cor-texto);">Evolução das assinaturas</flux:heading>
                @if ($evolucao->sum(fn ($m) => $m['entradas'] + $m['saidas']) === 0)
                    <x-ng.empty icon="chart-bar" title="Sem movimento ainda" text="Quando houver adesões e cancelamentos, a evolução aparece aqui." />
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left" style="color: var(--cor-texto-suave);">
                                    <th class="py-2 pr-4 font-medium">Mês</th>
                                    <th class="py-2 pr-4 font-medium tabular-nums">Entradas</th>
                                    <th class="py-2 pr-4 font-medium tabular-nums">Saídas</th>
                                    <th class="py-2 pr-4 font-medium tabular-nums">Saldo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($evolucao as $m)
                                    <tr style="border-top: 1px solid color-mix(in srgb, var(--cor-texto) 10%, transparent); color: var(--cor-texto);">
                                        <td class="py-2 pr-4">{{ $m['mes'] }}</td>
                                        <td class="py-2 pr-4 tabular-nums text-green-600 dark:text-green-400">+{{ $m['entradas'] }}</td>
                                        <td class="py-2 pr-4 tabular-nums text-amber-600 dark:text-amber-400">−{{ $m['saidas'] }}</td>
                                        <td class="py-2 pr-4 tabular-nums font-semibold">{{ $m['saldo'] >= 0 ? '+' : '' }}{{ $m['saldo'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Inadimplentes (lista de cobrança) --}}
            <div class="ng-surface flex flex-col gap-3 p-5">
                <flux:heading size="lg" style="color: var(--cor-texto);">Inadimplentes ({{ $inadimplentes?->total() ?? 0 }})</flux:heading>
                @if (($inadimplentes?->total() ?? 0) === 0)
                    <x-ng.empty icon="check-circle" title="Ninguém inadimplente" text="Todos os assinantes estão em dia." />
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left" style="color: var(--cor-texto-suave);">
                                    <th class="py-2 pr-4 font-medium">Cliente</th>
                                    <th class="py-2 pr-4 font-medium">Telefone</th>
                                    <th class="py-2 pr-4 font-medium">Plano</th>
                                    <th class="py-2 pr-4 font-medium">Desde</th>
                                    <th class="py-2 pr-4 font-medium tabular-nums">Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($inadimplentes->items() as $a)
                                    <tr style="border-top: 1px solid color-mix(in srgb, var(--cor-texto) 10%, transparent); color: var(--cor-texto);">
                                        <td class="py-2 pr-4">{{ $a->cliente?->nome }}</td>
                                        <td class="py-2 pr-4">{{ $a->cliente?->telefone }}</td>
                                        <td class="py-2 pr-4">{{ $a->plano?->nome }}</td>
                                        <td class="py-2 pr-4">{{ optional($a->data_inicio)->format('d/m/Y') }}</td>
                                        <td class="py-2 pr-4 tabular-nums">R$ {{ number_format((float) $a->preco_contratado, 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div>{{ $inadimplentes->links() }}</div>
                @endif
            </div>
        @endif

        {{-- ========================= PLANOS ========================= --}}
        @if ($aba === 'planos')
            <div class="flex justify-end">
                <flux:button wire:click="novoPlano" variant="primary" icon="plus">Novo plano</flux:button>
            </div>

            @if ($planos->isEmpty())
                <x-ng.empty icon="ticket" title="Nenhum plano ainda" text="Crie o primeiro plano de assinatura." />
            @else
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($planos as $plano)
                        <div class="ng-surface flex flex-col gap-2 p-5">
                            <div class="flex items-start justify-between gap-2">
                                <flux:heading size="lg" style="color: var(--cor-texto);">{{ $plano->nome }}</flux:heading>
                                <flux:badge :color="$plano->ativo ? 'green' : 'zinc'" size="sm">{{ $plano->ativo ? 'Ativo' : 'Inativo' }}</flux:badge>
                            </div>
                            <div class="text-2xl font-bold tabular-nums" style="color: var(--cor-texto);">R$ {{ number_format((float) $plano->preco_mensal, 2, ',', '.') }}<span class="text-sm font-normal" style="color: var(--cor-texto-suave);">/mês</span></div>
                            @if ($plano->descricao)
                                <flux:text class="text-sm" style="color: var(--cor-texto-suave);">{{ $plano->descricao }}</flux:text>
                            @endif
                            <div class="flex flex-wrap items-center gap-1.5 text-sm" style="color: var(--cor-texto-suave);">
                                @forelse ($plano->beneficios as $b)
                                    <flux:badge color="indigo" size="sm" icon="scissors">{{ $b->servico?->nome }}</flux:badge>
                                @empty
                                    <flux:badge color="amber" size="sm">Sem serviços cobertos</flux:badge>
                                @endforelse
                            </div>
                            <div class="flex flex-wrap items-center gap-2 text-xs" style="color: var(--cor-texto-suave);">
                                <span>{{ $plano->ilimitado ? 'Uso ilimitado' : (($plano->limite_usos ?? 0).'/mês') }}</span>
                                <span>·</span>
                                <span>{{ empty($plano->dias_semana) ? 'Todos os dias' : collect($plano->dias_semana)->map(fn ($d) => $diasLabel[(int) $d] ?? $d)->implode(', ') }}</span>
                                <span>·</span>
                                <span>{{ (int) $plano->capacidade }} {{ (int) $plano->capacidade > 1 ? 'contas' : 'conta' }}</span>
                                <span>·</span>
                                <span>{{ $plano->ativas_count }} ativo(s)</span>
                            </div>
                            <div class="mt-2 flex gap-2">
                                <flux:button wire:click="editarPlano({{ $plano->id }})" size="sm" variant="subtle" icon="pencil-square">Editar</flux:button>
                                <flux:button wire:click="alternarPlano({{ $plano->id }})" size="sm" variant="ghost">{{ $plano->ativo ? 'Inativar' : 'Reativar' }}</flux:button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif

        {{-- ========================= ASSINANTES ========================= --}}
        @if ($aba === 'assinantes')
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="flex flex-wrap items-end gap-3">
                    <flux:select wire:model.live="filtroPlano" label="Plano" class="min-w-44">
                        <flux:select.option value="">Todos os planos</flux:select.option>
                        @foreach ($planosAtivos as $p)
                            <flux:select.option :value="$p->id">{{ $p->nome }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="filtroStatus" label="Status" class="min-w-40">
                        <flux:select.option value="">Todos</flux:select.option>
                        @foreach ($statusLabel as $valor => $rotulo)
                            <flux:select.option :value="$valor">{{ $rotulo }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <flux:button wire:click="$set('novoClienteId', null); $flux.modal('novo-assinante').show()" variant="primary" icon="user-plus">Adicionar assinante</flux:button>
            </div>

            @if ($assinantes->total() === 0)
                <x-ng.empty icon="user-group" title="Nenhum assinante" text="Adicione um assinante ou ajuste os filtros." />
            @else
                <div class="ng-surface overflow-x-auto p-5">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left" style="color: var(--cor-texto-suave);">
                                <th class="py-2 pr-4 font-medium">Cliente</th>
                                <th class="py-2 pr-4 font-medium">Plano</th>
                                <th class="py-2 pr-4 font-medium">Status</th>
                                <th class="py-2 pr-4 font-medium">Desde</th>
                                <th class="py-2 pr-4 font-medium tabular-nums">Valor</th>
                                <th class="py-2 pr-4 font-medium">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($assinantes->items() as $a)
                                <tr style="border-top: 1px solid color-mix(in srgb, var(--cor-texto) 10%, transparent); color: var(--cor-texto);">
                                    <td class="py-2 pr-4">{{ $a->cliente?->nome }}</td>
                                    <td class="py-2 pr-4">{{ $a->plano?->nome }}</td>
                                    <td class="py-2 pr-4">
                                        @php($cor = ['ativa' => 'green', 'suspensa' => 'amber', 'inadimplente' => 'red', 'cancelada' => 'zinc'][$a->status] ?? 'zinc')
                                        <flux:badge :color="$cor" size="sm">{{ $statusLabel[$a->status] ?? $a->status }}</flux:badge>
                                    </td>
                                    <td class="py-2 pr-4">{{ optional($a->data_inicio)->format('d/m/Y') }}</td>
                                    <td class="py-2 pr-4 tabular-nums">R$ {{ number_format((float) $a->preco_contratado, 2, ',', '.') }}</td>
                                    <td class="py-2 pr-4">
                                        <div class="flex items-center gap-2">
                                            <flux:button wire:click="gerirBeneficiarios({{ $a->id }})" size="xs" variant="subtle" icon="users">
                                                Beneficiários ({{ $a->beneficiarios_count }})
                                            </flux:button>
                                            <flux:dropdown>
                                                <flux:button size="xs" variant="subtle" icon="ellipsis-horizontal">Status</flux:button>
                                                <flux:menu>
                                                    @if ($a->status !== 'ativa')
                                                        <flux:menu.item wire:click="mudarStatus({{ $a->id }}, 'ativa')" icon="check">Marcar ativa</flux:menu.item>
                                                    @endif
                                                    @if ($a->status !== 'inadimplente')
                                                        <flux:menu.item wire:click="mudarStatus({{ $a->id }}, 'inadimplente')" icon="exclamation-triangle">Marcar inadimplente</flux:menu.item>
                                                    @endif
                                                    @if ($a->status !== 'cancelada')
                                                        <flux:menu.item wire:click="mudarStatus({{ $a->id }}, 'cancelada')" icon="x-mark" variant="danger">Cancelar</flux:menu.item>
                                                    @endif
                                                </flux:menu>
                                            </flux:dropdown>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="mt-3">{{ $assinantes->links() }}</div>
                </div>
            @endif
        @endif

        {{-- ========================= RELATÓRIOS ========================= --}}
        @if ($aba === 'relatorios')
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="flex flex-wrap items-end gap-3">
                    <flux:input type="date" wire:model.live="relInicio" label="De" />
                    <flux:input type="date" wire:model.live="relFim" label="Até" />
                    <flux:select wire:model.live="filtroPlano" label="Plano" class="min-w-44">
                        <flux:select.option value="">Todos os planos</flux:select.option>
                        @foreach ($planosAtivos as $p)
                            <flux:select.option :value="$p->id">{{ $p->nome }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="filtroStatus" label="Status" class="min-w-40">
                        <flux:select.option value="">Todos</flux:select.option>
                        @foreach ($statusLabel as $valor => $rotulo)
                            <flux:select.option :value="$valor">{{ $rotulo }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <flux:button wire:click="exportarCsv" variant="primary" icon="arrow-down-tray">Exportar CSV</flux:button>
            </div>

            @if ($relatorio->total() === 0)
                <x-ng.empty icon="document-text" title="Nada no filtro" text="Ajuste o período/plano/status." />
            @else
                <div class="ng-surface overflow-x-auto p-5">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left" style="color: var(--cor-texto-suave);">
                                <th class="py-2 pr-4 font-medium">Cliente</th>
                                <th class="py-2 pr-4 font-medium">Plano</th>
                                <th class="py-2 pr-4 font-medium">Status</th>
                                <th class="py-2 pr-4 font-medium">Desde</th>
                                <th class="py-2 pr-4 font-medium tabular-nums">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($relatorio->items() as $a)
                                <tr style="border-top: 1px solid color-mix(in srgb, var(--cor-texto) 10%, transparent); color: var(--cor-texto);">
                                    <td class="py-2 pr-4">{{ $a->cliente?->nome }}</td>
                                    <td class="py-2 pr-4">{{ $a->plano?->nome }}</td>
                                    <td class="py-2 pr-4">{{ $statusLabel[$a->status] ?? $a->status }}</td>
                                    <td class="py-2 pr-4">{{ optional($a->data_inicio)->format('d/m/Y') }}</td>
                                    <td class="py-2 pr-4 tabular-nums">R$ {{ number_format((float) $a->preco_contratado, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="mt-3">{{ $relatorio->links() }}</div>
                </div>
            @endif
        @endif
    @endif

    {{-- Modal: criar/editar plano (cobertura) --}}
    <flux:modal name="plano-clube" class="md:w-[32rem]">
        <form wire:submit="salvarPlano" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ $planoId ? 'Editar plano' : 'Novo plano' }}</flux:heading>
            <flux:input wire:model="planoNome" label="Nome" required />
            <flux:input wire:model="planoPreco" type="number" step="0.01" min="0" label="Preço mensal (R$)" required />

            <flux:field>
                <flux:label>Serviços cobertos (100%)</flux:label>
                <div class="flex flex-col gap-1 rounded-lg border p-3" style="border-color: color-mix(in srgb, var(--cor-texto) 12%, transparent); max-height: 12rem; overflow-y: auto;">
                    @forelse ($servicos as $s)
                        <flux:checkbox wire:model="planoServicos" :value="$s->id" :label="$s->nome" />
                    @empty
                        <flux:text class="text-sm" style="color: var(--cor-texto-suave);">Nenhum serviço ativo.</flux:text>
                    @endforelse
                </div>
            </flux:field>

            <flux:field variant="inline">
                <flux:checkbox wire:model.live="planoIlimitado" />
                <flux:label>Uso ilimitado</flux:label>
            </flux:field>
            @unless ($planoIlimitado)
                <flux:input wire:model="planoLimite" type="number" min="1" label="Limite de usos por mês (compartilhado pela assinatura)" />
            @endunless

            <flux:field>
                <flux:label>Dias permitidos (vazio = todos)</flux:label>
                <div class="flex flex-wrap gap-3">
                    @foreach ($diasLabel as $num => $rotulo)
                        <flux:checkbox wire:model="planoDias" :value="$num" :label="$rotulo" />
                    @endforeach
                </div>
            </flux:field>

            <flux:input wire:model="planoCapacidade" type="number" min="1" label="Capacidade de contas (1 = individual, 2+ = família)" required />
            <flux:textarea wire:model="planoDescricao" label="Descrição (opcional)" rows="2" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Salvar</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal: beneficiários da assinatura --}}
    <flux:modal name="beneficiarios" class="md:w-[32rem]">
        <div class="flex flex-col gap-4">
            <div>
                <flux:heading size="lg">Beneficiários</flux:heading>
                @if ($assinaturaGeridaModel)
                    <flux:subheading>
                        {{ $assinaturaGeridaModel->plano?->nome }} · {{ $beneficiariosGeridos->count() }}/{{ (int) ($assinaturaGeridaModel->plano?->capacidade ?? 1) }} contas
                    </flux:subheading>
                @endif
            </div>

            <div class="flex flex-col gap-1">
                @foreach ($beneficiariosGeridos as $b)
                    <div class="flex items-center justify-between rounded-lg px-2 py-1" style="background-color: color-mix(in srgb, var(--cor-texto) 4%, transparent);">
                        <span style="color: var(--cor-texto);">
                            {{ $b->cliente?->nome ?? $b->nome }}
                            @if ($b->titular)<flux:badge size="sm" color="indigo">Titular</flux:badge>@endif
                            @unless ($b->cliente_id)<flux:badge size="sm" color="zinc">sem conta</flux:badge>@endunless
                        </span>
                        @unless ($b->titular)
                            <flux:button wire:click="removerBeneficiario({{ $b->id }})" size="xs" variant="ghost" icon="trash">Remover</flux:button>
                        @endunless
                    </div>
                @endforeach
            </div>

            @if ($assinaturaGeridaModel && $beneficiariosGeridos->count() < (int) ($assinaturaGeridaModel->plano?->capacidade ?? 1))
                <form wire:submit="adicionarBeneficiario" class="flex flex-col gap-3 border-t pt-3" style="border-color: color-mix(in srgb, var(--cor-texto) 12%, transparent);">
                    <flux:select wire:model="benClienteId" label="Cliente com conta (ou deixe vazio e use o nome)">
                        <flux:select.option value="">— sem conta —</flux:select.option>
                        @foreach ($clientes as $c)
                            <flux:select.option :value="$c->id">{{ $c->nome }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="benNome" label="Nome (beneficiário sem conta)" placeholder="ex.: filho(a)" />
                    <flux:button type="submit" variant="primary" icon="user-plus" class="self-start">Adicionar beneficiário</flux:button>
                </form>
            @else
                <flux:text class="text-sm" style="color: var(--cor-texto-suave);">Capacidade do plano atingida.</flux:text>
            @endif

            <div class="flex justify-end">
                <flux:modal.close><flux:button variant="ghost">Fechar</flux:button></flux:modal.close>
            </div>
        </div>
    </flux:modal>

    {{-- Modal: novo assinante --}}
    <flux:modal name="novo-assinante" class="md:w-96">
        <form wire:submit="adicionarAssinante" class="flex flex-col gap-4">
            <flux:heading size="lg">Adicionar assinante</flux:heading>
            <flux:select wire:model="novoClienteId" label="Cliente" required>
                <flux:select.option value="">Selecione…</flux:select.option>
                @foreach ($clientes as $c)
                    <flux:select.option :value="$c->id">{{ $c->nome }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model="novoPlanoId" label="Plano" required>
                <flux:select.option value="">Selecione…</flux:select.option>
                @foreach ($planosAtivos as $p)
                    <flux:select.option :value="$p->id">{{ $p->nome }} — R$ {{ number_format((float) $p->preco_mensal, 2, ',', '.') }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Adicionar</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
