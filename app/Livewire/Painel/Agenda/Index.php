<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Agenda;

use App\Models\Agendamento;
use App\Models\Unidade;
use App\Models\User;
use App\Models\Venda;
use App\Services\Agendamento\Agendador;
use App\Services\Agendamento\MotorDisponibilidade;
use App\Services\Agendamento\SlotIndisponivelException;
use App\Services\Agendamento\TransicaoInvalidaException;
use App\Services\Venda\Comanda;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Agenda da equipe. Visões de dia e semana, filtros e ações de status/remarcação.
 * Escopo por papel: ver_agenda vê todos; ver_agenda_propria (Profissional) vê só
 * os próprios. gerir_agenda libera criar/alterar status/cancelar/remarcar.
 */
#[Layout('components.layouts.painel')]
#[Title('Agenda')]
class Index extends Component
{
    use AuthorizesRequests;

    public string $visao = 'dia';

    public string $data;

    public ?int $filtroProfissional = null;

    public ?int $filtroUnidade = null;

    public ?string $filtroStatus = null;

    public ?int $detalheId = null;

    public bool $mostrarDetalhe = false;

    public bool $modoRemarcar = false;

    public ?string $remarcarData = null;

    public const STATUS_COR = [
        'pendente' => 'amber',
        'confirmado' => 'green',
        'em_andamento' => 'blue',
        'concluido' => 'teal',
        'cancelado' => 'red',
        'nao_compareceu' => 'orange',
    ];

    public const STATUS_LABEL = [
        'pendente' => 'Pendente',
        'confirmado' => 'Confirmado',
        'em_andamento' => 'Em andamento',
        'concluido' => 'Concluído',
        'cancelado' => 'Cancelado',
        'nao_compareceu' => 'Não compareceu',
    ];

    /**
     * Status alcançáveis pela troca manual (`mudarStatus`). 'concluido' fica DE FORA
     * de propósito: concluir só pelo "Finalizar atendimento" (que gera/abre a comanda),
     * garantindo "todo atendimento concluído tem comanda". Ver D70.
     */
    public const STATUS_VIA_MUDAR = ['confirmado', 'em_andamento', 'cancelado', 'nao_compareceu'];

    /** Cor semântica do status (barra de acento dos cartões). Não é tema da marca. */
    public const STATUS_HEX = [
        'pendente' => '#f59e0b',
        'confirmado' => '#22c55e',
        'em_andamento' => '#3b82f6',
        'concluido' => '#14b8a6',
        'cancelado' => '#ef4444',
        'nao_compareceu' => '#f97316',
    ];

    /** Falha ao carregar a lista (estado de erro recuperável). */
    public bool $erro = false;

    public function mount(): void
    {
        abort_unless(auth('web')->user()->canAny(['ver_agenda', 'ver_agenda_propria']), 403);

        $this->data = Carbon::now()->format('Y-m-d');

        // Profissional puro vê apenas a própria agenda.
        if (! $this->podeVerTodas()) {
            $this->filtroProfissional = auth('web')->id();
        }
    }

    protected function podeVerTodas(): bool
    {
        return auth('web')->user()->can('ver_agenda');
    }

    protected function podeGerir(): bool
    {
        return auth('web')->user()->can('gerir_agenda');
    }

    /** Pode finalizar/gerar a comanda deste atendimento (escopo de permissão). */
    protected function podeFinalizar(Agendamento $agendamento): bool
    {
        $user = auth('web')->user();
        $proprio = (int) $agendamento->profissional_id === (int) $user->id;

        return $user->can('criar_venda')
            || $user->can('gerir_agenda')
            || ($proprio && $user->can('finalizar_atendimento_proprio'));
    }

    public function irHoje(): void
    {
        $this->data = Carbon::now()->format('Y-m-d');
    }

    public function navegar(int $dir): void
    {
        $dias = $this->visao === 'semana' ? 7 : 1;
        $this->data = Carbon::parse($this->data)->addDays($dir * $dias)->format('Y-m-d');
    }

    public function abrirDetalhe(int $id): void
    {
        $this->detalheId = $this->escopo()->whereKey($id)->firstOrFail()->id;
        $this->modoRemarcar = false;
        $this->remarcarData = $this->data;
        $this->mostrarDetalhe = true;
    }

    public function mudarStatus(string $novo, Agendador $agendador): void
    {
        $this->authorize('gerir_agenda');

        // Conclusão NÃO passa por aqui: 'concluido' (e qualquer status fora da whitelist)
        // é rejeitado. Fecha a brecha de concluir sem comanda — concluir é só pelo
        // "Finalizar atendimento". Ver D70.
        if (! in_array($novo, self::STATUS_VIA_MUDAR, true)) {
            Flux::toast('Para concluir, use "Finalizar atendimento".', variant: 'danger');

            return;
        }

        $agendamento = $this->escopo()->whereKey($this->detalheId)->firstOrFail();

        try {
            $agendador->mudarStatus($agendamento, $novo);
        } catch (TransicaoInvalidaException $e) {
            Flux::toast($e->getMessage(), variant: 'danger');

            return;
        }

        Flux::toast('Status atualizado.', variant: 'success');
    }

    /** Abre o modal de confirmação de cancelamento (sem confirm nativo). */
    public function pedirCancelar(): void
    {
        $this->authorize('gerir_agenda');
        Flux::modal('cancelar-agendamento')->show();
    }

    /** Confirma o cancelamento (usa a mesma regra de transição do Agendador). */
    public function cancelarAgendamento(Agendador $agendador): void
    {
        $this->mudarStatus('cancelado', $agendador);
        Flux::modal('cancelar-agendamento')->close();
    }

    public function iniciarRemarcacao(): void
    {
        $this->authorize('gerir_agenda');
        $this->modoRemarcar = true;
    }

    public function cancelarRemarcacao(): void
    {
        $this->modoRemarcar = false;
    }

    public function confirmarRemarcacao(string $hora, Agendador $agendador): void
    {
        $this->authorize('gerir_agenda');
        $agendamento = $this->escopo()->whereKey($this->detalheId)->firstOrFail();

        $novoInicio = Carbon::parse($this->remarcarData.' '.$hora);

        try {
            $agendador->remarcar($agendamento, $novoInicio);
        } catch (SlotIndisponivelException $e) {
            Flux::toast($e->getMessage(), variant: 'danger');

            return;
        }

        $this->modoRemarcar = false;
        Flux::toast('Agendamento remarcado.', variant: 'success');
    }

    /**
     * "Finalizar atendimento": conclui o atendimento (se ainda não estiver) e
     * gera/abre a comanda dele (idempotente — reusa Comanda::apartirDeAgendamento),
     * levando ao detalhe. Cliente e profissional vêm travados do agendamento.
     *
     * Permissão: quem tem `criar_venda`/`gerir_agenda` (Dono/Gerente/Recepção)
     * finaliza qualquer um; o Profissional finaliza só os PRÓPRIOS atendimentos
     * (`finalizar_atendimento_proprio` + ser o profissional do agendamento). O
     * `escopo()` já restringe um Profissional aos próprios — a checagem abaixo é a
     * trava explícita.
     */
    public function finalizarAtendimento(Comanda $comanda, Agendador $agendador)
    {
        $agendamento = $this->escopo()->whereKey($this->detalheId)->firstOrFail();
        $user = auth('web')->user();
        $proprio = (int) $agendamento->profissional_id === (int) $user->id;

        abort_unless(
            $user->can('criar_venda')
                || $user->can('gerir_agenda')
                || ($proprio && $user->can('finalizar_atendimento_proprio')),
            403,
        );

        if (in_array($agendamento->status, ['cancelado', 'nao_compareceu'], true)) {
            Flux::toast('Não dá para finalizar um atendimento cancelado ou faltante.', variant: 'danger');

            return;
        }

        // Conclui (respeitando as transições) se ainda não estiver concluído.
        if ($agendamento->status !== 'concluido') {
            try {
                $agendador->mudarStatus($agendamento, 'concluido');
            } catch (TransicaoInvalidaException $e) {
                Flux::toast('Não foi possível concluir o atendimento.', variant: 'danger');

                return;
            }
        }

        $venda = $comanda->apartirDeAgendamento($agendamento, $user->id);

        return $this->redirectRoute('painel.vendas.detalhe', ['tenant' => tenant('id'), 'venda' => $venda->id], navigate: true);
    }

    #[On('agenda-atualizada')]
    public function atualizar(): void
    {
        // Re-render (ex.: após criar um agendamento manual no componente filho).
    }

    /**
     * Query base já escopada por papel e filtros (exceto intervalo de data).
     */
    protected function escopo()
    {
        return Agendamento::query()
            ->when(! $this->podeVerTodas(), fn ($q) => $q->where('profissional_id', auth('web')->id()))
            ->when($this->podeVerTodas() && $this->filtroProfissional, fn ($q) => $q->where('profissional_id', $this->filtroProfissional))
            ->when($this->filtroUnidade, fn ($q) => $q->where('unidade_id', $this->filtroUnidade))
            ->when($this->filtroStatus, fn ($q) => $q->where('status', $this->filtroStatus));
    }

    public function render(MotorDisponibilidade $motor): View
    {
        $inicio = Carbon::parse($this->data);

        if ($this->visao === 'semana') {
            $de = $inicio->copy()->startOfWeek(Carbon::MONDAY);
            $ate = $inicio->copy()->endOfWeek(Carbon::SUNDAY);
        } else {
            $de = $inicio->copy()->startOfDay();
            $ate = $inicio->copy()->endOfDay();
        }

        // Carregamento da lista resiliente (estado de erro recuperável na UI).
        try {
            $this->erro = false;
            $agendamentos = $this->escopo()
                ->with(['cliente', 'profissional', 'unidade', 'itens.servico'])
                ->whereBetween('data_hora_inicio', [$de, $ate])
                ->orderBy('data_hora_inicio')
                ->get();
        } catch (\Throwable $e) {
            report($e);
            $this->erro = true;
            $agendamentos = collect();
        }

        $detalhe = $this->detalheId
            ? $this->escopo()->with(['cliente', 'profissional', 'unidade', 'itens.servico'])->find($this->detalheId)
            : null;

        // Comanda já existente do atendimento + se o usuário pode finalizar/gerar.
        $comandaDoDetalhe = $detalhe
            ? Venda::where('agendamento_id', $detalhe->id)->where('status', '!=', 'cancelada')->value('id')
            : null;
        $podeFinalizar = $detalhe
            && $this->podeFinalizar($detalhe)
            && ! in_array($detalhe->status, ['cancelado', 'nao_compareceu'], true);

        // Slots para remarcação (mesmo profissional e serviços, ignorando o próprio).
        $horariosRemarcar = ($detalhe && $this->modoRemarcar && $this->remarcarData)
            ? $motor->slots(
                $detalhe->unidade_id,
                $detalhe->itens->pluck('servico_id')->all(),
                $detalhe->profissional_id,
                Carbon::parse($this->remarcarData),
                ignorarAgendamentoId: $detalhe->id,
            )
            : collect();

        return view('livewire.painel.agenda.index', [
            'agendamentos' => $agendamentos,
            'detalhe' => $detalhe,
            'comandaDoDetalhe' => $comandaDoDetalhe,
            'podeFinalizar' => $podeFinalizar,
            'horariosRemarcar' => $horariosRemarcar,
            'diasSemana' => $this->visao === 'semana' ? $this->montarSemana($de) : [],
            'podeVerTodas' => $this->podeVerTodas(),
            'podeGerir' => $this->podeGerir(),
            'profissionais' => User::where('e_profissional', true)->where('ativo', true)->orderBy('name')->get(),
            'unidades' => Unidade::where('ativo', true)->orderBy('nome')->get(),
            'statusCor' => self::STATUS_COR,
            'statusLabel' => self::STATUS_LABEL,
            'statusHex' => self::STATUS_HEX,
        ]);
    }

    /** @return array<int, Carbon> */
    protected function montarSemana(Carbon $de): array
    {
        return collect(range(0, 6))->map(fn ($i) => $de->copy()->addDays($i))->all();
    }
}
