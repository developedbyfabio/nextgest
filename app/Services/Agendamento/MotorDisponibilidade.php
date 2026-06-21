<?php

declare(strict_types=1);

namespace App\Services\Agendamento;

use App\Models\Agendamento;
use App\Models\Bloqueio;
use App\Models\Configuracao;
use App\Models\HorarioTrabalho;
use App\Models\Servico;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Motor de disponibilidade: calcula os horários livres para um conjunto de
 * serviços, numa unidade, num dia — opcionalmente para um profissional ou
 * "sem preferência" (qualquer profissional que atenda).
 *
 * Regras (ver prompt 1C/B):
 *  - duração total = soma das durações dos serviços;
 *  - janelas = horarios_trabalho do profissional naquela unidade e dia (várias
 *    faixas/dia — o slot precisa caber inteiro em UMA faixa, respeitando almoço);
 *  - subtrai agendamentos ocupantes (status ≠ cancelado/nao_compareceu) e bloqueios;
 *  - slots a cada `intervalo_slots_minutos` (config, padrão 15);
 *  - não oferta horário no passado (para hoje).
 */
class MotorDisponibilidade
{
    /**
     * `Funcionamento` é a CAMADA de horário do estabelecimento (semanal + exceções)
     * aplicada por cima das janelas dos profissionais. Resolvida pelo container.
     */
    public function __construct(private readonly Funcionamento $funcionamento) {}

    /**
     * Lista de slots livres. Cada item: ['hora' => 'HH:MM', 'profissional_id' => int,
     * 'inicio' => Carbon]. Para "sem preferência", cada hora traz o primeiro
     * profissional disponível.
     */
    public function slots(int $unidadeId, array $servicoIds, ?int $profissionalId, Carbon $data, ?int $ignorarAgendamentoId = null): Collection
    {
        $servicoIds = $this->normalizarServicos($servicoIds);
        $duracao = $this->duracaoTotal($servicoIds);

        if ($duracao <= 0) {
            return collect();
        }

        // Data anterior a hoje não oferta nada.
        if ($data->copy()->endOfDay()->isPast()) {
            return collect();
        }

        // CAMADA de funcionamento: dia fechado (semanal/exceção) → nada; aberto →
        // restringe à faixa; sem_config → permissivo (comportamento anterior).
        $func = $this->funcionamento->doDia($data);
        if ($func['estado'] === Funcionamento::FECHADO) {
            return collect();
        }
        $gateIni = isset($func['inicio']) ? $data->copy()->setTimeFromTimeString($func['inicio']) : null;
        $gateFim = isset($func['fim']) ? $data->copy()->setTimeFromTimeString($func['fim']) : null;

        $profissionais = $profissionalId
            ? $this->filtrarProfissional($profissionalId, $unidadeId, $servicoIds)
            : $this->profissionaisQueAtendem($unidadeId, $servicoIds);

        if ($profissionais->isEmpty()) {
            return collect();
        }

        $intervalo = max(5, Configuracao::inteiro('intervalo_slots_minutos', 15));
        $agora = Carbon::now();
        $ehHoje = $data->isSameDay($agora);
        $diaSemana = (int) $data->dayOfWeek;

        $porHora = [];

        foreach ($profissionais as $prof) {
            $janelas = HorarioTrabalho::where('user_id', $prof->id)
                ->where('unidade_id', $unidadeId)
                ->where('dia_semana', $diaSemana)
                ->get();

            if ($janelas->isEmpty()) {
                continue;
            }

            $ocupados = $this->intervalosOcupados($prof->id, $data, $ignorarAgendamentoId);

            foreach ($janelas as $janela) {
                $faixaFim = $data->copy()->setTimeFromTimeString($janela->hora_fim);
                $cursor = $data->copy()->setTimeFromTimeString($janela->hora_inicio);

                // Recorta a janela do profissional à faixa de funcionamento do dia.
                if ($gateIni && $cursor->lt($gateIni)) {
                    $cursor = $gateIni->copy();
                }
                if ($gateFim && $faixaFim->gt($gateFim)) {
                    $faixaFim = $gateFim->copy();
                }

                while ($cursor->copy()->addMinutes($duracao)->lte($faixaFim)) {
                    $inicio = $cursor->copy();
                    $fim = $inicio->copy()->addMinutes($duracao);

                    if ($this->slotLivre($inicio, $fim, $ocupados, $ehHoje, $agora)) {
                        $chave = $inicio->format('H:i');
                        $porHora[$chave] ??= [
                            'hora' => $chave,
                            'profissional_id' => $prof->id,
                            'inicio' => $inicio,
                        ];
                    }

                    $cursor->addMinutes($intervalo);
                }
            }
        }

        ksort($porHora);

        return collect(array_values($porHora));
    }

    /**
     * Revalida que um início específico é agendável para um profissional.
     * Usado pelo Agendador dentro da transação (com lock).
     */
    public function slotValido(int $unidadeId, array $servicoIds, int $profissionalId, Carbon $inicio, ?int $ignorarAgendamentoId = null): bool
    {
        $servicoIds = $this->normalizarServicos($servicoIds);
        $duracao = $this->duracaoTotal($servicoIds);

        if ($duracao <= 0) {
            return false;
        }

        // Profissional ativo, profissional, atende a unidade e faz TODOS os serviços.
        if ($this->filtrarProfissional($profissionalId, $unidadeId, $servicoIds)->isEmpty()) {
            return false;
        }

        // Todos os serviços pertencem à unidade.
        $naUnidade = Servico::whereIn('id', $servicoIds)
            ->whereHas('unidades', fn ($q) => $q->where('unidades.id', $unidadeId))
            ->count();
        if ($naUnidade !== count($servicoIds)) {
            return false;
        }

        $fim = $inicio->copy()->addMinutes($duracao);

        return $this->intervaloAgendavel($unidadeId, $profissionalId, $inicio, $fim, $ignorarAgendamentoId);
    }

    /**
     * Um intervalo [inicio, fim] é agendável para o profissional: não está no
     * passado, cabe inteiro numa janela de trabalho do dia, e não colide com
     * agendamentos/bloqueios (ignorando opcionalmente um agendamento — útil ao
     * remarcar o próprio).
     */
    public function intervaloAgendavel(int $unidadeId, int $profissionalId, Carbon $inicio, Carbon $fim, ?int $ignorarAgendamentoId = null): bool
    {
        if ($inicio->lte(Carbon::now())) {
            return false;
        }

        // CAMADA de funcionamento: respeita dia fechado / faixa especial do dia.
        $func = $this->funcionamento->doDia($inicio);
        if ($func['estado'] === Funcionamento::FECHADO) {
            return false;
        }
        if ($func['estado'] === Funcionamento::ABERTO) {
            $gi = $inicio->copy()->setTimeFromTimeString($func['inicio']);
            $gf = $inicio->copy()->setTimeFromTimeString($func['fim']);
            if ($inicio->lt($gi) || $fim->gt($gf)) {
                return false;
            }
        }

        $cabe = HorarioTrabalho::where('user_id', $profissionalId)
            ->where('unidade_id', $unidadeId)
            ->where('dia_semana', (int) $inicio->dayOfWeek)
            ->get()
            ->contains(function ($janela) use ($inicio, $fim) {
                $fi = $inicio->copy()->setTimeFromTimeString($janela->hora_inicio);
                $ff = $inicio->copy()->setTimeFromTimeString($janela->hora_fim);

                return $inicio->gte($fi) && $fim->lte($ff);
            });
        if (! $cabe) {
            return false;
        }

        foreach ($this->intervalosOcupados($profissionalId, $inicio, $ignorarAgendamentoId) as [$oi, $of]) {
            if ($inicio->lt($of) && $oi->lt($fim)) {
                return false;
            }
        }

        return true;
    }

    public function duracaoTotal(array $servicoIds): int
    {
        $servicoIds = $this->normalizarServicos($servicoIds);

        if (empty($servicoIds)) {
            return 0;
        }

        return (int) Servico::whereIn('id', $servicoIds)->sum('duracao_minutos');
    }

    /**
     * Profissionais (ativos) que atendem na unidade e fazem TODOS os serviços.
     */
    public function profissionaisQueAtendem(int $unidadeId, array $servicoIds): Collection
    {
        $servicoIds = $this->normalizarServicos($servicoIds);

        if (empty($servicoIds)) {
            return collect();
        }

        return User::query()
            ->where('ativo', true)
            ->where('e_profissional', true)
            ->whereHas('unidades', fn ($q) => $q->where('unidades.id', $unidadeId))
            ->whereHas('servicos', fn ($q) => $q->whereIn('servicos.id', $servicoIds), '=', count($servicoIds))
            ->orderBy('name')
            ->get();
    }

    /** @return array<int, array{0: Carbon, 1: Carbon}> */
    protected function intervalosOcupados(int $profissionalId, Carbon $data, ?int $ignorarAgendamentoId = null): array
    {
        $inicioDia = $data->copy()->startOfDay();
        $fimDia = $data->copy()->endOfDay();

        $intervalos = [];

        Agendamento::query()
            ->where('profissional_id', $profissionalId)
            ->ocupantes()
            ->when($ignorarAgendamentoId, fn ($q) => $q->whereKeyNot($ignorarAgendamentoId))
            ->where('data_hora_inicio', '<', $fimDia)
            ->where('data_hora_fim', '>', $inicioDia)
            ->get(['data_hora_inicio', 'data_hora_fim'])
            ->each(function ($a) use (&$intervalos) {
                $intervalos[] = [$a->data_hora_inicio, $a->data_hora_fim];
            });

        Bloqueio::query()
            ->where('user_id', $profissionalId)
            ->where('inicio', '<', $fimDia)
            ->where('fim', '>', $inicioDia)
            ->get(['inicio', 'fim'])
            ->each(function ($b) use (&$intervalos) {
                $intervalos[] = [$b->inicio, $b->fim];
            });

        return $intervalos;
    }

    protected function slotLivre(Carbon $inicio, Carbon $fim, array $ocupados, bool $ehHoje, Carbon $agora): bool
    {
        if ($ehHoje && $inicio->lte($agora)) {
            return false;
        }

        foreach ($ocupados as [$oi, $of]) {
            if ($inicio->lt($of) && $oi->lt($fim)) {
                return false;
            }
        }

        return true;
    }

    protected function filtrarProfissional(int $profissionalId, int $unidadeId, array $servicoIds): Collection
    {
        return User::query()
            ->where('id', $profissionalId)
            ->where('ativo', true)
            ->where('e_profissional', true)
            ->whereHas('unidades', fn ($q) => $q->where('unidades.id', $unidadeId))
            ->whereHas('servicos', fn ($q) => $q->whereIn('servicos.id', $servicoIds), '=', count($servicoIds))
            ->get();
    }

    /** @return array<int, int> */
    protected function normalizarServicos(array $servicoIds): array
    {
        return array_values(array_unique(array_map('intval', $servicoIds)));
    }
}
