<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Models\Agendamento;
use App\Models\Avaliacao;
use App\Models\Configuracao;
use App\Services\Agendamento\Agendador;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Home do portal do cliente (mobile-first). Mostra os próximos agendamentos do
 * cliente logado, o histórico e o acesso a "Novo agendamento".
 *
 * Avaliações (D51, coleta): após um atendimento CONCLUÍDO, o cliente avalia
 * (1–5 + comentário). A captura acontece por POPUP (aparece uma vez ao carregar)
 * e pelo HISTÓRICO (botão "Avaliar"). Os dois usam o MESMO modal/ação.
 */
#[Layout('components.layouts.portal')]
class Home extends Component
{
    /** Agendamento aguardando confirmação de cancelamento (modal). */
    public ?int $cancelandoId = null;

    // --- Avaliação ---
    public bool $mostrarAvaliacao = false;

    /** Agendamento (atendimento) sendo avaliado no modal. */
    public ?int $avaliandoId = null;

    public ?int $nota = null;

    public string $comentario = '';

    /**
     * Ao carregar: se houver um atendimento concluído AVALIÁVEL cujo popup ainda
     * não foi exibido, abre o modal (uma vez) e marca o popup como exibido. Ignorar
     * não cria avaliação; o atendimento segue avaliável pelo histórico.
     */
    public function mount(): void
    {
        $cliente = auth('cliente')->user();

        if (! $cliente) {
            return;
        }

        $pendente = Agendamento::where('cliente_id', $cliente->id)
            ->where('status', 'concluido')
            ->whereNull('avaliacao_popup_exibido_em')
            ->whereDoesntHave('avaliacao')
            ->orderByDesc('data_hora_inicio')
            ->first();

        if ($pendente) {
            $pendente->forceFill(['avaliacao_popup_exibido_em' => now()])->save();
            $this->avaliandoId = $pendente->id;
            $this->mostrarAvaliacao = true;
        }
    }

    /** Abre o modal de avaliação para um atendimento (histórico). */
    public function abrirAvaliacao(int $id): void
    {
        $this->avaliacaoAvaliavel($id); // valida posse + concluído + não avaliado

        $this->avaliandoId = $id;
        $this->nota = null;
        $this->comentario = '';
        $this->resetValidation();
        $this->mostrarAvaliacao = true;
    }

    /** Fecha o modal sem criar avaliação (o popup já foi marcado como exibido). */
    public function ignorarAvaliacao(): void
    {
        $this->mostrarAvaliacao = false;
        $this->reset(['avaliandoId', 'nota', 'comentario']);
    }

    /** Salva a avaliação do atendimento (1 atendimento = 1 avaliação). */
    public function salvarAvaliacao(): void
    {
        $this->validate([
            'nota' => ['required', 'integer', 'between:1,5'],
            'comentario' => ['nullable', 'string', 'max:1000'],
        ], attributes: ['nota' => 'nota', 'comentario' => 'comentário']);

        $agendamento = $this->avaliacaoAvaliavel((int) $this->avaliandoId);

        Avaliacao::create([
            'agendamento_id' => $agendamento->id,
            'cliente_id' => $agendamento->cliente_id,
            'profissional_id' => $agendamento->profissional_id,
            'unidade_id' => $agendamento->unidade_id,
            'nota' => $this->nota,
            'comentario' => $this->comentario !== '' ? $this->comentario : null,
        ]);

        $this->mostrarAvaliacao = false;
        $this->reset(['avaliandoId', 'nota', 'comentario']);
        Flux::toast('Obrigado pela avaliação!', variant: 'success');
    }

    /**
     * Garante que o atendimento é DO cliente logado, está CONCLUÍDO e ainda NÃO foi
     * avaliado. Aborta (404) caso contrário — protege contra avaliar atendimento
     * alheio, não concluído ou já avaliado.
     */
    protected function avaliacaoAvaliavel(int $id): Agendamento
    {
        $cliente = auth('cliente')->user();
        abort_unless($cliente !== null, 403);

        return Agendamento::where('cliente_id', $cliente->id)
            ->where('status', 'concluido')
            ->whereDoesntHave('avaliacao')
            ->findOrFail($id);
    }

    /** Abre o modal de confirmação de cancelamento (UI bonita, sem confirm nativo). */
    public function pedirCancelamento(int $id): void
    {
        $this->cancelandoId = $id;
        Flux::modal('cancelar-agendamento')->show();
    }

    public function cancelar(int $id): void
    {
        $cliente = auth('cliente')->user();
        abort_unless($cliente !== null, 403);

        // Ownership: cliente só cancela o que é dele.
        $agendamento = Agendamento::where('cliente_id', $cliente->id)->findOrFail($id);

        $agendador = app(Agendador::class);

        if (! $agendador->podeCancelar($agendamento)) {
            Flux::modal('cancelar-agendamento')->close();
            $this->cancelandoId = null;
            Flux::toast('Não é possível cancelar com menos de algumas horas de antecedência.', variant: 'danger');

            return;
        }

        $agendador->cancelar($agendamento);
        Flux::modal('cancelar-agendamento')->close();
        $this->cancelandoId = null;
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
        // passado. Carrega a avaliação (se houver) para exibir nota ou o botão Avaliar.
        $historico = $cliente
            ? Agendamento::with(['profissional', 'unidade', 'itens.servico', 'avaliacao'])
                ->where('cliente_id', $cliente->id)
                ->where(fn ($q) => $q
                    ->whereIn('status', ['concluido', 'cancelado', 'nao_compareceu'])
                    ->orWhere('data_hora_inicio', '<', Carbon::now()))
                ->orderByDesc('data_hora_inicio')
                ->limit(8)
                ->get()
            : collect();

        // Atendimento aberto no modal de avaliação (para mostrar serviço/data/profissional).
        $avaliando = ($cliente && $this->avaliandoId)
            ? Agendamento::with(['profissional', 'itens.servico'])
                ->where('cliente_id', $cliente->id)
                ->find($this->avaliandoId)
            : null;

        $agendador = app(Agendador::class);

        return view('livewire.portal.home', [
            'cliente' => $cliente,
            'proximos' => $proximos,
            'historico' => $historico,
            'avaliando' => $avaliando,
            'podeCancelar' => fn (Agendamento $a) => $agendador->podeCancelar($a),
            'descricao' => Configuracao::valor('descricao'),
        ]);
    }
}
