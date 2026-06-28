<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Whatsapp;

use App\Models\Cliente;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Gestão de OPT-OUT do WhatsApp (Controle de mensagens, D83) — torna visível/gerenciável
 * o opt-out interno do D79 (`clientes.whatsapp_optout`). Lista quem está marcado como
 * "não enviar" e permite marcar/desmarcar manualmente (busca por nome/telefone). Gated
 * (`recurso:whatsapp` + `can('gerenciar_whatsapp')`). Respeitado nos comandos/jobs (D79/D81).
 */
#[Layout('components.layouts.painel')]
#[Title('WhatsApp · Opt-out')]
class OptOut extends Component
{
    use WithPagination;

    /** Busca p/ ADICIONAR ao opt-out (entre quem NÃO está marcado). */
    public string $busca = '';

    /** Cliente em confirmação de "voltar a enviar" (modal D65). */
    public ?int $confirmarId = null;

    public string $confirmarNome = '';

    public function mount(): void
    {
        abort_unless(auth('web')->user()?->can('gerenciar_whatsapp'), 403);
    }

    /** Abre o modal de confirmação antes de tirar o cliente do opt-out (ação sensível). */
    public function confirmarRemocao(int $clienteId): void
    {
        abort_unless(auth('web')->user()?->can('gerenciar_whatsapp'), 403);

        $this->confirmarId = $clienteId;
        $this->confirmarNome = (string) (Cliente::whereKey($clienteId)->value('nome') ?? '');
        Flux::modal('optout-voltar')->show();
    }

    /** Marca o cliente como opt-out (não recebe mais mensagens). */
    public function marcar(int $clienteId): void
    {
        abort_unless(auth('web')->user()?->can('gerenciar_whatsapp'), 403);

        Cliente::whereKey($clienteId)->update(['whatsapp_optout' => true]);
        $this->busca = '';
        $this->resetPage();
        Flux::toast('Cliente adicionado ao opt-out.', variant: 'success');
    }

    /** Remove o opt-out (volta a poder receber). */
    public function desmarcar(int $clienteId): void
    {
        abort_unless(auth('web')->user()?->can('gerenciar_whatsapp'), 403);

        Cliente::whereKey($clienteId)->update(['whatsapp_optout' => false]);
        $this->confirmarId = null;
        $this->confirmarNome = '';
        Flux::modal('optout-voltar')->close();
        Flux::toast('Cliente removido do opt-out.', variant: 'success');
    }

    public function render(): View
    {
        $optouts = Cliente::query()
            ->where('whatsapp_optout', true)
            ->orderBy('nome')
            ->paginate(15);

        // Resultados da busca p/ adicionar — só entre quem NÃO está marcado.
        $resultados = collect();
        if (mb_strlen(trim($this->busca)) >= 2) {
            $termo = '%'.trim($this->busca).'%';
            $resultados = Cliente::query()
                ->where('whatsapp_optout', false)
                ->where(fn ($q) => $q->where('nome', 'like', $termo)->orWhere('telefone', 'like', $termo))
                ->orderBy('nome')
                ->limit(8)
                ->get(['id', 'nome', 'telefone']);
        }

        return view('livewire.painel.whatsapp.optout', [
            'optouts' => $optouts,
            'resultados' => $resultados,
        ]);
    }
}
