<?php

declare(strict_types=1);

namespace App\Services\Agendamento;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Configuracao;
use App\Models\Servico;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cria e cancela agendamentos com segurança de concorrência.
 *
 * Concorrência (prompt 1C/D): a confirmação roda numa transação e adquire um
 * LOCK PESSIMISTA na linha do profissional (users.id FOR UPDATE) antes de
 * revalidar o slot. Isso serializa tentativas simultâneas para o mesmo
 * profissional — diferente de travar as linhas de `agendamentos` (que não
 * impediria a inserção fantasma quando ainda não há agendamento no horário).
 * O segundo cliente, ao obter o lock, já enxerga o agendamento do primeiro e a
 * revalidação falha → SlotIndisponivelException.
 */
class Agendador
{
    public function __construct(private MotorDisponibilidade $motor) {}

    public function confirmar(
        Cliente $cliente,
        int $unidadeId,
        array $servicoIds,
        int $profissionalId,
        Carbon $inicio,
    ): Agendamento {
        return DB::transaction(function () use ($cliente, $unidadeId, $servicoIds, $profissionalId, $inicio) {
            // Lock pessimista: serializa agendamentos do mesmo profissional.
            User::whereKey($profissionalId)->lockForUpdate()->first();

            if (! $this->motor->slotValido($unidadeId, $servicoIds, $profissionalId, $inicio)) {
                throw new SlotIndisponivelException;
            }

            $servicos = Servico::whereIn('id', $this->normalizar($servicoIds))->get();
            $duracaoTotal = (int) $servicos->sum('duracao_minutos');
            $valorTotal = (float) $servicos->sum('preco');

            $status = Configuracao::booleano('confirmacao_automatica', true) ? 'confirmado' : 'pendente';

            $agendamento = Agendamento::create([
                'unidade_id' => $unidadeId,
                'cliente_id' => $cliente->id,
                'profissional_id' => $profissionalId,
                'data_hora_inicio' => $inicio,
                'data_hora_fim' => $inicio->copy()->addMinutes($duracaoTotal),
                'status' => $status,
                'origem' => 'cliente',
                'valor_total' => $valorTotal,
            ]);

            // Itens com snapshot de preço e duração.
            foreach ($servicos as $servico) {
                $agendamento->itens()->create([
                    'servico_id' => $servico->id,
                    'preco' => $servico->preco,
                    'duracao_minutos' => $servico->duracao_minutos,
                ]);
            }

            return $agendamento;
        });
    }

    /**
     * O cliente pode cancelar se faltam pelo menos N horas (config) para o início
     * e o agendamento ainda está ativo (não cancelado/concluído).
     */
    public function podeCancelar(Agendamento $agendamento): bool
    {
        if (in_array($agendamento->status, ['cancelado', 'concluido', 'nao_compareceu'], true)) {
            return false;
        }

        $horas = Configuracao::inteiro('cancelamento_antecedencia_horas', 2);

        return Carbon::now()->lte($agendamento->data_hora_inicio->copy()->subHours($horas));
    }

    public function cancelar(Agendamento $agendamento): void
    {
        $agendamento->update(['status' => 'cancelado']);
    }

    /** @return array<int, int> */
    private function normalizar(array $servicoIds): array
    {
        return array_values(array_unique(array_map('intval', $servicoIds)));
    }
}
