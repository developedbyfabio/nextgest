<?php

declare(strict_types=1);

namespace App\Services\Agendamento;

use App\Models\Configuracao;
use App\Models\ExcecaoFuncionamento;
use Carbon\Carbon;

/**
 * Funcionamento do ESTABELECIMENTO num dia — camada de horário consultada pelo
 * MotorDisponibilidade SEM reescrevê-lo. Combina:
 *  1) exceções por data (`excecoes_funcionamento`) — feriado/fechamento/horário
 *     especial — que têm PRECEDÊNCIA;
 *  2) o horário semanal (Configuracao `horario_funcionamento`, do onboarding/painel).
 *
 * Retorno de `doDia()`:
 *  - ['estado' => 'fechado']                                   → não oferta nada;
 *  - ['estado' => 'aberto', 'inicio' => 'HH:MM', 'fim' => 'HH:MM'] → restringe à faixa;
 *  - ['estado' => 'sem_config']                                → PERMISSIVO (não há
 *    horário configurado → o motor segue como antes, só com HorarioTrabalho/bloqueios).
 *
 * O modo permissivo preserva a disponibilidade de tenants/testes sem horário.
 */
class Funcionamento
{
    public const FECHADO = 'fechado';

    public const ABERTO = 'aberto';

    public const SEM_CONFIG = 'sem_config';

    /** @return array{estado: string, inicio?: string, fim?: string} */
    public function doDia(Carbon $data): array
    {
        $excecao = ExcecaoFuncionamento::whereDate('data', $data->toDateString())->first();

        if ($excecao !== null) {
            if ($excecao->tipo === 'fechado') {
                return ['estado' => self::FECHADO];
            }

            // horário especial: a faixa da exceção substitui a do dia.
            return [
                'estado' => self::ABERTO,
                'inicio' => substr((string) $excecao->hora_inicio, 0, 5),
                'fim' => substr((string) $excecao->hora_fim, 0, 5),
            ];
        }

        $dia = $this->semanal($data);

        if ($dia === null) {
            return ['estado' => self::SEM_CONFIG];
        }

        if (! ($dia['aberto'] ?? false)) {
            return ['estado' => self::FECHADO];
        }

        return ['estado' => self::ABERTO, 'inicio' => $dia['inicio'], 'fim' => $dia['fim']];
    }

    public function fechadoNoDia(Carbon $data): bool
    {
        return $this->doDia($data)['estado'] === self::FECHADO;
    }

    /**
     * Entrada do horário semanal para o dia da `data`, ou null se não há config
     * (ou o dia não está listado) — caso permissivo.
     *
     * @return array{dia:int, aberto:bool, inicio:string, fim:string}|null
     */
    private function semanal(Carbon $data): ?array
    {
        $json = Configuracao::valor('horario_funcionamento');

        if (! $json) {
            return null;
        }

        $lista = json_decode($json, true);

        if (! is_array($lista) || $lista === []) {
            return null;
        }

        $diaSemana = (int) $data->dayOfWeek; // 0=domingo … 6=sábado

        foreach ($lista as $f) {
            if ((int) ($f['dia'] ?? -1) === $diaSemana) {
                return $f;
            }
        }

        return null;
    }
}
