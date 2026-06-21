<?php

declare(strict_types=1);

namespace App\Services\Venda;

use App\Models\Agendamento;
use App\Models\Produto;
use App\Models\Servico;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Services\Estoque\MovimentadorEstoque;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Regras da venda/comanda (Fatia 2B). A agenda guarda o atendimento; a venda guarda
 * o financeiro (doc seção 6).
 *
 * - Itens com SNAPSHOT de descrição e preço; `subtotal = preço × quantidade`.
 * - Totais recalculados a cada mudança: `valor_bruto` (soma) e `valor_total`
 *   (`bruto − desconto`, nunca negativo).
 * - Ao PAGAR: baixa de estoque dos produtos (via MovimentadorEstoque, reusado da 2A)
 *   e comissão básica (snapshot). Bloqueia venda acima do estoque.
 * - Cancelar venda paga ESTORNA o estoque (entrada), para não furar.
 */
class Comanda
{
    public function __construct(private readonly MovimentadorEstoque $estoque) {}

    /** Abre uma comanda avulsa (balcão). Cliente é opcional (venda anônima). */
    public function abrir(int $unidadeId, ?int $clienteId = null, ?int $criadoPorUserId = null): Venda
    {
        return Venda::create([
            'unidade_id' => $unidadeId,
            'cliente_id' => $clienteId,
            'status' => 'aberta',
            'valor_bruto' => 0,
            'desconto' => 0,
            'valor_total' => 0,
            'criado_por_user_id' => $criadoPorUserId,
            'data' => Carbon::now(),
        ]);
    }

    /**
     * Cria (ou reaproveita) a comanda de um agendamento, copiando os serviços do
     * `agendamento_servico` como itens (snapshot), com o profissional que atendeu.
     */
    public function apartirDeAgendamento(Agendamento $agendamento, ?int $criadoPorUserId = null): Venda
    {
        $existente = Venda::where('agendamento_id', $agendamento->id)
            ->where('status', '!=', 'cancelada')
            ->first();

        if ($existente) {
            return $existente;
        }

        return DB::transaction(function () use ($agendamento, $criadoPorUserId) {
            $venda = Venda::create([
                'unidade_id' => $agendamento->unidade_id,
                'cliente_id' => $agendamento->cliente_id,
                'agendamento_id' => $agendamento->id,
                'status' => 'aberta',
                'valor_bruto' => 0,
                'desconto' => 0,
                'valor_total' => 0,
                'criado_por_user_id' => $criadoPorUserId,
                'data' => Carbon::now(),
            ]);

            foreach ($agendamento->itens()->with('servico')->get() as $item) {
                $venda->itens()->create([
                    'tipo' => 'servico',
                    'servico_id' => $item->servico_id,
                    'descricao' => $item->servico?->nome ?? 'Serviço',
                    'quantidade' => 1,
                    'preco_unitario' => $item->preco,
                    'subtotal' => $item->preco,
                    'profissional_id' => $agendamento->profissional_id,
                ]);
            }

            $this->recalcular($venda);

            return $venda;
        });
    }

    /** Adiciona um produto à comanda (snapshot). Bloqueia acima do estoque. */
    public function adicionarProduto(Venda $venda, Produto $produto, int $quantidade = 1, ?int $profissionalId = null): VendaItem
    {
        $this->garantirAberta($venda);
        $quantidade = max(1, $quantidade);

        if ($produto->controla_estoque) {
            $jaNaComanda = (int) $venda->itens()->where('produto_id', $produto->id)->sum('quantidade');
            $disponivel = $this->estoque->disponivel($produto->id, $venda->unidade_id);

            if ($jaNaComanda + $quantidade > $disponivel) {
                throw new EstoqueInsuficienteException(
                    "Estoque insuficiente de \"{$produto->nome}\" nesta unidade (disponível: {$disponivel})."
                );
            }
        }

        $preco = (float) $produto->preco_venda;

        $item = $venda->itens()->create([
            'tipo' => 'produto',
            'produto_id' => $produto->id,
            'descricao' => $produto->nome,
            'quantidade' => $quantidade,
            'preco_unitario' => $preco,
            'subtotal' => $preco * $quantidade,
            'profissional_id' => $profissionalId,
        ]);

        $this->recalcular($venda);

        return $item;
    }

    /** Adiciona um serviço à comanda (snapshot, quantidade 1). */
    public function adicionarServico(Venda $venda, Servico $servico, ?int $profissionalId = null): VendaItem
    {
        $this->garantirAberta($venda);

        $preco = (float) $servico->preco;

        $item = $venda->itens()->create([
            'tipo' => 'servico',
            'servico_id' => $servico->id,
            'descricao' => $servico->nome,
            'quantidade' => 1,
            'preco_unitario' => $preco,
            'subtotal' => $preco,
            'profissional_id' => $profissionalId,
        ]);

        $this->recalcular($venda);

        return $item;
    }

    public function removerItem(VendaItem $item): void
    {
        $venda = $item->venda;
        $this->garantirAberta($venda);
        $item->delete();
        $this->recalcular($venda);
    }

    public function definirDesconto(Venda $venda, float $desconto): void
    {
        $this->garantirAberta($venda);
        $venda->desconto = max(0, $desconto);
        $this->recalcular($venda);
    }

    /** Recalcula `valor_bruto` (soma dos subtotais) e `valor_total` (bruto − desconto). */
    public function recalcular(Venda $venda): void
    {
        $bruto = (float) $venda->itens()->sum('subtotal');
        $desconto = min((float) $venda->desconto, $bruto); // desconto nunca passa do bruto

        $venda->forceFill([
            'valor_bruto' => $bruto,
            'desconto' => $desconto,
            'valor_total' => max(0, $bruto - $desconto),
        ])->save();
    }

    /**
     * Fecha/paga a comanda: dá baixa no estoque dos produtos (com `venda_id`) e grava
     * a comissão básica (snapshot) por item. Bloqueia se faltar estoque.
     */
    public function pagar(Venda $venda, ?int $userId = null): void
    {
        $this->garantirAberta($venda);

        DB::transaction(function () use ($venda, $userId) {
            $venda->load('itens.produto');

            // 1) Confere estoque agregado por produto (controla_estoque) antes de baixar.
            $necessario = $venda->itens
                ->where('tipo', 'produto')
                ->filter(fn ($i) => $i->produto && $i->produto->controla_estoque)
                ->groupBy('produto_id')
                ->map(fn ($itens) => (int) $itens->sum('quantidade'));

            foreach ($necessario as $produtoId => $qtd) {
                $disp = $this->estoque->disponivel((int) $produtoId, $venda->unidade_id);
                if ($qtd > $disp) {
                    $nome = $venda->itens->firstWhere('produto_id', $produtoId)?->descricao ?? 'produto';
                    throw new EstoqueInsuficienteException("Estoque insuficiente de \"{$nome}\" (disponível: {$disp}).");
                }
            }

            // 2) Baixa de estoque + comissão (snapshot) por item.
            foreach ($venda->itens as $item) {
                if ($item->tipo === 'produto' && $item->produto && $item->produto->controla_estoque) {
                    $this->estoque->saida(
                        $item->produto_id,
                        $venda->unidade_id,
                        (int) $item->quantidade,
                        'Venda #'.$venda->id,
                        $userId,
                        $venda->id,
                    );
                }

                $this->gravarComissao($item);
            }

            $venda->update(['status' => 'paga']);
        });
    }

    /**
     * Cancela a comanda. Se já estava PAGA, estorna o estoque baixado (entrada),
     * para o estoque não furar.
     */
    public function cancelar(Venda $venda, ?int $userId = null): void
    {
        if ($venda->status === 'cancelada') {
            return;
        }

        DB::transaction(function () use ($venda, $userId) {
            if ($venda->status === 'paga') {
                $venda->load('itens.produto');

                foreach ($venda->itens as $item) {
                    if ($item->tipo === 'produto' && $item->produto && $item->produto->controla_estoque) {
                        $this->estoque->entrada(
                            $item->produto_id,
                            $venda->unidade_id,
                            (int) $item->quantidade,
                            'Estorno (cancelamento) venda #'.$venda->id,
                            $userId,
                            $venda->id,
                        );
                    }
                }
            }

            $venda->update(['status' => 'cancelada']);
        });
    }

    /**
     * Comissão BÁSICA (2B): produto usa `percentual_comissao` do cadastro; serviço
     * fica sem comissão (a % padrão de serviço e o override por profissional vêm na 2C).
     */
    private function gravarComissao(VendaItem $item): void
    {
        $percentual = null;

        if ($item->tipo === 'produto' && $item->produto && $item->produto->percentual_comissao !== null) {
            $percentual = (float) $item->produto->percentual_comissao;
        }

        $item->update([
            'percentual_comissao' => $percentual,
            'valor_comissao' => $percentual !== null ? round((float) $item->subtotal * $percentual / 100, 2) : null,
        ]);
    }

    private function garantirAberta(Venda $venda): void
    {
        if ($venda->status !== 'aberta') {
            throw new VendaNaoEditavelException('Esta comanda não está aberta para edição.');
        }
    }
}
