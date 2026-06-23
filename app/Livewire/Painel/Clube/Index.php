<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Clube;

use App\Models\AssinaturaClube;
use App\Models\Cliente;
use App\Models\PlanoClube;
use App\Models\PlanoDesconto;
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

    // Formulário de plano (benefício v1 = desconto %).
    public ?int $planoId = null;

    public string $planoNome = '';

    public string $planoPreco = '';

    public string $planoDescricao = '';

    public string $planoDescontoPct = '';

    // Novo assinante.
    public ?int $novoClienteId = null;

    public ?int $novoPlanoId = null;

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
        $this->reset('planoId', 'planoNome', 'planoPreco', 'planoDescricao', 'planoDescontoPct');
        $this->resetValidation();
        Flux::modal('plano-clube')->show();
    }

    public function editarPlano(int $id): void
    {
        $this->gate();
        $plano = PlanoClube::with('descontos')->findOrFail($id);
        $pct = $plano->descontos
            ->firstWhere(fn ($d) => $d->tipo_desconto === PlanoDesconto::TIPO_PERCENTUAL && $d->aplica_em === PlanoDesconto::APLICA_TODOS);

        $this->planoId = $plano->id;
        $this->planoNome = $plano->nome;
        $this->planoPreco = (string) $plano->preco_mensal;
        $this->planoDescricao = (string) $plano->descricao;
        $this->planoDescontoPct = $pct ? (string) (float) $pct->valor : '';
        $this->resetValidation();
        Flux::modal('plano-clube')->show();
    }

    public function salvarPlano(): void
    {
        $this->gate();

        $dados = $this->validate([
            'planoNome' => ['required', 'string', 'max:255'],
            'planoPreco' => ['required', 'numeric', 'min:0'],
            'planoDescricao' => ['nullable', 'string', 'max:1000'],
            'planoDescontoPct' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], attributes: [
            'planoNome' => 'nome', 'planoPreco' => 'preço', 'planoDescontoPct' => 'desconto',
        ]);

        $plano = PlanoClube::updateOrCreate(
            ['id' => $this->planoId],
            [
                'nome' => $dados['planoNome'],
                'preco_mensal' => $dados['planoPreco'],
                'descricao' => $dados['planoDescricao'] ?: null,
            ],
        );

        // Benefício v1 = desconto % (em "todos"). Sincroniza um único plano_desconto.
        $pct = (float) ($dados['planoDescontoPct'] ?: 0);
        $plano->descontos()
            ->where('tipo_desconto', PlanoDesconto::TIPO_PERCENTUAL)
            ->where('aplica_em', PlanoDesconto::APLICA_TODOS)
            ->delete();

        if ($pct > 0) {
            $plano->descontos()->create([
                'aplica_em' => PlanoDesconto::APLICA_TODOS,
                'tipo_desconto' => PlanoDesconto::TIPO_PERCENTUAL,
                'valor' => $pct,
            ]);
        }

        Flux::modal('plano-clube')->close();
        Flux::toast('Plano salvo.', variant: 'success');
    }

    public function alternarPlano(int $id): void
    {
        $this->gate();
        $plano = PlanoClube::findOrFail($id);
        $plano->update(['ativo' => ! $plano->ativo]);
        Flux::toast($plano->ativo ? 'Plano reativado.' : 'Plano inativado.', variant: 'success');
    }

    // ---- Assinantes ---------------------------------------------------------

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
                $dados['planos'] = PlanoClube::with('descontos')->withCount(['assinaturas as ativas_count' => fn ($q) => $q->where('status', AssinaturaClube::STATUS_ATIVA)])->orderBy('nome')->get();
            } elseif ($this->aba === 'assinantes') {
                $dados['assinantes'] = $this->queryAssinaturas()->paginate(15);
            } elseif ($this->aba === 'relatorios') {
                $dados['relatorio'] = $this->queryAssinaturas()->paginate(15);
            }
        } catch (\Throwable $e) {
            report($e);
            $this->erro = true;
        }

        return view('livewire.painel.clube.index', array_merge($dados, [
            'planosAtivos' => PlanoClube::ativo()->orderBy('nome')->get(['id', 'nome', 'preco_mensal']),
            'statusLabel' => AssinaturaClube::STATUS_LABEL,
            'clientes' => $this->aba === 'assinantes' ? Cliente::orderBy('nome')->limit(500)->get(['id', 'nome']) : collect(),
        ]));
    }
}
