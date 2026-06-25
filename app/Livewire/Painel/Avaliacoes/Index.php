<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Avaliacoes;

use App\Models\Agendamento;
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

    public function mount(): void
    {
        abort_unless(auth('web')->user()->canAny(['ver_avaliacoes', 'ver_avaliacoes_proprias']), 403);
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
            ->with($with);
    }

    public function render(): View
    {
        $atendimentos = $this->escopo()
            ->orderByDesc('data_hora_inicio')
            ->paginate(15);

        return view('livewire.painel.avaliacoes.index', [
            'atendimentos' => $atendimentos,
            'podeVerTudo' => $this->podeVerTudo(),
        ]);
    }
}
