<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Agenda;

use App\Models\Cliente;
use App\Models\Servico;
use App\Models\Unidade;
use App\Rules\CelularBr;
use App\Services\Agendamento\Agendador;
use App\Services\Agendamento\MotorDisponibilidade;
use App\Services\Agendamento\SlotIndisponivelException;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * Novo agendamento manual (painel). Seleciona/cria cliente e segue o wizard
 * (unidade → serviços → profissional → dia/hora), gravando via Agendador
 * (origem=equipe, criado_por_user_id). Emite "agenda-atualizada" ao concluir.
 */
class NovoAgendamento extends Component
{
    use AuthorizesRequests;

    public bool $mostrar = false;

    public int $passo = 1; // 1 cliente, 2 unidade, 3 serviços, 4 profissional, 5 horário

    // Cliente
    public string $buscaCliente = '';

    public ?int $clienteId = null;

    public string $clienteNome = '';

    public string $novoNome = '';

    public string $novoTelefone = '';

    // Agendamento
    public ?int $unidadeId = null;

    /** @var array<int> */
    public array $servicoIds = [];

    public string $profissional = 'sem';

    public ?string $data = null;

    public ?string $slotHora = null;

    public ?int $slotProfissionalId = null;

    public function abrir(): void
    {
        $this->authorize('gerir_agenda');
        $this->resetarFormulario();
        $this->data = Carbon::now()->format('Y-m-d');

        $unidades = Unidade::where('ativo', true)->orderBy('nome')->get();
        if ($unidades->count() === 1) {
            $this->unidadeId = $unidades->first()->id;
        }

        $this->mostrar = true;
    }

    public function selecionarCliente(int $id): void
    {
        $cliente = Cliente::findOrFail($id);
        $this->clienteId = $cliente->id;
        $this->clienteNome = $cliente->nome;
        $this->buscaCliente = '';
        $this->irAposCliente();
    }

    public function criarCliente(): void
    {
        // Telefone segue OBRIGATÓRIO no walk-in (cliente presente), mas agora com a
        // MESMA regra do resto (CelularBr) e gravado em dígitos — a máscara é do input,
        // o WhatsApp/EvolutionGateway prefixa 55 no envio. (D98)
        $this->novoTelefone = preg_replace('/\D+/', '', $this->novoTelefone);

        $dados = $this->validate([
            'novoNome' => ['required', 'string', 'max:255'],
            'novoTelefone' => ['required', 'string', new CelularBr],
        ], attributes: ['novoNome' => 'nome', 'novoTelefone' => 'telefone']);

        $cliente = Cliente::create(['nome' => $dados['novoNome'], 'telefone' => $dados['novoTelefone']]);
        $this->clienteId = $cliente->id;
        $this->clienteNome = $cliente->nome;
        $this->reset(['novoNome', 'novoTelefone']);
        $this->irAposCliente();
    }

    protected function irAposCliente(): void
    {
        // Com unidade única, pula direto aos serviços.
        $this->passo = $this->unidadeId ? 3 : 2;
    }

    public function selecionarUnidade(int $id): void
    {
        $this->unidadeId = $id;
        $this->servicoIds = [];
        $this->limparSlot();
        $this->passo = 3;
    }

    public function toggleServico(int $id): void
    {
        if (in_array($id, $this->servicoIds, true)) {
            $this->servicoIds = array_values(array_diff($this->servicoIds, [$id]));
        } else {
            $this->servicoIds[] = $id;
        }
        $this->limparSlot();
    }

    public function irParaProfissional(): void
    {
        if (empty($this->servicoIds)) {
            Flux::toast('Escolha ao menos um serviço.', variant: 'warning');

            return;
        }
        $this->passo = 4;
    }

    public function selecionarProfissional(string $valor): void
    {
        $this->profissional = $valor;
        $this->limparSlot();
        $this->passo = 5;
    }

    public function updatedData(): void
    {
        $this->limparSlot();
    }

    public function selecionarSlot(string $hora, int $profissionalId): void
    {
        $this->slotHora = $hora;
        $this->slotProfissionalId = $profissionalId;
    }

    public function voltar(): void
    {
        $minimo = $this->unidadeId && Unidade::where('ativo', true)->count() === 1 ? 1 : 1;
        // Da etapa de serviços (3), com unidade única, volta para cliente (1).
        if ($this->passo === 3 && Unidade::where('ativo', true)->count() === 1) {
            $this->passo = 1;

            return;
        }
        $this->passo = max($minimo, $this->passo - 1);
    }

    public function confirmar(Agendador $agendador)
    {
        $this->authorize('gerir_agenda');

        if (! $this->clienteId || ! $this->unidadeId || ! $this->slotHora || ! $this->slotProfissionalId) {
            Flux::toast('Complete os passos do agendamento.', variant: 'warning');

            return;
        }

        $inicio = Carbon::parse($this->data.' '.$this->slotHora);

        try {
            $agendador->agendarPelaEquipe(
                $this->clienteId,
                $this->unidadeId,
                $this->servicoIds,
                $this->slotProfissionalId,
                $inicio,
                auth('web')->id(),
            );
        } catch (SlotIndisponivelException $e) {
            $this->limparSlot();
            Flux::toast($e->getMessage(), variant: 'danger');

            return;
        }

        $this->mostrar = false;
        Flux::toast('Agendamento criado.', variant: 'success');
        $this->dispatch('agenda-atualizada');
    }

    protected function limparSlot(): void
    {
        $this->slotHora = null;
        $this->slotProfissionalId = null;
    }

    protected function resetarFormulario(): void
    {
        $this->reset([
            'passo', 'buscaCliente', 'clienteId', 'clienteNome', 'novoNome', 'novoTelefone',
            'unidadeId', 'servicoIds', 'profissional', 'slotHora', 'slotProfissionalId',
        ]);
        $this->resetValidation();
    }

    public function render(MotorDisponibilidade $motor): View
    {
        $clientes = strlen($this->buscaCliente) >= 2
            ? Cliente::where('nome', 'like', "%{$this->buscaCliente}%")
                ->orWhere('telefone', 'like', "%{$this->buscaCliente}%")
                ->orderBy('nome')->limit(8)->get()
            : collect();

        $servicosDisponiveis = $this->unidadeId
            ? Servico::where('ativo', true)
                ->whereHas('unidades', fn ($q) => $q->where('unidades.id', $this->unidadeId))
                ->orderBy('nome')->get()
            : collect();

        $servicosEscolhidos = $servicosDisponiveis->whereIn('id', $this->servicoIds);

        $profissionais = ($this->unidadeId && ! empty($this->servicoIds))
            ? $motor->profissionaisQueAtendem($this->unidadeId, $this->servicoIds)
            : collect();

        $horarios = ($this->passo === 5 && $this->unidadeId && ! empty($this->servicoIds) && $this->data)
            ? $motor->slots($this->unidadeId, $this->servicoIds, $this->profissional === 'sem' ? null : (int) $this->profissional, Carbon::parse($this->data))
            : collect();

        return view('livewire.painel.agenda.novo-agendamento', [
            'clientes' => $clientes,
            'unidades' => Unidade::where('ativo', true)->orderBy('nome')->get(),
            'servicosDisponiveis' => $servicosDisponiveis,
            'profissionais' => $profissionais,
            'horarios' => $horarios,
            'duracaoTotal' => (int) $servicosEscolhidos->sum('duracao_minutos'),
            'valorTotal' => (float) $servicosEscolhidos->sum('preco'),
        ]);
    }
}
