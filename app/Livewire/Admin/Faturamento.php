<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Assinatura;
use App\Models\Estabelecimento;
use App\Models\Fatura;
use App\Models\Tenant;
use App\Services\MercadoPago\MercadoPagoException;
use App\Services\MercadoPago\PreapprovalClient;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Tela "Faturamento" do admin (D59): configura a assinatura SaaS do tenant, gera
 * faturas, marca pago/reverte/cancela (manual) e mostra a situação via
 * Assinatura::situacaoAcesso() (D58). Cobrança salão → Nextgest (≠ Clube).
 *
 * SÓ operação manual + visualização: NENHUM bloqueio de login/painel (isso é a 4c) e
 * NENHUM gateway (Fase 5 — `link_pagamento` continua nulo). `atrasada`/`suspensa` são
 * DERIVADAS (situacaoAcesso); o status manual aceita só em_teste/ativa/cancelada.
 */
#[Layout('components.layouts.admin')]
#[Title('Faturamento')]
class Faturamento extends Component
{
    public Tenant $tenant;

    public int $assinaturaId;

    // Config da assinatura.
    public string $valorMensal = '';

    public string $dataInicio = '';

    public ?string $trialDias = null;

    public ?string $dataPrimeiraCobranca = null;

    public ?string $diaVencimento = null;

    public string $statusManual = Assinatura::EM_TESTE;

    public string $observacoes = '';

    // Gerar fatura (modal).
    public string $novaCompetencia = '';

    public string $novoValor = '';

    public string $novoVencimento = '';

    // Marcar paga (modal).
    public ?int $pagamentoId = null;

    public string $pagamentoData = '';

    public string $pagamentoForma = 'manual';

    public function mount(string $tenantId): void
    {
        abort_unless(auth('admin')->check(), 403);

        $this->tenant = Tenant::findOrFail($tenantId);

        $a = Assinatura::firstOrNew(['tenant_id' => $this->tenant->getKey()]);

        // 1º uso: cria a assinatura com defaults (snapshot do plano atual).
        if (! $a->exists) {
            $plano = $this->tenant->planoAtual();
            $a->fill([
                'plano' => $plano,
                'valor_mensal' => (float) (config("planos.{$plano}.preco_mes") ?? 0),
                'data_inicio' => $this->tenant->created_at ?? now(),
                'trial_dias' => (int) config('cobranca.trial_padrao_dias', 30),
                'status' => Assinatura::EM_TESTE,
            ])->save();
        }

        $this->assinaturaId = $a->id;
        $this->preencherForm($a);
    }

    protected function preencherForm(Assinatura $a): void
    {
        $this->valorMensal = (string) $a->valor_mensal;
        $this->dataInicio = $a->data_inicio->toDateString();
        $this->trialDias = $a->trial_dias !== null ? (string) $a->trial_dias : null;
        $this->dataPrimeiraCobranca = $a->data_primeira_cobranca?->toDateString();
        $this->diaVencimento = $a->dia_vencimento !== null ? (string) $a->dia_vencimento : null;
        // atrasada/suspensa são derivadas — no seletor manual cai para "ativa".
        $this->statusManual = in_array($a->status, [Assinatura::EM_TESTE, Assinatura::ATIVA, Assinatura::CANCELADA], true)
            ? $a->status
            : Assinatura::ATIVA;
        $this->observacoes = (string) ($a->observacoes ?? '');
    }

    protected function assinatura(): Assinatura
    {
        return Assinatura::findOrFail($this->assinaturaId);
    }

    /** Vazios ('') dos campos opcionais viram null antes de validar/salvar. */
    protected function normalizar(): void
    {
        foreach (['trialDias', 'dataPrimeiraCobranca', 'diaVencimento'] as $campo) {
            if ($this->{$campo} === '') {
                $this->{$campo} = null;
            }
        }
    }

    public function salvarConfig(): void
    {
        abort_unless(auth('admin')->check(), 403);

        $this->normalizar();

        $this->validate([
            'valorMensal' => ['required', 'numeric', 'min:0'],
            'dataInicio' => ['required', 'date'],
            'trialDias' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'dataPrimeiraCobranca' => ['nullable', 'date'],
            'diaVencimento' => ['nullable', 'integer', 'min:1', 'max:28'],
            'statusManual' => ['required', Rule::in([Assinatura::EM_TESTE, Assinatura::ATIVA, Assinatura::CANCELADA])],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ], attributes: [
            'valorMensal' => 'valor mensal',
            'dataInicio' => 'data de início',
            'trialDias' => 'dias de teste',
            'dataPrimeiraCobranca' => 'data da 1ª cobrança',
            'diaVencimento' => 'dia de vencimento',
            'statusManual' => 'status',
        ]);

        $a = $this->assinatura();
        $a->fill([
            'valor_mensal' => $this->valorMensal,
            'data_inicio' => $this->dataInicio,
            'trial_dias' => $this->trialDias !== null ? (int) $this->trialDias : null,
            'data_primeira_cobranca' => $this->dataPrimeiraCobranca ?: null,
            'dia_vencimento' => $this->diaVencimento !== null ? (int) $this->diaVencimento : null,
            'status' => $this->statusManual,
            'observacoes' => $this->observacoes ?: null,
        ])->save();

        Flux::toast('Configuração da assinatura salva.', variant: 'success');
    }

    public function abrirGerar(): void
    {
        $a = $this->assinatura();
        $comp = now()->startOfMonth();

        $this->novaCompetencia = $comp->format('Y-m');
        $this->novoValor = (string) $a->valor_mensal;

        // Vencimento padrão: 1ª fatura → data da 1ª cobrança; demais → dia de vencimento
        // do mês da competência (1–28). Editável antes de confirmar.
        if ($a->faturas()->count() === 0) {
            $venc = $a->primeiraCobranca();
        } else {
            $dia = $a->dia_vencimento ?: (int) $a->primeiraCobranca()->day;
            $venc = $comp->copy()->day(min(max($dia, 1), 28));
        }
        $this->novoVencimento = $venc->toDateString();

        $this->resetValidation();
        Flux::modal('gerar-fatura')->show();
    }

    public function gerarFatura(): void
    {
        abort_unless(auth('admin')->check(), 403);

        $this->validate([
            'novaCompetencia' => ['required', 'date_format:Y-m'],
            'novoValor' => ['required', 'numeric', 'min:0'],
            'novoVencimento' => ['required', 'date'],
        ], attributes: [
            'novaCompetencia' => 'competência',
            'novoValor' => 'valor',
            'novoVencimento' => 'vencimento',
        ]);

        $competencia = Carbon::createFromFormat('Y-m', $this->novaCompetencia)->startOfMonth();

        $jaExiste = Fatura::where('assinatura_id', $this->assinaturaId)
            ->whereDate('competencia', $competencia->toDateString())
            ->exists();

        if ($jaExiste) {
            $this->addError('novaCompetencia', 'Já existe uma fatura para essa competência.');

            return;
        }

        Fatura::create([
            'assinatura_id' => $this->assinaturaId,
            'competencia' => $competencia,
            'valor' => $this->novoValor,
            'data_vencimento' => $this->novoVencimento,
            'status' => Fatura::ABERTA,
        ]);

        Flux::modal('gerar-fatura')->close();
        Flux::toast('Fatura gerada.', variant: 'success');
    }

    public function abrirPagar(int $faturaId): void
    {
        $this->pagamentoId = $faturaId;
        $this->pagamentoData = now()->toDateString();
        $this->pagamentoForma = 'manual';
        $this->resetValidation();
        Flux::modal('pagar-fatura')->show();
    }

    public function confirmarPagamento(): void
    {
        abort_unless(auth('admin')->check(), 403);

        $this->validate([
            'pagamentoData' => ['required', 'date'],
            'pagamentoForma' => ['required', Rule::in(['manual', 'mercadopago', 'asaas'])],
        ], attributes: ['pagamentoData' => 'data do pagamento', 'pagamentoForma' => 'forma']);

        $fatura = $this->faturaDoTenant($this->pagamentoId);
        $fatura->update([
            'status' => Fatura::PAGA,
            'data_pagamento' => $this->pagamentoData,
            'forma_pagamento' => $this->pagamentoForma,
        ]);

        $this->pagamentoId = null;
        Flux::modal('pagar-fatura')->close();
        Flux::toast('Fatura marcada como paga.', variant: 'success');
    }

    /** Ids alvos das confirmações por modal (x-ng.confirmar, sem confirm nativo — D65). */
    public ?int $reverterId = null;

    public ?int $cancelarId = null;

    public function pedirReverter(int $faturaId): void
    {
        $this->reverterId = $faturaId;
        Flux::modal('reverter-fatura')->show();
    }

    public function reverter(int $faturaId): void
    {
        abort_unless(auth('admin')->check(), 403);

        $this->faturaDoTenant($faturaId)->update([
            'status' => Fatura::ABERTA,
            'data_pagamento' => null,
            'forma_pagamento' => null,
        ]);

        $this->reverterId = null;
        Flux::modal('reverter-fatura')->close();
        Flux::toast('Pagamento revertido (fatura voltou a aberta).');
    }

    public function pedirCancelar(int $faturaId): void
    {
        $this->cancelarId = $faturaId;
        Flux::modal('cancelar-fatura')->show();
    }

    public function cancelar(int $faturaId): void
    {
        abort_unless(auth('admin')->check(), 403);

        $this->faturaDoTenant($faturaId)->update(['status' => Fatura::CANCELADA]);

        $this->cancelarId = null;
        Flux::modal('cancelar-fatura')->close();
        Flux::toast('Fatura cancelada.');
    }

    /** Garante que a fatura pertence à assinatura deste tenant (defesa). */
    protected function faturaDoTenant(int $faturaId): Fatura
    {
        return Fatura::where('id', $faturaId)
            ->where('assinatura_id', $this->assinaturaId)
            ->firstOrFail();
    }

    /**
     * Ativa a cobrança automática (recorrência Mercado Pago — D61). Cria o preapproval
     * e expõe o link de adesão (o dono cadastra o cartão no MP). IDEMPOTENTE: se já há
     * recorrência, não recria. A confirmação das cobranças vem pelo webhook (5b).
     */
    public function ativarCobrancaAutomatica(PreapprovalClient $mp): void
    {
        abort_unless(auth('admin')->check(), 403);

        $a = $this->assinatura();

        // Idempotência: já existe recorrência → não recria (só mantém o link/status atual).
        if ($a->temRecorrencia()) {
            Flux::toast('A cobrança automática já está ativada para este estabelecimento.');

            return;
        }

        if ((float) $a->valor_mensal <= 0) {
            Flux::toast('Defina um valor mensal maior que zero antes de ativar a cobrança automática.', variant: 'danger');

            return;
        }

        // E-mail do pagador = contato cadastral do dono (tela Dados).
        $payerEmail = Estabelecimento::where('tenant_id', $a->tenant_id)->value('dono_email');

        if (empty($payerEmail)) {
            Flux::toast('Cadastre o e-mail do dono na tela "Dados" antes de ativar a cobrança automática.', variant: 'danger');

            return;
        }

        try {
            $resultado = $mp->criarPreapproval($a, $payerEmail);
        } catch (MercadoPagoException $e) {
            Flux::toast($e->getMessage(), variant: 'danger');

            return;
        }

        $a->update([
            'mp_preapproval_id' => $resultado['id'],
            'mp_status' => $resultado['status'],
            'link_adesao' => $resultado['init_point'],
            'cobranca_automatica' => true,
        ]);

        Flux::toast('Cobrança automática criada. Envie o link de adesão para o dono cadastrar o cartão.', variant: 'success');
    }

    public function render(): View
    {
        $a = $this->assinatura();
        $situacao = $a->situacaoAcesso();

        // Info de atraso (só apresentação): fatura não paga vencida mais antiga.
        $atraso = null;
        if (in_array($situacao, [Assinatura::ATRASADA, Assinatura::SUSPENSA], true)) {
            $venc = $a->faturas()
                ->whereNotIn('status', [Fatura::PAGA, Fatura::CANCELADA])
                ->orderBy('data_vencimento')
                ->value('data_vencimento');

            if ($venc) {
                $venc = Carbon::parse($venc)->startOfDay();
                $atraso = [
                    'dias' => (int) $venc->diffInDays(Carbon::today()),
                    'limite' => $venc->copy()->addDays((int) config('cobranca.carencia_dias', 20))->toDateString(),
                ];
            }
        }

        return view('livewire.admin.faturamento', [
            'situacao' => $situacao,
            'atraso' => $atraso,
            'faturas' => $a->faturas()->orderByDesc('competencia')->get(),
            'recorrencia' => [
                'ativa' => $a->temRecorrencia(),
                'mp_status' => $a->mp_status,
                'link' => $a->link_adesao,
            ],
        ]);
    }
}
