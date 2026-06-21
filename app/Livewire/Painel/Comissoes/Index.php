<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Comissoes;

use App\Models\ComissaoProfissional;
use App\Models\Produto;
use App\Models\Servico;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Dashboard\Metricas;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Relatório de comissões por profissional (Fatia 2C) + gestão dos overrides de
 * comissão (`comissoes_profissional`). Financeiro: permissão `ver_financeiro` (Dono).
 * O relatório usa o snapshot `venda_itens.valor_comissao` das vendas pagas.
 */
#[Layout('components.layouts.painel')]
#[Title('Comissões')]
class Index extends Component
{
    use AuthorizesRequests;

    public string $periodo = '30d';

    public ?string $dataInicio = null;

    public ?string $dataFim = null;

    public ?string $unidadeId = '';

    // Modal de overrides.
    public bool $mostrarOverrides = false;

    public ?string $overrideProfId = null;

    /** @var array<int, string> servico_id => % */
    public array $overrideServico = [];

    /** @var array<int, string> produto_id => % */
    public array $overrideProduto = [];

    public function mount(): void
    {
        $this->authorize('ver_financeiro');
        $this->dataInicio ??= Carbon::today()->subDays(29)->toDateString();
        $this->dataFim ??= Carbon::today()->toDateString();
    }

    private function intervalo(): array
    {
        $hoje = Carbon::today();

        return match ($this->periodo) {
            'hoje' => [$hoje->copy()->startOfDay(), $hoje->copy()->endOfDay()],
            '7d' => [$hoje->copy()->subDays(6)->startOfDay(), $hoje->copy()->endOfDay()],
            'mes' => [$hoje->copy()->startOfMonth(), $hoje->copy()->endOfMonth()],
            'custom' => $this->intervaloCustom(),
            default => [$hoje->copy()->subDays(29)->startOfDay(), $hoje->copy()->endOfDay()],
        };
    }

    private function intervaloCustom(): array
    {
        $hoje = Carbon::today();
        $inicio = Carbon::parse($this->dataInicio ?: $hoje)->startOfDay();
        $fim = Carbon::parse($this->dataFim ?: $hoje)->endOfDay();

        return $fim->lt($inicio) ? [$fim->copy()->startOfDay(), $inicio->copy()->endOfDay()] : [$inicio, $fim];
    }

    // ----- Overrides por profissional -----

    public function abrirOverrides(): void
    {
        $this->authorize('ver_financeiro');
        $this->reset(['overrideProfId', 'overrideServico', 'overrideProduto']);
        $this->resetValidation();
        $this->mostrarOverrides = true;
    }

    public function updatedOverrideProfId(): void
    {
        $this->overrideServico = [];
        $this->overrideProduto = [];

        if (! $this->overrideProfId) {
            return;
        }

        $overrides = ComissaoProfissional::where('user_id', (int) $this->overrideProfId)->get();

        foreach ($overrides as $o) {
            if ($o->servico_id) {
                $this->overrideServico[$o->servico_id] = (string) $o->percentual;
            } elseif ($o->produto_id) {
                $this->overrideProduto[$o->produto_id] = (string) $o->percentual;
            }
        }
    }

    public function salvarOverrides(): void
    {
        $this->authorize('ver_financeiro');

        $userId = (int) $this->overrideProfId;
        abort_unless($userId && User::whereKey($userId)->exists(), 422);

        $this->sincronizarOverride('servico_id', Servico::pluck('id'), $this->overrideServico, $userId);
        $this->sincronizarOverride('produto_id', Produto::pluck('id'), $this->overrideProduto, $userId);

        $this->mostrarOverrides = false;
        Flux::toast('Comissões personalizadas salvas.', variant: 'success');
    }

    /**
     * Para cada item: % preenchida (0–100) → grava/atualiza override; vazia → remove
     * o override (volta ao % padrão).
     */
    private function sincronizarOverride(string $coluna, $idsValidos, array $valores, int $userId): void
    {
        foreach ($idsValidos as $id) {
            $bruto = $valores[$id] ?? '';
            $valor = is_string($bruto) ? trim($bruto) : $bruto;

            if ($valor === '' || $valor === null) {
                ComissaoProfissional::where('user_id', $userId)->where($coluna, $id)->delete();

                continue;
            }

            $pct = max(0, min(100, (float) str_replace(',', '.', (string) $valor)));

            ComissaoProfissional::updateOrCreate(
                ['user_id' => $userId, $coluna => $id],
                ['percentual' => $pct],
            );
        }
    }

    public function render(): View
    {
        [$inicio, $fim] = $this->intervalo();
        $unidade = ($this->unidadeId === null || $this->unidadeId === '') ? null : (int) $this->unidadeId;

        $comissoes = (new Metricas($inicio, $fim, $unidade))->comissoesPorProfissional();
        $unidades = Unidade::where('ativo', true)->orderBy('nome')->get(['id', 'nome']);

        return view('livewire.painel.comissoes.index', [
            'inicio' => $inicio,
            'fim' => $fim,
            'comissoes' => $comissoes,
            'totalGeral' => (float) $comissoes->sum('total'),
            'unidades' => $unidades,
            'multiUnidade' => $unidades->count() >= 2,
            'profissionais' => User::where('e_profissional', true)->where('ativo', true)->orderBy('name')->get(['id', 'name']),
            'servicos' => Servico::where('ativo', true)->orderBy('nome')->get(['id', 'nome', 'percentual_comissao']),
            'produtos' => Produto::where('ativo', true)->orderBy('nome')->get(['id', 'nome', 'percentual_comissao']),
        ]);
    }
}
