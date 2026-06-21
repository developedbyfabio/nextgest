<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Models\Agendamento;
use App\Models\Configuracao;
use App\Services\Agendamento\Agendador;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Home do portal do cliente (mobile-first). Mostra os próximos agendamentos do
 * cliente logado e o acesso a "Novo agendamento". O Clube fica como placeholder.
 */
#[Layout('components.layouts.portal')]
class Home extends Component
{
    public function cancelar(int $id): void
    {
        $cliente = auth('cliente')->user();
        abort_unless($cliente !== null, 403);

        // Ownership: cliente só cancela o que é dele.
        $agendamento = Agendamento::where('cliente_id', $cliente->id)->findOrFail($id);

        $agendador = app(Agendador::class);

        if (! $agendador->podeCancelar($agendamento)) {
            Flux::toast('Não é possível cancelar com menos de algumas horas de antecedência.', variant: 'danger');

            return;
        }

        $agendador->cancelar($agendamento);
        Flux::toast('Agendamento cancelado.', variant: 'success');
    }

    public function render(): View
    {
        $cliente = auth('cliente')->user();

        $proximos = $cliente
            ? Agendamento::with(['profissional', 'unidade', 'itens.servico'])
                ->where('cliente_id', $cliente->id)
                ->whereIn('status', ['pendente', 'confirmado', 'em_andamento'])
                ->where('data_hora_inicio', '>=', Carbon::now())
                ->orderBy('data_hora_inicio')
                ->get()
            : collect();

        // Histórico: já finalizados (concluído/cancelado/não compareceu) ou no
        // passado. Limitado — é uma visão de acompanhamento, não relatório.
        $historico = $cliente
            ? Agendamento::with(['profissional', 'unidade', 'itens.servico'])
                ->where('cliente_id', $cliente->id)
                ->where(fn ($q) => $q
                    ->whereIn('status', ['concluido', 'cancelado', 'nao_compareceu'])
                    ->orWhere('data_hora_inicio', '<', Carbon::now()))
                ->orderByDesc('data_hora_inicio')
                ->limit(8)
                ->get()
            : collect();

        $agendador = app(Agendador::class);

        return view('livewire.portal.home', [
            'cliente' => $cliente,
            'proximos' => $proximos,
            'historico' => $historico,
            'podeCancelar' => fn (Agendamento $a) => $agendador->podeCancelar($a),
            'descricao' => Configuracao::valor('descricao'),
        ]);
    }
}
