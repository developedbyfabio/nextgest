<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Vendas;

use App\Models\Produto;
use App\Models\Servico;
use App\Models\User;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Services\Venda\Comanda;
use App\Services\Venda\EstoqueInsuficienteException;
use App\Services\Venda\PagamentoInvalidoException;
use App\Services\Venda\VendaNaoEditavelException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Detalhe da comanda (Fatia 2B): itens (add/remover), profissional por item,
 * desconto, totais ao vivo e ações fechar/pagar / cancelar. Permissão: criar_venda.
 */
#[Layout('components.layouts.painel')]
#[Title('Comanda')]
class Detalhe extends Component
{
    use AuthorizesRequests;

    public int $vendaId;

    public ?string $desconto = null;

    public ?string $vendedorId = null; // "quem vendeu/atendeu" (responsável da comanda)

    // Modal de item.
    public bool $mostrarItem = false;

    public string $tipoItem = 'produto'; // produto | servico

    public ?string $itemRefId = null;

    public int $itemQtd = 1;

    public ?string $itemProfissionalId = null;

    // Pagamento presencial (etapa 1): N formas somando o total.
    /** @var array<int, array{metodo: string, valor: string}> */
    public array $pagamentos = [];

    public ?string $valorRecebido = null; // dinheiro: só calcula troco na UI

    public function mount(int $venda): void
    {
        $v = Venda::findOrFail($venda);
        $this->authorize('gerir', $v); // criar_venda OU profissional do próprio atendimento
        $this->vendaId = $v->id;
        $this->desconto = (string) $v->desconto;
        $this->vendedorId = $v->profissional_id ? (string) $v->profissional_id : '';
    }

    /** Comanda de finalização (vinda de agendamento): cliente e vendedor TRAVADOS. */
    private function travado(): bool
    {
        return $this->venda()->agendamento_id !== null;
    }

    private function venda(): Venda
    {
        return Venda::findOrFail($this->vendaId);
    }

    public function abrirItem(): void
    {
        $this->reset(['itemRefId', 'itemQtd', 'itemProfissionalId']);
        $this->itemQtd = 1;
        $this->tipoItem = 'produto';
        // Pré-preenche o profissional do item com o vendedor/responsável da comanda.
        $this->itemProfissionalId = $this->venda()->profissional_id ? (string) $this->venda()->profissional_id : null;
        $this->resetValidation();
        $this->mostrarItem = true;
    }

    /** Define o "quem vendeu/atendeu" da comanda (avulsa). Travado se for finalização. */
    public function updatedVendedorId(): void
    {
        $venda = $this->venda();
        $this->authorize('gerir', $venda);

        if ($this->travado() || $venda->status !== 'aberta') {
            $this->vendedorId = $venda->profissional_id ? (string) $venda->profissional_id : '';

            return;
        }

        $venda->update(['profissional_id' => $this->vendedorId !== '' ? (int) $this->vendedorId : null]);
    }

    public function adicionarItem(Comanda $comanda): void
    {
        $this->authorize('gerir', $this->venda());

        $dados = $this->validate([
            'tipoItem' => ['required', 'in:produto,servico'],
            'itemRefId' => ['required', 'integer'],
            'itemQtd' => ['required', 'integer', 'min:1'],
            'itemProfissionalId' => ['nullable', 'integer', 'exists:users,id'],
        ], attributes: ['itemRefId' => 'item', 'itemQtd' => 'quantidade']);

        $venda = $this->venda();
        $profId = $dados['itemProfissionalId'] ? (int) $dados['itemProfissionalId'] : null;

        try {
            if ($dados['tipoItem'] === 'produto') {
                $produto = Produto::where('ativo', true)->findOrFail((int) $dados['itemRefId']);
                $comanda->adicionarProduto($venda, $produto, (int) $dados['itemQtd'], $profId);
            } else {
                $servico = Servico::where('ativo', true)->findOrFail((int) $dados['itemRefId']);
                $comanda->adicionarServico($venda, $servico, $profId);
            }
        } catch (EstoqueInsuficienteException|VendaNaoEditavelException $e) {
            Flux::toast($e->getMessage(), variant: 'danger');

            return;
        }

        $this->sincronizar();
        $this->mostrarItem = false;
        Flux::toast('Item adicionado.', variant: 'success');
    }

    public function removerItem(int $itemId, Comanda $comanda): void
    {
        $this->authorize('gerir', $this->venda());
        $item = VendaItem::where('venda_id', $this->vendaId)->findOrFail($itemId);

        try {
            $comanda->removerItem($item);
        } catch (VendaNaoEditavelException $e) {
            Flux::toast($e->getMessage(), variant: 'danger');

            return;
        }

        $this->sincronizar();
        Flux::toast('Item removido.');
    }

    public function updatedDesconto(Comanda $comanda): void
    {
        $venda = $this->venda();

        if ($venda->status !== 'aberta') {
            return;
        }

        $comanda->definirDesconto($venda, (float) str_replace(',', '.', (string) $this->desconto));
        $this->sincronizar();
    }

    public function pedirPagar(): void
    {
        // Pré-preenche uma forma (dinheiro) com o total da comanda.
        $this->pagamentos = [['metodo' => 'dinheiro', 'valor' => number_format((float) $this->venda()->valor_total, 2, '.', '')]];
        $this->valorRecebido = null;
        $this->resetValidation();
        Flux::modal('pagar-comanda')->show();
    }

    public function adicionarFormaPagamento(): void
    {
        $restante = max(0, round((float) $this->venda()->valor_total - $this->somaPagamentos(), 2));
        $this->pagamentos[] = ['metodo' => 'pix', 'valor' => number_format($restante, 2, '.', '')];
    }

    public function removerFormaPagamento(int $indice): void
    {
        unset($this->pagamentos[$indice]);
        $this->pagamentos = array_values($this->pagamentos);
    }

    private function somaPagamentos(): float
    {
        return round(collect($this->pagamentos)->sum(fn ($p) => (float) str_replace(',', '.', (string) ($p['valor'] ?? 0))), 2);
    }

    public function pagar(Comanda $comanda): void
    {
        $this->authorize('gerir', $this->venda());

        $linhas = collect($this->pagamentos)
            ->map(fn ($p) => ['metodo' => $p['metodo'] ?? '', 'valor' => (float) str_replace(',', '.', (string) ($p['valor'] ?? 0))])
            ->all();

        try {
            $comanda->pagarPresencial($this->venda(), $linhas, auth('web')->id());
        } catch (EstoqueInsuficienteException|VendaNaoEditavelException|PagamentoInvalidoException $e) {
            Flux::toast($e->getMessage(), variant: 'danger');

            return;
        }

        Flux::modal('pagar-comanda')->close();
        Flux::toast('Comanda paga. Estoque e comissão atualizados.', variant: 'success');
    }

    public function pedirCancelar(): void
    {
        Flux::modal('cancelar-comanda')->show();
    }

    public function cancelar(Comanda $comanda): void
    {
        $this->authorize('gerir', $this->venda());
        $comanda->cancelar($this->venda(), auth('web')->id());
        Flux::modal('cancelar-comanda')->close();
        Flux::toast('Comanda cancelada.');
    }

    /** Re-sincroniza o campo de desconto com o valor (possivelmente clampado) no banco. */
    private function sincronizar(): void
    {
        $this->desconto = (string) $this->venda()->desconto;
    }

    public function render(): View
    {
        $venda = Venda::with([
            'itens.produto:id,nome',
            'itens.servico:id,nome',
            'itens.profissional:id,name',
            'pagamentos:id,venda_id,metodo,valor,status,pago_em',
            'cliente:id,nome,telefone',
            'profissional:id,name',
            'unidade:id,nome',
        ])->findOrFail($this->vendaId);

        $soma = $this->somaPagamentos();
        $total = round((float) $venda->valor_total, 2);
        $recebido = (float) str_replace(',', '.', (string) $this->valorRecebido);

        return view('livewire.painel.vendas.detalhe', [
            'venda' => $venda,
            'editavel' => $venda->status === 'aberta',
            'produtos' => Produto::where('ativo', true)->orderBy('nome')->get(['id', 'nome', 'preco_venda', 'controla_estoque']),
            'servicos' => Servico::where('ativo', true)->orderBy('nome')->get(['id', 'nome', 'preco']),
            'profissionais' => User::where('e_profissional', true)->where('ativo', true)->orderBy('name')->get(['id', 'name']),
            'comissaoTotal' => (float) $venda->itens->sum('valor_comissao'),
            'metodos' => \App\Models\Pagamento::METODO_LABEL,
            'somaPagamentos' => $soma,
            'totalVenda' => $total,
            'faltaPagamento' => round($total - $soma, 2),
            'temDinheiro' => collect($this->pagamentos)->contains(fn ($p) => ($p['metodo'] ?? '') === 'dinheiro'),
            'troco' => $recebido > $total ? round($recebido - $total, 2) : 0.0,
        ]);
    }
}
