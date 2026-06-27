<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Clube;

use App\Models\AssinaturaClube;
use App\Models\BeneficiarioAssinatura;
use App\Models\Cliente;
use App\Models\PlanoClube;
use App\Models\Servico;
use App\Services\Clube\Assinaturas;
use App\Services\Clube\IndicadoresClube;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Aba "Clube de Assinatura" (Fase A). CASCA sobre os serviços do clube — NÃO recalcula
 * indicadores (usa App\Services\Clube\IndicadoresClube) nem reescreve o ciclo de vida
 * (usa App\Services\Clube\Assinaturas, que grava os eventos). Gated por `recurso:clube`
 * (rota → 404 se off) + permissão `gerenciar_clube` (D39, nunca hasRole).
 *
 * Cobrança recorrente está PENDENTE de gateway/VPS — status é manual (costura
 * GatewayRecorrente, impl. manual). A UI deixa isso explícito.
 */
#[Layout('components.layouts.painel')]
#[Title('Clube de Assinatura')]
class Index extends Component
{
    use WithPagination;

    /** Seção ativa: visao | planos | assinantes | relatorios. */
    public string $aba = 'visao';

    // Filtros (assinantes/relatórios).
    public ?int $filtroPlano = null;

    public string $filtroStatus = '';

    public ?string $relInicio = null;

    public ?string $relFim = null;

    // Formulário de plano (benefício = COBERTURA de serviços).
    public ?int $planoId = null;

    public string $planoNome = '';

    public string $planoPreco = '';

    public string $planoDescricao = '';

    /** Ids dos serviços cobertos. */
    public array $planoServicos = [];

    public bool $planoIlimitado = true;

    public string $planoLimite = '';

    /** Dias da semana elegíveis (0=dom..6=sáb); vazio = todos. */
    public array $planoDias = [];

    public string $planoCapacidade = '1';

    // Novo assinante.
    public ?int $novoClienteId = null;

    public ?int $novoPlanoId = null;

    // Gestão de beneficiários (de uma assinatura).
    public ?int $assinaturaGerida = null;

    public ?int $benClienteId = null;

    public string $benNome = '';

    public bool $erro = false;

    public function mount(): void
    {
        abort_unless(tenant_tem_recurso('clube'), 404);
        abort_unless(auth('web')->user()?->can('gerenciar_clube') ?? false, 403);

        $this->relInicio ??= Carbon::today()->startOfMonth()->toDateString();
        $this->relFim ??= Carbon::today()->toDateString();
    }

    public function setAba(string $aba): void
    {
        if (in_array($aba, ['visao', 'planos', 'assinantes', 'relatorios'], true)) {
            $this->aba = $aba;
            $this->resetPage();
        }
    }

    public function updated(string $prop): void
    {
        if (in_array($prop, ['filtroPlano', 'filtroStatus', 'relInicio', 'relFim'], true)) {
            $this->resetPage();
        }
    }

    private function gate(): void
    {
        abort_unless(auth('web')->user()?->can('gerenciar_clube') ?? false, 403);
    }

    // ---- Planos -------------------------------------------------------------

    public function novoPlano(): void
    {
        $this->gate();
        $this->reset('planoId', 'planoNome', 'planoPreco', 'planoDescricao', 'planoServicos', 'planoLimite');
        $this->planoIlimitado = true;
        $this->planoDias = [];
        $this->planoCapacidade = '1';
        $this->resetValidation();
        Flux::modal('plano-clube')->show();
    }

    public function editarPlano(int $id): void
    {
        $this->gate();
        $plano = PlanoClube::with('beneficios')->findOrFail($id);

        $this->planoId = $plano->id;
        $this->planoNome = $plano->nome;
        $this->planoPreco = (string) $plano->preco_mensal;
        $this->planoDescricao = (string) $plano->descricao;
        $this->planoServicos = $plano->servicosCobertosIds();
        $this->planoIlimitado = (bool) $plano->ilimitado;
        $this->planoLimite = $plano->limite_usos ? (string) $plano->limite_usos : '';
        $this->planoDias = array_map('intval', $plano->dias_semana ?? []);
        $this->planoCapacidade = (string) max(1, (int) $plano->capacidade);
        $this->resetValidation();
        Flux::modal('plano-clube')->show();
    }

    public function salvarPlano(): void
    {
        $this->gate();

        // "Teto" (não-ilimitado) exige o número de usos preenchido e > 0 — senão o plano
        // viraria ilimitado silenciosamente (limite_usos = null). O ilimitado segue sem número.
        $regraLimite = $this->planoIlimitado
            ? ['nullable']
            : ['required', 'integer', 'min:1'];

        $dados = $this->validate([
            'planoNome' => ['required', 'string', 'max:255'],
            'planoPreco' => ['required', 'numeric', 'min:0'],
            'planoDescricao' => ['nullable', 'string', 'max:1000'],
            // Plano de COBERTURA tem de cobrir pelo menos 1 serviço (regra "1+", D44).
            'planoServicos' => ['required', 'array', 'min:1'],
            'planoServicos.*' => ['integer', 'exists:servicos,id'],
            'planoCapacidade' => ['required', 'integer', 'min:1'],
            'planoLimite' => $regraLimite,
        ], messages: [
            'planoServicos.required' => 'Selecione ao menos um serviço coberto pelo plano.',
            'planoServicos.min' => 'Selecione ao menos um serviço coberto pelo plano.',
            'planoLimite.required' => 'Informe o limite de usos por mês (ou marque o plano como ilimitado).',
            'planoLimite.min' => 'O limite de usos por mês precisa ser maior que zero.',
        ], attributes: ['planoNome' => 'nome', 'planoPreco' => 'preço', 'planoCapacidade' => 'capacidade']);

        $plano = PlanoClube::updateOrCreate(
            ['id' => $this->planoId],
            [
                'nome' => $dados['planoNome'],
                'preco_mensal' => $dados['planoPreco'],
                'descricao' => $dados['planoDescricao'] ?: null,
                'ilimitado' => $this->planoIlimitado,
                'limite_usos' => $this->planoIlimitado ? null : ((int) $this->planoLimite ?: null),
                'periodo' => 'mes',
                'dias_semana' => $this->planoDias === [] ? null : array_values(array_map('intval', $this->planoDias)),
                'capacidade' => (int) $dados['planoCapacidade'],
            ],
        );

        // Serviços cobertos: sincroniza a pivô plano_beneficios (uma linha por serviço).
        $plano->beneficios()->delete();
        foreach (array_unique(array_map('intval', $this->planoServicos)) as $servicoId) {
            $plano->beneficios()->create(['servico_id' => $servicoId, 'tipo' => 'ilimitado']);
        }

        Flux::modal('plano-clube')->close();
        Flux::toast('Plano salvo.', variant: 'success');
    }

    // ---- Beneficiários ------------------------------------------------------

    public function gerirBeneficiarios(int $assinaturaId): void
    {
        $this->gate();
        $this->assinaturaGerida = $assinaturaId;
        $this->reset('benClienteId', 'benNome');
        $this->resetValidation();
        Flux::modal('beneficiarios')->show();
    }

    public function adicionarBeneficiario(Assinaturas $assinaturas): void
    {
        $this->gate();
        $assinatura = AssinaturaClube::with('plano')->findOrFail($this->assinaturaGerida);

        $this->validate([
            'benClienteId' => ['nullable', 'integer', 'exists:clientes,id'],
            'benNome' => ['nullable', 'string', 'max:255'],
        ], attributes: ['benClienteId' => 'cliente', 'benNome' => 'nome']);

        try {
            $assinaturas->adicionarBeneficiario($assinatura, $this->benClienteId ?: null, $this->benNome ?: null);
        } catch (\Throwable $e) {
            Flux::toast($e->getMessage(), variant: 'danger');

            return;
        }

        $this->reset('benClienteId', 'benNome');
        Flux::toast('Beneficiário adicionado.', variant: 'success');
    }

    public function removerBeneficiario(int $beneficiarioId, Assinaturas $assinaturas): void
    {
        $this->gate();
        $beneficiario = BeneficiarioAssinatura::findOrFail($beneficiarioId);
        $assinaturas->removerBeneficiario($beneficiario);
        Flux::toast('Beneficiário removido.', variant: 'success');
    }

    public function alternarPlano(int $id): void
    {
        $this->gate();
        $plano = PlanoClube::findOrFail($id);
        $plano->update(['ativo' => ! $plano->ativo]);
        Flux::toast($plano->ativo ? 'Plano reativado.' : 'Plano inativado.', variant: 'success');
    }

    // ---- Assinantes ---------------------------------------------------------

    /**
     * Abre o modal de novo assinante (padrão server-side, igual a novoPlano/
     * gerirBeneficiarios). Antes o gatilho misturava `$flux` (Alpine) num `wire:click`
     * (Livewire) — malformado: abria o modal sozinho ao renderizar a aba. Ver D66.
     */
    public function novoAssinante(): void
    {
        $this->gate();
        $this->reset('novoClienteId', 'novoPlanoId');
        $this->resetValidation();
        Flux::modal('novo-assinante')->show();
    }

    public function adicionarAssinante(Assinaturas $assinaturas): void
    {
        $this->gate();

        $this->validate([
            'novoClienteId' => ['required', 'integer', 'exists:clientes,id'],
            'novoPlanoId' => ['required', 'integer', 'exists:planos_clube,id'],
        ], attributes: ['novoClienteId' => 'cliente', 'novoPlanoId' => 'plano']);

        $plano = PlanoClube::findOrFail($this->novoPlanoId);
        $assinaturas->criar($this->novoClienteId, $plano, AssinaturaClube::STATUS_ATIVA);

        $this->reset('novoClienteId', 'novoPlanoId');
        Flux::modal('novo-assinante')->close();
        Flux::toast('Assinante adicionado.', variant: 'success');
    }

    public function mudarStatus(int $assinaturaId, string $status, Assinaturas $assinaturas): void
    {
        $this->gate();
        $assinatura = AssinaturaClube::findOrFail($assinaturaId);
        $assinaturas->alterarStatus($assinatura, $status);
        Flux::toast('Status atualizado: '.(AssinaturaClube::STATUS_LABEL[$status] ?? $status).'.', variant: 'success');
    }

    // ---- Relatórios / CSV ---------------------------------------------------

    /** Query de assinaturas aplicando os filtros (plano/status/período por data_inicio). */
    private function queryAssinaturas()
    {
        return AssinaturaClube::query()
            ->with(['cliente:id,nome,telefone', 'plano:id,nome'])
            ->when($this->filtroPlano, fn ($q) => $q->where('plano_id', $this->filtroPlano))
            ->when($this->filtroStatus !== '', fn ($q) => $q->where('status', $this->filtroStatus))
            ->when($this->aba === 'relatorios' && $this->relInicio && $this->relFim, fn ($q) => $q->whereBetween('data_inicio', [
                Carbon::parse($this->relInicio)->startOfDay(),
                Carbon::parse($this->relFim)->endOfDay(),
            ]))
            ->orderByDesc('data_inicio');
    }

    public function exportarCsv(): StreamedResponse
    {
        $this->gate();

        $assinaturas = $this->queryAssinaturas()->get();

        return response()->streamDownload(function () use ($assinaturas) {
            $saida = fopen('php://output', 'w');
            fputcsv($saida, ['Cliente', 'Telefone', 'Plano', 'Status', 'Desde', 'Valor mensal']);
            foreach ($assinaturas as $a) {
                fputcsv($saida, [
                    $a->cliente?->nome,
                    $a->cliente?->telefone,
                    $a->plano?->nome,
                    AssinaturaClube::STATUS_LABEL[$a->status] ?? $a->status,
                    optional($a->data_inicio)->format('d/m/Y'),
                    number_format((float) $a->preco_contratado, 2, ',', '.'),
                ]);
            }
            fclose($saida);
        }, 'clube-assinantes-'.now()->format('Ymd_His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function render(IndicadoresClube $indicadores): View
    {
        $dados = [
            'ativos' => 0, 'novosMes' => 0, 'canceladosMes' => 0,
            'inadimplentes' => null, 'evolucao' => collect(),
            'planos' => collect(), 'assinantes' => null, 'relatorio' => null,
        ];

        try {
            $this->erro = false;

            if ($this->aba === 'visao') {
                $dados['ativos'] = $indicadores->assinantesAtivos();
                $dados['novosMes'] = $indicadores->novosNoMes();
                $dados['canceladosMes'] = $indicadores->canceladosNoMes();
                $dados['inadimplentes'] = $indicadores->inadimplentes(10);
                $dados['evolucao'] = $indicadores->evolucao(6);
            } elseif ($this->aba === 'planos') {
                $dados['planos'] = PlanoClube::with('beneficios.servico:id,nome')
                    ->withCount(['assinaturas as ativas_count' => fn ($q) => $q->where('status', AssinaturaClube::STATUS_ATIVA)])
                    ->orderBy('nome')->get();
            } elseif ($this->aba === 'assinantes') {
                $dados['assinantes'] = $this->queryAssinaturas()->withCount('beneficiarios')->paginate(15);
            } elseif ($this->aba === 'relatorios') {
                $dados['relatorio'] = $this->queryAssinaturas()->paginate(15);
            }
        } catch (\Throwable $e) {
            report($e);
            $this->erro = true;
        }

        // Beneficiários da assinatura em gestão (modal).
        $beneficiariosGeridos = $this->assinaturaGerida
            ? BeneficiarioAssinatura::with('cliente:id,nome')->where('assinatura_id', $this->assinaturaGerida)->get()
            : collect();
        $assinaturaGeridaModel = $this->assinaturaGerida
            ? AssinaturaClube::with('plano:id,nome,capacidade')->find($this->assinaturaGerida)
            : null;

        return view('livewire.painel.clube.index', array_merge($dados, [
            'planosAtivos' => PlanoClube::ativo()->orderBy('nome')->get(['id', 'nome', 'preco_mensal']),
            'statusLabel' => AssinaturaClube::STATUS_LABEL,
            'servicos' => $this->aba === 'planos' ? Servico::where('ativo', true)->orderBy('nome')->get(['id', 'nome']) : collect(),
            'clientes' => $this->aba === 'assinantes' ? Cliente::orderBy('nome')->limit(500)->get(['id', 'nome']) : collect(),
            'beneficiariosGeridos' => $beneficiariosGeridos,
            'assinaturaGeridaModel' => $assinaturaGeridaModel,
            'diasLabel' => [0 => 'Dom', 1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb'],
        ]));
    }
}
