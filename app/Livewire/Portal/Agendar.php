<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Models\Servico;
use App\Models\Unidade;
use App\Services\Agendamento\Agendador;
use App\Services\Agendamento\MotorDisponibilidade;
use App\Services\Agendamento\SlotIndisponivelException;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Wizard de "Novo agendamento" (portal do cliente, mobile-first):
 * (filial →) serviços → profissional → dia → horário → confirmar.
 *
 * Toda escolha é revalidada no servidor na confirmação (Agendador), então
 * manipular o estado no cliente não burla as regras.
 */
#[Layout('components.layouts.portal')]
#[Title('Novo agendamento')]
class Agendar extends Component
{
    public int $passo = 1;

    public ?int $unidadeId = null;

    /** @var array<int> */
    public array $servicoIds = [];

    /** 'sem' (sem preferência) ou o id do profissional como string. */
    public string $profissional = 'sem';

    public ?string $data = null;

    public ?string $slotHora = null;

    public ?int $slotProfissionalId = null;

    public function mount(): void
    {
        $unidades = Unidade::where('ativo', true)->orderBy('nome')->get();

        // Multi-unidade: com uma só filial, seleciona automático e pula a etapa.
        if ($unidades->count() === 1) {
            $this->unidadeId = $unidades->first()->id;
            $this->passo = 2;
        }

        $this->data = Carbon::now()->format('Y-m-d');
    }

    public function selecionarUnidade(int $id): void
    {
        $this->unidadeId = $id;
        $this->servicoIds = [];
        $this->limparSelecaoSlot();
        $this->passo = 2;
    }

    public function toggleServico(int $id): void
    {
        if (in_array($id, $this->servicoIds, true)) {
            $this->servicoIds = array_values(array_diff($this->servicoIds, [$id]));
        } else {
            $this->servicoIds[] = $id;
        }
        $this->limparSelecaoSlot();
    }

    public function irParaProfissional(): void
    {
        if (empty($this->servicoIds)) {
            Flux::toast('Escolha ao menos um serviço.', variant: 'warning');

            return;
        }
        $this->passo = 3;
    }

    public function selecionarProfissional(string $valor): void
    {
        $this->profissional = $valor;
        $this->limparSelecaoSlot();
        $this->passo = 4;
    }

    public function updatedData(): void
    {
        $this->limparSelecaoSlot();
    }

    public function selecionarSlot(string $hora, int $profissionalId): void
    {
        $this->slotHora = $hora;
        $this->slotProfissionalId = $profissionalId;
    }

    public function voltar(): void
    {
        $minimo = (Unidade::where('ativo', true)->count() === 1) ? 2 : 1;
        $this->passo = max($minimo, $this->passo - 1);
    }

    public function confirmar(Agendador $agendador)
    {
        $cliente = auth('cliente')->user();
        abort_unless($cliente !== null, 403);

        if ($this->slotHora === null || $this->slotProfissionalId === null || $this->unidadeId === null) {
            Flux::toast('Selecione um horário.', variant: 'warning');

            return;
        }

        $inicio = Carbon::parse($this->data.' '.$this->slotHora);

        try {
            $agendador->confirmar($cliente, $this->unidadeId, $this->servicoIds, $this->slotProfissionalId, $inicio);
        } catch (SlotIndisponivelException $e) {
            // Outro cliente pegou o horário: limpa a seleção e reapresenta os slots.
            $this->limparSelecaoSlot();
            Flux::toast($e->getMessage(), variant: 'danger');

            return;
        }

        Flux::toast('Agendamento confirmado!', variant: 'success');

        return $this->redirectRoute('tenant.home', ['tenant' => tenant('id')], navigate: true);
    }

    protected function limparSelecaoSlot(): void
    {
        $this->slotHora = null;
        $this->slotProfissionalId = null;
    }

    public function render(MotorDisponibilidade $motor): View
    {
        $servicosDisponiveis = $this->unidadeId
            ? Servico::where('ativo', true)
                ->whereHas('unidades', fn ($q) => $q->where('unidades.id', $this->unidadeId))
                ->orderBy('nome')->get()
            : collect();

        $servicosEscolhidos = $servicosDisponiveis->whereIn('id', $this->servicoIds);

        $profissionais = ($this->unidadeId && ! empty($this->servicoIds))
            ? $motor->profissionaisQueAtendem($this->unidadeId, $this->servicoIds)
            : collect();

        $horarios = ($this->passo === 4 && $this->unidadeId && ! empty($this->servicoIds) && $this->data)
            ? $motor->slots(
                $this->unidadeId,
                $this->servicoIds,
                $this->profissional === 'sem' ? null : (int) $this->profissional,
                Carbon::parse($this->data),
            )
            : collect();

        return view('livewire.portal.agendar', [
            'unidades' => Unidade::where('ativo', true)->orderBy('nome')->get(),
            'servicosDisponiveis' => $servicosDisponiveis,
            'profissionais' => $profissionais,
            'horarios' => $horarios,
            'duracaoTotal' => (int) $servicosEscolhidos->sum('duracao_minutos'),
            'valorTotal' => (float) $servicosEscolhidos->sum('preco'),
        ]);
    }
}
