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

    /**
     * Agendamento feito pelo próprio cliente (portal). origem=cliente.
     */
    public function confirmar(
        Cliente $cliente,
        int $unidadeId,
        array $servicoIds,
        int $profissionalId,
        Carbon $inicio,
    ): Agendamento {
        return $this->criar($unidadeId, $servicoIds, $profissionalId, $inicio, [
            'cliente_id' => $cliente->id,
            'origem' => 'cliente',
        ]);
    }

    /**
     * Agendamento feito pela equipe (painel). origem=equipe, registra quem criou.
     */
    public function agendarPelaEquipe(
        int $clienteId,
        int $unidadeId,
        array $servicoIds,
        int $profissionalId,
        Carbon $inicio,
        int $criadoPorUserId,
    ): Agendamento {
        return $this->criar($unidadeId, $servicoIds, $profissionalId, $inicio, [
            'cliente_id' => $clienteId,
            'origem' => 'equipe',
            'criado_por_user_id' => $criadoPorUserId,
        ]);
    }

    /**
     * Núcleo concorrente-seguro: lock no profissional, revalida o slot e insere
     * o agendamento com snapshots de preço/duração.
     */
    protected function criar(int $unidadeId, array $servicoIds, int $profissionalId, Carbon $inicio, array $atributos): Agendamento
    {
        return DB::transaction(function () use ($unidadeId, $servicoIds, $profissionalId, $inicio, $atributos) {
            // Lock pessimista: serializa agendamentos do mesmo profissional.
            User::whereKey($profissionalId)->lockForUpdate()->first();

            if (! $this->motor->slotValido($unidadeId, $servicoIds, $profissionalId, $inicio)) {
                throw new SlotIndisponivelException;
            }

            $servicos = Servico::whereIn('id', $this->normalizar($servicoIds))->get();
            $duracaoTotal = (int) $servicos->sum('duracao_minutos');

            $status = Configuracao::booleano('confirmacao_automatica', true) ? 'confirmado' : 'pendente';

            $agendamento = Agendamento::create(array_merge([
                'unidade_id' => $unidadeId,
                'profissional_id' => $profissionalId,
                'data_hora_inicio' => $inicio,
                'data_hora_fim' => $inicio->copy()->addMinutes($duracaoTotal),
                'status' => $status,
                'valor_total' => (float) $servicos->sum('preco'),
            ], $atributos));

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
     * Remarca um agendamento para um novo início (e opcionalmente outro
     * profissional), revalidando com lock e ignorando o próprio agendamento na
     * checagem de conflito. Mantém a duração (snapshot dos itens).
     */
    public function remarcar(Agendamento $agendamento, Carbon $novoInicio, ?int $profissionalId = null): Agendamento
    {
        $profissionalId ??= $agendamento->profissional_id;

        return DB::transaction(function () use ($agendamento, $novoInicio, $profissionalId) {
            User::whereKey($profissionalId)->lockForUpdate()->first();

            $duracao = (int) $agendamento->itens()->sum('duracao_minutos');
            $novoFim = $novoInicio->copy()->addMinutes($duracao);

            $ok = $this->motor->intervaloAgendavel(
                $agendamento->unidade_id,
                $profissionalId,
                $novoInicio,
                $novoFim,
                ignorarAgendamentoId: $agendamento->id,
            );

            if (! $ok) {
                throw new SlotIndisponivelException;
            }

            $agendamento->update([
                'profissional_id' => $profissionalId,
                'data_hora_inicio' => $novoInicio,
                'data_hora_fim' => $novoFim,
            ]);

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

    /**
     * Muda o status respeitando as transições permitidas (Agendamento::TRANSICOES).
     */
    public function mudarStatus(Agendamento $agendamento, string $novo): void
    {
        if (! $agendamento->podeTransicionarPara($novo)) {
            throw new TransicaoInvalidaException;
        }

        $agendamento->update(['status' => $novo]);
    }

    /** @return array<int, int> */
    private function normalizar(array $servicoIds): array
    {
        return array_values(array_unique(array_map('intval', $servicoIds)));
    }
}
