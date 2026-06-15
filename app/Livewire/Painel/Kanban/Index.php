<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Kanban;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\KanbanCartao;
use App\Models\KanbanColuna;
use App\Models\KanbanQuadro;
use App\Models\User;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Kanban do painel (tenant, guard `web`). Dois quadros (D22): Atendimento
 * (balcão — permissão `ver_kanban_atendimento`, inclui Recepção) e CRM (funil —
 * permissão `gerir_kanban`, Dono/Gerente). Editar a estrutura (colunas) exige
 * `gerir_kanban`. Cartões: quem acessa o quadro gere seus cartões.
 */
#[Layout('components.layouts.painel')]
#[Title('Kanban')]
class Index extends Component
{
    public string $tipo = 'atendimento';

    // Modal de cartão.
    public bool $mostrarCartao = false;

    public ?int $cartaoId = null;

    public ?int $colunaId = null;

    public string $titulo = '';

    public string $descricao = '';

    public ?string $clienteId = null;

    public ?string $agendamentoId = null;

    public ?string $responsavelId = null;

    public ?string $prazo = null;

    public ?string $valorEstimado = null;

    // Modal de coluna.
    public bool $mostrarColuna = false;

    public ?int $colunaEditId = null;

    public string $nomeColuna = '';

    public function mount(): void
    {
        abort_unless(auth('web')->user()->can('ver_kanban_atendimento'), 403);
    }

    private function podeGerir(): bool
    {
        return auth('web')->user()->can('gerir_kanban');
    }

    private function autorizarQuadro(string $tipo): void
    {
        $ok = $tipo === 'crm'
            ? $this->podeGerir()
            : auth('web')->user()->can('ver_kanban_atendimento');

        abort_unless($ok, 403);
    }

    private function quadro(): KanbanQuadro
    {
        $this->autorizarQuadro($this->tipo);

        return KanbanQuadro::with([
            'colunas.cartoes' => fn ($q) => $q->orderBy('ordem')->orderBy('id'),
            'colunas.cartoes.cliente:id,nome',
            'colunas.cartoes.responsavel:id,name',
            'colunas.cartoes.agendamento:id,cliente_id,data_hora_inicio',
            'colunas.cartoes.agendamento.cliente:id,nome',
        ])->firstOrCreate(
            ['tipo' => $this->tipo],
            ['nome' => $this->tipo === 'crm' ? 'CRM' : 'Atendimento', 'ativo' => true],
        );
    }

    public function trocarQuadro(string $tipo): void
    {
        $this->autorizarQuadro($tipo);
        $this->tipo = $tipo;
    }

    // ----- Colunas (estrutura: exige gerir_kanban) -----

    public function novaColuna(): void
    {
        abort_unless($this->podeGerir(), 403);
        $this->reset(['colunaEditId', 'nomeColuna']);
        $this->resetValidation();
        $this->mostrarColuna = true;
    }

    public function editarColuna(int $id): void
    {
        abort_unless($this->podeGerir(), 403);
        $coluna = KanbanColuna::findOrFail($id);
        $this->colunaEditId = $coluna->id;
        $this->nomeColuna = $coluna->nome;
        $this->resetValidation();
        $this->mostrarColuna = true;
    }

    public function salvarColuna(): void
    {
        abort_unless($this->podeGerir(), 403);
        $this->validate(['nomeColuna' => ['required', 'string', 'max:255']], attributes: ['nomeColuna' => 'nome']);

        $quadro = $this->quadro();

        if ($this->colunaEditId) {
            $coluna = KanbanColuna::where('quadro_id', $quadro->id)->findOrFail($this->colunaEditId);
            $coluna->update(['nome' => $this->nomeColuna]);
        } else {
            KanbanColuna::create([
                'quadro_id' => $quadro->id,
                'nome' => $this->nomeColuna,
                'ordem' => (int) KanbanColuna::where('quadro_id', $quadro->id)->max('ordem') + 1,
            ]);
        }

        $this->mostrarColuna = false;
        Flux::toast('Coluna salva.', variant: 'success');
    }

    public function removerColuna(int $id): void
    {
        abort_unless($this->podeGerir(), 403);
        KanbanColuna::where('quadro_id', $this->quadro()->id)->findOrFail($id)->delete();
        Flux::toast('Coluna removida.');
    }

    // ----- Cartões -----

    public function novoCartao(int $colunaId): void
    {
        $this->autorizarQuadro($this->tipo);
        $this->reset(['cartaoId', 'titulo', 'descricao', 'clienteId', 'agendamentoId', 'responsavelId', 'prazo', 'valorEstimado']);
        $this->resetValidation();
        $this->colunaId = $colunaId;
        $this->mostrarCartao = true;
    }

    public function editarCartao(int $id): void
    {
        $this->autorizarQuadro($this->tipo);
        $c = $this->cartaoDoQuadro($id);

        $this->cartaoId = $c->id;
        $this->colunaId = $c->coluna_id;
        $this->titulo = $c->titulo;
        $this->descricao = (string) $c->descricao;
        $this->clienteId = $c->cliente_id ? (string) $c->cliente_id : null;
        $this->agendamentoId = $c->agendamento_id ? (string) $c->agendamento_id : null;
        $this->responsavelId = $c->responsavel_user_id ? (string) $c->responsavel_user_id : null;
        $this->prazo = $c->prazo?->toDateString();
        $this->valorEstimado = $c->valor_estimado;
        $this->resetValidation();
        $this->mostrarCartao = true;
    }

    public function salvarCartao(): void
    {
        $this->autorizarQuadro($this->tipo);

        $dados = $this->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string', 'max:2000'],
            'colunaId' => ['required', 'integer'],
            'clienteId' => ['nullable', 'integer', 'exists:clientes,id'],
            'agendamentoId' => ['nullable', 'integer', 'exists:agendamentos,id'],
            'responsavelId' => ['nullable', 'integer', 'exists:users,id'],
            'prazo' => ['nullable', 'date'],
            'valorEstimado' => ['nullable', 'numeric', 'min:0'],
        ], attributes: ['titulo' => 'título', 'colunaId' => 'coluna']);

        $quadro = $this->quadro();
        // A coluna alvo precisa pertencer ao quadro atual.
        $coluna = KanbanColuna::where('quadro_id', $quadro->id)->findOrFail($this->colunaId);

        $payload = [
            'titulo' => $dados['titulo'],
            'descricao' => $dados['descricao'] ?: null,
            'cliente_id' => $dados['clienteId'] ?: null,
            'agendamento_id' => $dados['agendamentoId'] ?: null,
            'responsavel_user_id' => $dados['responsavelId'] ?: null,
            'prazo' => $dados['prazo'] ?: null,
            'valor_estimado' => $dados['valorEstimado'] !== null && $dados['valorEstimado'] !== '' ? $dados['valorEstimado'] : null,
        ];

        if ($this->cartaoId) {
            $cartao = $this->cartaoDoQuadro($this->cartaoId);
            $colunaAntiga = $cartao->coluna_id;
            $cartao->update($payload + ['coluna_id' => $coluna->id]);

            if ($colunaAntiga !== $coluna->id) {
                $this->reindexar($coluna->id);
                $this->reindexar($colunaAntiga);
            }
        } else {
            KanbanCartao::create($payload + [
                'coluna_id' => $coluna->id,
                'ordem' => (int) KanbanCartao::where('coluna_id', $coluna->id)->max('ordem') + 1,
            ]);
        }

        $this->mostrarCartao = false;
        Flux::toast('Cartão salvo.', variant: 'success');
    }

    public function removerCartao(int $id): void
    {
        $this->autorizarQuadro($this->tipo);
        $this->cartaoDoQuadro($id)->delete();
        Flux::toast('Cartão removido.');
    }

    /** Arrastar-e-soltar: persiste coluna + posição (última escrita vence). */
    public function moverCartao($cartaoId, $colunaId, $novaOrdem): void
    {
        $this->autorizarQuadro($this->tipo);
        $quadro = $this->quadro();

        $cartao = $this->cartaoDoQuadro((int) $cartaoId);
        $destino = KanbanColuna::where('quadro_id', $quadro->id)->findOrFail((int) $colunaId);

        $origem = $cartao->coluna_id;
        $cartao->update(['coluna_id' => $destino->id]);

        $this->reindexar($destino->id, $cartao->id, (int) $novaOrdem);

        if ($origem !== $destino->id) {
            $this->reindexar($origem);
        }
    }

    /** Alternativa acessível (teclado/menu): move para o fim da coluna alvo. */
    public function moverCartaoParaColuna(int $cartaoId, int $colunaId): void
    {
        $this->moverCartao($cartaoId, $colunaId, PHP_INT_MAX);
    }

    /** Cria cartões no Atendimento a partir dos agendamentos de hoje (sem duplicar). */
    public function gerarCartoesDoDia(): void
    {
        $this->autorizarQuadro('atendimento');

        $quadro = KanbanQuadro::where('tipo', 'atendimento')->firstOrFail();
        $primeira = KanbanColuna::where('quadro_id', $quadro->id)->orderBy('ordem')->first();

        if (! $primeira) {
            Flux::toast('Crie ao menos uma coluna primeiro.', variant: 'warning');

            return;
        }

        $colunaIds = KanbanColuna::where('quadro_id', $quadro->id)->pluck('id');
        $jaVinculados = KanbanCartao::whereIn('coluna_id', $colunaIds)->whereNotNull('agendamento_id')->pluck('agendamento_id')->all();

        $hoje = Agendamento::with(['cliente:id,nome'])
            ->whereBetween('data_hora_inicio', [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()])
            ->whereNotIn('status', Agendamento::STATUS_LIVRES)
            ->whereNotIn('id', $jaVinculados)
            ->orderBy('data_hora_inicio')
            ->get();

        $ordem = (int) KanbanCartao::where('coluna_id', $primeira->id)->max('ordem');

        foreach ($hoje as $ag) {
            KanbanCartao::create([
                'coluna_id' => $primeira->id,
                'titulo' => $ag->cliente?->nome.' · '.$ag->data_hora_inicio->format('H:i'),
                'cliente_id' => $ag->cliente_id,
                'agendamento_id' => $ag->id,
                'responsavel_user_id' => $ag->profissional_id,
                'ordem' => ++$ordem,
            ]);
        }

        Flux::toast($hoje->isEmpty() ? 'Nenhum agendamento novo para hoje.' : "{$hoje->count()} cartão(ões) gerado(s).", variant: 'success');
    }

    private function cartaoDoQuadro(int $id): KanbanCartao
    {
        $colunaIds = KanbanColuna::where('quadro_id', $this->quadro()->id)->pluck('id');

        return KanbanCartao::whereIn('coluna_id', $colunaIds)->findOrFail($id);
    }

    private function reindexar(int $colunaId, ?int $cartaoId = null, ?int $posicao = null): void
    {
        $ids = KanbanCartao::where('coluna_id', $colunaId)->orderBy('ordem')->orderBy('id')->pluck('id')->all();

        if ($cartaoId !== null && $posicao !== null) {
            $ids = array_values(array_filter($ids, fn ($i) => $i !== $cartaoId));
            $posicao = max(0, min($posicao, count($ids)));
            array_splice($ids, $posicao, 0, [$cartaoId]);
        }

        foreach ($ids as $ordem => $id) {
            KanbanCartao::whereKey($id)->update(['ordem' => $ordem]);
        }
    }

    public function render(): View
    {
        $quadro = $this->quadro();

        return view('livewire.painel.kanban.index', [
            'quadro' => $quadro,
            'podeGerir' => $this->podeGerir(),
            'mostrarCRM' => $this->podeGerir(),
            'clientes' => Cliente::orderBy('nome')->get(['id', 'nome']),
            'equipe' => User::where('ativo', true)->orderBy('name')->get(['id', 'name']),
            'agendamentos' => Agendamento::with('cliente:id,nome')
                ->whereBetween('data_hora_inicio', [Carbon::today()->subDays(7), Carbon::today()->addDays(30)])
                ->orderByDesc('data_hora_inicio')
                ->limit(100)
                ->get(['id', 'cliente_id', 'data_hora_inicio']),
        ]);
    }
}
