<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Avaliacoes;

use App\Models\Agendamento;
use App\Models\Avaliacao;
use App\Models\Unidade;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * "Últimos serviços" (Operação): lista os atendimentos CONCLUÍDOS e a avaliação de
 * cada um (estrelas + comentário) ou "sem avaliação", com resumo e filtros.
 *
 * RBAC por PERMISSÃO (nunca por papel):
 *  - `ver_avaliacoes` (Dono/Gerente): TODOS os atendimentos do tenant, COM o nome do
 *    cliente, e o filtro por cliente.
 *  - `ver_avaliacoes_proprias` (Profissional): só os atendimentos DELE, ANÔNIMO —
 *    a query NEM carrega o cliente (anonimato real), sem filtro por cliente.
 *  - Sem nenhuma das duas: 403 (a aba nem aparece no menu).
 */
#[Layout('components.layouts.painel')]
#[Title('Últimos serviços')]
class Index extends Component
{
    use WithPagination;

    /** Filtro por cliente (busca por nome) — SÓ para quem vê tudo (Dono). */
    public string $filtroCliente = '';

    /** '', 'dia', 'semana', 'mes' — sobre a data do atendimento. */
    public string $filtroPeriodo = '';

    /** '', '1'..'5' — nota da avaliação (afeta só a lista). */
    public string $filtroNota = '';

    /** '', 'com', 'sem' — avaliação com/sem comentário (afeta só a lista). */
    public string $filtroComentario = '';

    public ?int $filtroUnidade = null;

    public function mount(): void
    {
        abort_unless(auth('web')->user()->canAny(['ver_avaliacoes', 'ver_avaliacoes_proprias']), 403);
    }

    /** Qualquer filtro alterado volta para a 1ª página. */
    public function updated(string $name): void
    {
        if (str_starts_with($name, 'filtro')) {
            $this->resetPage();
        }
    }

    /** Dono/Gerente: vê tudo (e o nome do cliente). Profissional puro: não. */
    protected function podeVerTudo(): bool
    {
        return auth('web')->user()->can('ver_avaliacoes');
    }

    /**
     * Atendimentos CONCLUÍDOS no escopo do usuário. Profissional puro → só os dele,
     * e SEM carregar o cliente (o nome não sai do banco). Dono → todos + cliente.
     */
    protected function escopo()
    {
        $with = ['itens.servico', 'profissional', 'unidade', 'avaliacao'];

        if ($this->podeVerTudo()) {
            $with[] = 'cliente';
        }

        return Agendamento::query()
            ->where('status', 'concluido')
            ->when(! $this->podeVerTudo(), fn ($q) => $q->where('profissional_id', auth('web')->id()))
            // Filtro por cliente: só faz sentido (e só é permitido) para quem vê tudo.
            ->when($this->podeVerTudo() && $this->filtroCliente !== '', fn ($q) => $q->whereHas('cliente', fn ($c) => $c->where('nome', 'like', '%'.$this->filtroCliente.'%')))
            ->when($this->filtroUnidade, fn ($q) => $q->where('unidade_id', $this->filtroUnidade))
            ->when($this->filtroPeriodo !== '', fn ($q) => $q->where('data_hora_inicio', '>=', match ($this->filtroPeriodo) {
                'semana' => now()->startOfWeek(),
                'mes' => now()->startOfMonth(),
                default => now()->startOfDay(),
            }))
            ->with($with);
    }

    /**
     * Filtros que afetam SÓ a lista (não o resumo): nota e com/sem comentário —
     * para a média/taxa do termômetro seguirem significativas no período.
     */
    protected function escopoLista()
    {
        return $this->escopo()
            ->when($this->filtroNota !== '', fn ($q) => $q->whereHas('avaliacao', fn ($a) => $a->where('nota', (int) $this->filtroNota)))
            ->when($this->filtroComentario === 'com', fn ($q) => $q->whereHas('avaliacao', fn ($a) => $a->whereNotNull('comentario')->where('comentario', '!=', '')))
            ->when($this->filtroComentario === 'sem', fn ($q) => $q->whereHas('avaliacao', fn ($a) => $a->where(fn ($x) => $x->whereNull('comentario')->orWhere('comentario', ''))));
    }

    /**
     * Resumo (termômetro) do MESMO escopo da lista (RBAC + filtros de escopo):
     * média de estrelas, nº de avaliações, atendimentos concluídos e taxa de
     * avaliação. Ancorado nos atendimentos do escopo (a média/contagem usam as
     * avaliações desses atendimentos).
     */
    protected function resumo(): array
    {
        $base = $this->escopo();

        $concluidos = (clone $base)->count();
        $avaliados = (clone $base)->whereHas('avaliacao')->count();
        $media = (float) Avaliacao::whereIn('agendamento_id', (clone $base)->select('id'))->avg('nota');

        return [
            'concluidos' => $concluidos,
            'avaliados' => $avaliados,
            'media' => round($media, 1),
            'taxa' => $concluidos > 0 ? (int) round($avaliados / $concluidos * 100) : 0,
        ];
    }

    public function render(): View
    {
        $atendimentos = $this->escopoLista()
            ->orderByDesc('data_hora_inicio')
            ->paginate(15);

        return view('livewire.painel.avaliacoes.index', [
            'atendimentos' => $atendimentos,
            'podeVerTudo' => $this->podeVerTudo(),
            'resumo' => $this->resumo(),
            'unidades' => Unidade::where('ativo', true)->orderBy('nome')->get(),
        ]);
    }
}
