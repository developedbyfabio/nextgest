<?php

declare(strict_types=1);

namespace App\Services\Estoque;

use App\Models\MovimentacaoEstoque;
use App\Models\ProdutoUnidade;
use Illuminate\Support\Facades\DB;

/**
 * Movimenta o estoque de um produto por filial, de forma atômica: atualiza
 * `produto_unidade.quantidade` e registra a `movimentacao_estoque` (histórico).
 *
 * `quantidade` da movimentação é o DELTA SINALIZADO (+entra / -sai). Em MySQL o
 * lock pessimista na linha do estoque serializa ajustes concorrentes (SQLite, nos
 * testes, ignora o lock — a lógica de soma continua correta). Reutilizável na 2B
 * para a saída por venda.
 */
class MovimentadorEstoque
{
    /** Aplica um delta sinalizado ao estoque da filial e registra a movimentação. */
    public function aplicar(int $produtoId, int $unidadeId, string $tipo, int $delta, ?string $motivo = null, ?int $userId = null, ?int $vendaId = null): MovimentacaoEstoque
    {
        return DB::transaction(function () use ($produtoId, $unidadeId, $tipo, $delta, $motivo, $userId, $vendaId) {
            $linha = ProdutoUnidade::where('produto_id', $produtoId)
                ->where('unidade_id', $unidadeId)
                ->lockForUpdate()
                ->first();

            $atual = $linha?->quantidade ?? 0;
            $nova = max(0, $atual + $delta); // estoque nunca fica negativo

            ProdutoUnidade::updateOrCreate(
                ['produto_id' => $produtoId, 'unidade_id' => $unidadeId],
                ['quantidade' => $nova],
            );

            return MovimentacaoEstoque::create([
                'produto_id' => $produtoId,
                'unidade_id' => $unidadeId,
                'tipo' => $tipo,
                'quantidade' => $delta,
                'motivo' => $motivo,
                'user_id' => $userId,
                'venda_id' => $vendaId,
            ]);
        });
    }

    /** Entrada de estoque (reposição/compra/estorno): soma `$quantidade` (>0). */
    public function entrada(int $produtoId, int $unidadeId, int $quantidade, ?string $motivo = null, ?int $userId = null, ?int $vendaId = null): MovimentacaoEstoque
    {
        return $this->aplicar($produtoId, $unidadeId, 'entrada', abs($quantidade), $motivo ?: 'Entrada', $userId, $vendaId);
    }

    /** Saída de estoque (venda): subtrai `$quantidade` (>0). Grava `venda_id`. */
    public function saida(int $produtoId, int $unidadeId, int $quantidade, ?string $motivo = null, ?int $userId = null, ?int $vendaId = null): MovimentacaoEstoque
    {
        return $this->aplicar($produtoId, $unidadeId, 'saida', -abs($quantidade), $motivo ?: 'Venda', $userId, $vendaId);
    }

    /** Estoque atual de um produto numa filial. */
    public function disponivel(int $produtoId, int $unidadeId): int
    {
        return (int) (ProdutoUnidade::where('produto_id', $produtoId)
            ->where('unidade_id', $unidadeId)
            ->value('quantidade') ?? 0);
    }

    /** Ajuste para um valor ABSOLUTO (ex.: contagem): delta = alvo − atual. */
    public function ajustePara(int $produtoId, int $unidadeId, int $alvo, ?string $motivo = null, ?int $userId = null): MovimentacaoEstoque
    {
        $atual = (int) (ProdutoUnidade::where('produto_id', $produtoId)
            ->where('unidade_id', $unidadeId)
            ->value('quantidade') ?? 0);

        $delta = max(0, $alvo) - $atual;

        return $this->aplicar($produtoId, $unidadeId, 'ajuste', $delta, $motivo ?: 'Ajuste manual', $userId);
    }
}
