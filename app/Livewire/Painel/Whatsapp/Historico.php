<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Whatsapp;

use App\Enums\AutomacaoWhatsapp;
use App\Models\MensagemWhatsapp;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Histórico de mensagens de WhatsApp ENVIADAS (Controle de mensagens, D83). Lista o log
 * `mensagens_whatsapp` (metadados + conteúdo; conteúdo expurgado após o prazo), com filtros
 * por automação, status e período. Gated (`recurso:whatsapp` + `can('gerenciar_whatsapp')`).
 *
 * ANONIMATO (D51): registra ENVIO, nunca o RESULTADO da avaliação. NÃO consulta/junta
 * `avaliacoes` — saber "pedi avaliação ao cliente X" ≠ "o que o cliente X avaliou". A tela
 * só é acessível a quem gere o WhatsApp (Dono/Gerente), nunca ao profissional.
 */
#[Layout('components.layouts.painel')]
#[Title('WhatsApp · Histórico')]
class Historico extends Component
{
    use WithPagination;

    public string $filtroAutomacao = '';

    /** '', enviado, falhou, descartado. */
    public string $filtroStatus = '';

    /** '', dia, semana, mes. */
    public string $filtroPeriodo = '';

    public function mount(): void
    {
        abort_unless(auth('web')->user()?->can('gerenciar_whatsapp'), 403);
    }

    public function updated(string $name): void
    {
        if (str_starts_with($name, 'filtro')) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $mensagens = MensagemWhatsapp::query()
            ->with('cliente:id,nome')
            ->when($this->filtroAutomacao !== '', fn ($q) => $q->where('automacao', $this->filtroAutomacao))
            ->when($this->filtroStatus !== '', fn ($q) => $q->where('status', $this->filtroStatus))
            ->when($this->filtroPeriodo !== '', fn ($q) => $q->where('created_at', '>=', match ($this->filtroPeriodo) {
                'semana' => now()->startOfWeek(),
                'mes' => now()->startOfMonth(),
                default => now()->startOfDay(),
            }))
            ->orderByDesc('created_at')
            ->paginate(20);

        // Catálogo p/ o filtro de automação (+ "teste"), com rótulos amigáveis.
        $automacoes = collect(AutomacaoWhatsapp::cases())
            ->mapWithKeys(fn ($a) => [$a->value => $a->rotulo()])
            ->put('teste', 'Teste manual');

        return view('livewire.painel.whatsapp.historico', [
            'mensagens' => $mensagens,
            'automacoes' => $automacoes,
        ]);
    }
}
