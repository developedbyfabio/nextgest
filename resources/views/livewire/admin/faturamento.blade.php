<div class="flex flex-col gap-6">
    @php
        $rotuloSituacao = [
            'em_teste' => 'Em teste',
            'ativa' => 'Ativa',
            'atrasada' => 'Atrasada',
            'suspensa' => 'Suspensa',
            'cancelada' => 'Cancelada',
        ];
        $corSituacao = [
            'em_teste' => 'blue', 'ativa' => 'green', 'atrasada' => 'amber',
            'suspensa' => 'red', 'cancelada' => 'zinc',
        ];
        $corFatura = ['aberta' => 'blue', 'paga' => 'green', 'atrasada' => 'amber', 'cancelada' => 'zinc'];
        $rotuloFatura = ['aberta' => 'Aberta', 'paga' => 'Paga', 'atrasada' => 'Atrasada', 'cancelada' => 'Cancelada'];
    @endphp

    <x-ng.page-header :title="$tenant->nome" subtitle="Faturamento (assinatura SaaS)">
        <x-slot:actions>
            <flux:button :href="route('admin.tenant.detalhe', ['tenantId' => $tenant->id])" variant="ghost" icon="arrow-left" wire:navigate>Voltar</flux:button>
            <flux:button wire:click="abrirGerar" variant="primary" icon="document-plus">Gerar fatura</flux:button>
        </x-slot:actions>
    </x-ng.page-header>

    {{-- Situação (informativo — nenhum bloqueio nesta tela; bloqueio é a 4c) --}}
    <flux:card class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <flux:badge :color="$corSituacao[$situacao] ?? 'zinc'" size="lg">{{ $rotuloSituacao[$situacao] ?? $situacao }}</flux:badge>
            @if ($atraso)
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-300">
                    Vencida há <strong>{{ $atraso['dias'] }}</strong> {{ $atraso['dias'] == 1 ? 'dia' : 'dias' }}.
                    Carência até <strong>{{ \Illuminate\Support\Carbon::parse($atraso['limite'])->format('d/m/Y') }}</strong>.
                </flux:text>
            @endif
        </div>
        <flux:text class="text-xs text-zinc-500">
            Mensalidade do estabelecimento → Nextgest. Situação derivada de pagamentos/vencimentos (informativo).
        </flux:text>
    </flux:card>

    {{-- Cobrança automática (recorrência Mercado Pago — D61) --}}
    <flux:card class="flex flex-col gap-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <flux:heading size="lg">Cobrança automática (Mercado Pago)</flux:heading>
                <flux:subheading>Débito recorrente mensal. O dono cadastra o cartão uma vez, no link de adesão.</flux:subheading>
            </div>
            @if (! $recorrencia['ativa'])
                <flux:button wire:click="ativarCobrancaAutomatica" variant="primary" icon="credit-card" wire:loading.attr="disabled" wire:target="ativarCobrancaAutomatica">
                    <span wire:loading.remove wire:target="ativarCobrancaAutomatica">Ativar cobrança automática</span>
                    <span wire:loading wire:target="ativarCobrancaAutomatica">Ativando…</span>
                </flux:button>
            @else
                <flux:badge color="green" size="lg">Ativada · MP: {{ $recorrencia['mp_status'] ?? '—' }}</flux:badge>
            @endif
        </div>

        @if ($recorrencia['ativa'] && $recorrencia['link'])
            <flux:separator />
            <div class="flex flex-col gap-2">
                <flux:text class="text-sm text-zinc-500">Link de adesão (envie ao dono para cadastrar o cartão):</flux:text>
                <div class="flex items-center gap-2">
                    <flux:input readonly value="{{ $recorrencia['link'] }}" class="font-mono text-xs" />
                    <flux:button :href="$recorrencia['link']" target="_blank" variant="ghost" icon="arrow-top-right-on-square">Abrir</flux:button>
                </div>
                <flux:callout icon="information-circle">
                    <flux:callout.text>
                        A confirmação das cobranças mensais chega pelo webhook (próxima fase). O status acima é o que o Mercado Pago retornou na criação.
                    </flux:callout.text>
                </flux:callout>
            </div>
        @endif
    </flux:card>

    {{-- Configuração da assinatura --}}
    <form wire:submit="salvarConfig">
        <flux:card class="flex flex-col gap-4">
            <flux:heading size="lg">Configuração da assinatura</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:input wire:model="valorMensal" type="number" step="0.01" min="0" label="Valor mensal (R$)" required>
                    <x-slot:description>Default do plano; editável (lançamento/acordo).</x-slot:description>
                </flux:input>
                <flux:input wire:model="dataInicio" type="date" label="Início" required />
                <flux:input wire:model="diaVencimento" type="number" min="1" max="28" label="Dia de vencimento (1–28)" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:input wire:model="trialDias" type="number" min="0" label="Teste grátis (dias)">
                    <x-slot:description>Ex.: 15, 30, 60, 90.</x-slot:description>
                </flux:input>
                <flux:input wire:model="dataPrimeiraCobranca" type="date" label="1ª cobrança (combinada)">
                    <x-slot:description>Se preenchida, sobrescreve o teste grátis.</x-slot:description>
                </flux:input>
                <flux:select wire:model="statusManual" label="Status">
                    <flux:select.option value="em_teste">Em teste</flux:select.option>
                    <flux:select.option value="ativa">Ativa</flux:select.option>
                    <flux:select.option value="cancelada">Cancelada</flux:select.option>
                </flux:select>
            </div>

            <flux:textarea wire:model="observacoes" label="Observações" rows="2" />

            <flux:text class="text-xs text-zinc-500">
                "Atrasada" e "Suspensa" são <strong>derivadas</strong> dos vencimentos (não se define à mão).
                O plano/recursos são geridos na tela de <strong>Editar</strong>, não aqui.
            </flux:text>

            <div class="flex justify-end border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:button type="submit" variant="primary" icon="check">Salvar configuração</flux:button>
            </div>
        </flux:card>
    </form>

    {{-- Histórico de faturas --}}
    <div class="flex flex-col gap-3">
        <flux:heading size="lg">Faturas</flux:heading>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Competência</flux:table.column>
                <flux:table.column>Valor</flux:table.column>
                <flux:table.column>Vencimento</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Pago em</flux:table.column>
                <flux:table.column>Forma</flux:table.column>
                <flux:table.column />
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($faturas as $f)
                    <flux:table.row :key="$f->id">
                        <flux:table.cell variant="strong">{{ $f->competencia->format('m/Y') }}</flux:table.cell>
                        <flux:table.cell>R$ {{ number_format((float) $f->valor, 2, ',', '.') }}</flux:table.cell>
                        <flux:table.cell>{{ $f->data_vencimento->format('d/m/Y') }}</flux:table.cell>
                        <flux:table.cell><flux:badge :color="$corFatura[$f->status] ?? 'zinc'" size="sm">{{ $rotuloFatura[$f->status] ?? $f->status }}</flux:badge></flux:table.cell>
                        <flux:table.cell>{{ $f->data_pagamento?->format('d/m/Y') ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $f->forma_pagamento ?? '—' }}</flux:table.cell>
                        <flux:table.cell class="text-right">
                            @if ($f->status === 'paga')
                                <flux:button wire:click="pedirReverter({{ $f->id }})" size="sm" variant="ghost" icon="arrow-uturn-left">Reverter</flux:button>
                            @elseif ($f->status !== 'cancelada')
                                <flux:button wire:click="abrirPagar({{ $f->id }})" size="sm" variant="ghost" icon="banknotes">Marcar paga</flux:button>
                                <flux:button wire:click="pedirCancelar({{ $f->id }})" size="sm" variant="subtle" icon="x-mark">Cancelar</flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7">
                            <x-ng.empty icon="document-text" title="Sem faturas" text="Gere a primeira fatura com o botão acima." />
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- Modal: gerar fatura --}}
    <flux:modal name="gerar-fatura" class="md:w-96">
        <form wire:submit="gerarFatura" class="flex flex-col gap-4">
            <flux:heading size="lg">Gerar fatura</flux:heading>
            <flux:input wire:model="novaCompetencia" type="month" label="Competência" required />
            <flux:input wire:model="novoValor" type="number" step="0.01" min="0" label="Valor (R$)" required />
            <flux:input wire:model="novoVencimento" type="date" label="Vencimento" required />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Gerar</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal: marcar paga --}}
    <flux:modal name="pagar-fatura" class="md:w-96">
        <form wire:submit="confirmarPagamento" class="flex flex-col gap-4">
            <flux:heading size="lg">Marcar como paga</flux:heading>
            <flux:input wire:model="pagamentoData" type="date" label="Data do pagamento" required />
            <flux:select wire:model="pagamentoForma" label="Forma">
                <flux:select.option value="manual">Manual</flux:select.option>
                <flux:select.option value="mercadopago">Mercado Pago</flux:select.option>
                <flux:select.option value="asaas">Asaas</flux:select.option>
            </flux:select>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancelar</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Confirmar</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Confirmações por modal (padrão x-ng.confirmar — sem confirm nativo). --}}
    <x-ng.confirmar name="reverter-fatura" tom="amber" icone="arrow-uturn-left" titulo="Reverter o pagamento?"
        texto="A fatura volta para 'aberta' (limpa data e forma de pagamento). Use para corrigir um lançamento.">
        @if ($reverterId)
            <flux:button wire:click="reverter({{ $reverterId }})" variant="primary" icon="arrow-uturn-left">Reverter</flux:button>
        @endif
    </x-ng.confirmar>

    <x-ng.confirmar name="cancelar-fatura" tom="red" icone="x-mark" titulo="Cancelar esta fatura?"
        texto="A fatura fica como 'cancelada' e não conta mais para a situação da assinatura.">
        @if ($cancelarId)
            <flux:button wire:click="cancelar({{ $cancelarId }})" variant="danger" icon="x-mark">Cancelar fatura</flux:button>
        @endif
    </x-ng.confirmar>
</div>
