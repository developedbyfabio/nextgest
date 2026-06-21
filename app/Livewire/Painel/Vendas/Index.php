<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Vendas;

use App\Models\Cliente;
use App\Models\Unidade;
use App\Models\User;
use App\Models\Venda;
use App\Services\Venda\Comanda;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Lista de comandas/vendas (Fatia 2B). Filtros por status/período/unidade e busca
 * por cliente. "Nova comanda" abre uma venda avulsa (balcão) e leva ao detalhe.
 * Permissão: criar_venda (Dono/Gerente/Recepção).
 */
#[Layout('components.layouts.painel')]
#[Title('Comandas')]
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public string $busca = '';

    public string $statusFiltro = 'todas'; // todas | aberta | paga | cancelada

    public string $periodo = '30d'; // hoje | 7d | 30d | todos

    public ?string $unidadeFiltro = '';

    // Modal nova comanda.
    public bool $mostrarNova = false;

    public ?string $novaUnidadeId = null;

    public ?string $novaClienteId = null;

    public ?string $novaProfissionalId = null; // "quem vendeu" — pré-preenche os itens

    public function mount(): void
    {
        $this->authorize('criar_venda');
        $primeira = Unidade::where('ativo', true)->orderBy('nome')->first();
        $this->novaUnidadeId = $primeira ? (string) $primeira->id : null;
    }

    public function updatedBusca(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFiltro(): void
    {
        $this->resetPage();
    }

    public function updatedPeriodo(): void
    {
        $this->resetPage();
    }

    public function updatedUnidadeFiltro(): void
    {
        $this->resetPage();
    }

    public function novaComanda(): void
    {
        $this->authorize('criar_venda');
        $this->reset(['novaClienteId', 'novaProfissionalId']);
        $this->resetValidation();
        $this->mostrarNova = true;
    }

    public function criar(Comanda $comanda)
    {
        $this->authorize('criar_venda');

        $dados = $this->validate([
            'novaUnidadeId' => ['required', 'integer', 'exists:unidades,id'],
            'novaClienteId' => ['nullable', 'integer', 'exists:clientes,id'],
            'novaProfissionalId' => ['nullable', 'integer', 'exists:users,id'],
        ], attributes: ['novaUnidadeId' => 'unidade', 'novaClienteId' => 'cliente', 'novaProfissionalId' => 'profissional']);

        $venda = $comanda->abrir(
            (int) $dados['novaUnidadeId'],
            $dados['novaClienteId'] ? (int) $dados['novaClienteId'] : null,
            auth('web')->id(),
            $dados['novaProfissionalId'] ? (int) $dados['novaProfissionalId'] : null,
        );

        return $this->redirectRoute('painel.vendas.detalhe', ['tenant' => tenant('id'), 'venda' => $venda->id], navigate: true);
    }

    private function intervalo(): ?array
    {
        $hoje = Carbon::today();

        return match ($this->periodo) {
            'hoje' => [$hoje->copy()->startOfDay(), $hoje->copy()->endOfDay()],
            '7d' => [$hoje->copy()->subDays(6)->startOfDay(), $hoje->copy()->endOfDay()],
            '30d' => [$hoje->copy()->subDays(29)->startOfDay(), $hoje->copy()->endOfDay()],
            default => null,
        };
    }

    public function render(): View
    {
        $intervalo = $this->intervalo();

        $vendas = Venda::query()
            ->with(['cliente:id,nome', 'unidade:id,nome'])
            ->when($this->statusFiltro !== 'todas', fn ($q) => $q->where('status', $this->statusFiltro))
            ->when($this->unidadeFiltro, fn ($q) => $q->where('unidade_id', (int) $this->unidadeFiltro))
            ->when($intervalo, fn ($q) => $q->whereBetween('data', $intervalo))
            ->when($this->busca !== '', fn ($q) => $q->whereHas('cliente', fn ($c) => $c->where('nome', 'like', '%'.$this->busca.'%')))
            ->orderByDesc('data')
            ->paginate(12);

        return view('livewire.painel.vendas.index', [
            'vendas' => $vendas,
            'unidades' => Unidade::where('ativo', true)->orderBy('nome')->get(),
            'clientes' => Cliente::orderBy('nome')->get(['id', 'nome']),
            'profissionais' => User::where('e_profissional', true)->where('ativo', true)->orderBy('name')->get(['id', 'name']),
            'multiUnidade' => Unidade::where('ativo', true)->count() >= 2,
        ]);
    }
}
