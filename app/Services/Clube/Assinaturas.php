<?php

declare(strict_types=1);

namespace App\Services\Clube;

use App\Models\AssinaturaClube;
use App\Models\EventoAssinaturaClube;
use App\Models\PlanoClube;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ciclo de vida das assinaturas do Clube (banco do TENANT). TODA mudança de status
 * grava um EventoAssinaturaClube — é a fonte da evolução/churn dos indicadores.
 *
 * Na Fase A o status é MANUAL (definido aqui pela UI/seed). O gateway recorrente é a
 * costura GatewayRecorrente (impl. manual: não cobra). Quando o webhook entrar, ele
 * chamará `alterarStatus()` com a mesma semântica — sem mudar a aba.
 */
class Assinaturas
{
    public function __construct(private readonly GatewayRecorrente $gateway) {}

    /**
     * Cria uma assinatura (snapshot do preço; próxima cobrança +1 mês). Registra evento
     * `criada`. Em produção futura, `criarRecorrencia` devolveria o id do Preapproval.
     */
    public function criar(int $clienteId, PlanoClube $plano, string $status = AssinaturaClube::STATUS_ATIVA): AssinaturaClube
    {
        return DB::transaction(function () use ($clienteId, $plano, $status) {
            $assinatura = AssinaturaClube::create([
                'cliente_id' => $clienteId,
                'plano_id' => $plano->id,
                'status' => $status,
                'preco_contratado' => $plano->preco_mensal,
                'data_inicio' => Carbon::today(),
                'proxima_cobranca' => Carbon::today()->addMonthNoOverflow(),
            ]);

            $idGateway = $this->gateway->criarRecorrencia($assinatura);
            if ($idGateway !== null) {
                $assinatura->update(['gateway_assinatura_id' => $idGateway]);
            }

            $this->registrarEvento($assinatura, EventoAssinaturaClube::TIPO_CRIADA);

            return $assinatura;
        });
    }

    /**
     * Altera o status manualmente e registra o evento correspondente. Alvos válidos:
     * ativa (reativada), inadimplente (pagamento_falhou), cancelada (cancelada + data_fim
     * + cancela recorrência). Status igual ao atual → no-op (sem evento duplicado).
     */
    public function alterarStatus(AssinaturaClube $assinatura, string $novoStatus): void
    {
        $validos = [
            AssinaturaClube::STATUS_ATIVA,
            AssinaturaClube::STATUS_INADIMPLENTE,
            AssinaturaClube::STATUS_CANCELADA,
        ];

        if (! in_array($novoStatus, $validos, true) || $assinatura->status === $novoStatus) {
            return;
        }

        DB::transaction(function () use ($assinatura, $novoStatus) {
            $tipoEvento = match ($novoStatus) {
                AssinaturaClube::STATUS_CANCELADA => EventoAssinaturaClube::TIPO_CANCELADA,
                AssinaturaClube::STATUS_INADIMPLENTE => EventoAssinaturaClube::TIPO_PAGAMENTO_FALHOU,
                AssinaturaClube::STATUS_ATIVA => EventoAssinaturaClube::TIPO_REATIVADA,
            };

            $dados = ['status' => $novoStatus];

            if ($novoStatus === AssinaturaClube::STATUS_CANCELADA) {
                $dados['data_fim'] = Carbon::today();
                $dados['proxima_cobranca'] = null;
                $this->gateway->cancelarRecorrencia($assinatura);
            }

            if ($novoStatus === AssinaturaClube::STATUS_ATIVA) {
                $dados['data_fim'] = null;
            }

            $assinatura->update($dados);

            $this->registrarEvento($assinatura, $tipoEvento);
        });
    }

    private function registrarEvento(AssinaturaClube $assinatura, string $tipo): void
    {
        EventoAssinaturaClube::create([
            'assinatura_id' => $assinatura->id,
            'tipo' => $tipo,
            'ocorrido_em' => Carbon::now(),
        ]);
    }
}
