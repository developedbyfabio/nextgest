<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Models\Agendamento;
use App\Models\Avaliacao;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Avaliação pós-serviço por LINK (Fatia 5, D81). Página PÚBLICA (sem login) acessada por
 * URL ASSINADA (middleware `signed`: HMAC + expira → não-adivinhável, não dá p/ avaliar
 * o atendimento de outro nem forjar). Reusa a criação de `Avaliacao` (D51) — a avaliação
 * acontece na web, onde o anonimato já é garantido (o painel esconde o cliente do
 * profissional). A URL leva só o id do agendamento (sem dado pessoal).
 */
#[Layout('components.layouts.portal-auth')]
#[Title('Avaliar atendimento')]
class AvaliacaoPublica extends Component
{
    public int $agendamentoId;

    public ?int $nota = null;

    public string $comentario = '';

    public bool $enviado = false;

    /** Atendimento não avaliável (não concluído ou já avaliado) → mostra aviso. */
    public bool $indisponivel = false;

    public string $servico = '';

    public string $profissional = '';

    public string $quando = '';

    public function mount(Agendamento $agendamento): void
    {
        $this->agendamentoId = $agendamento->id;

        if ($agendamento->status !== 'concluido' || $agendamento->avaliacao()->exists()) {
            $this->indisponivel = true;

            return;
        }

        $agendamento->loadMissing(['itens.servico', 'profissional']);
        $this->servico = $agendamento->itens->first()?->servico?->nome ?? 'seu atendimento';
        $this->profissional = $agendamento->profissional?->name ?? '';
        $this->quando = $agendamento->data_hora_inicio->translatedFormat('d/m/Y');
    }

    public function salvar(): void
    {
        $this->validate([
            'nota' => ['required', 'integer', 'between:1,5'],
            'comentario' => ['nullable', 'string', 'max:1000'],
        ], attributes: ['nota' => 'nota', 'comentario' => 'comentário']);

        $agendamento = Agendamento::query()->findOrFail($this->agendamentoId);

        // Revalida (não concluído / já avaliado) — protege contra duplo/avaliação inválida.
        abort_if($agendamento->status !== 'concluido' || $agendamento->avaliacao()->exists(), 404);

        Avaliacao::create([
            'agendamento_id' => $agendamento->id,
            'cliente_id' => $agendamento->cliente_id,
            'profissional_id' => $agendamento->profissional_id,
            'unidade_id' => $agendamento->unidade_id,
            'nota' => $this->nota,
            'comentario' => $this->comentario !== '' ? $this->comentario : null,
        ]);

        $this->enviado = true;
    }

    public function render(): View
    {
        return view('livewire.portal.avaliacao-publica');
    }
}
