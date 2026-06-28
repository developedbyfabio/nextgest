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
 * Gestão de OPT-OUT do WhatsApp (D83 + Broadcast Fatia 1, D86). Dois consentimentos
 * INDEPENDENTES por cliente:
 *  - GERAL (transacional): `whatsapp_optout` — bloqueia TUDO (lembrete/avaliação/marketing).
 *  - MARKETING: `whatsapp_marketing_optout` — bloqueia só broadcast; lembretes continuam.
 *
 * Bloquear é imediato; LIBERAR (re-consentir) pede confirmação (D65). Gated por
 * `recurso:whatsapp` + `can('gerenciar_whatsapp')`. O transacional (D79/D81) respeita só
 * o geral; o marketing será consumido pela Fatia 2 via `Cliente::aceitaMarketing()`.
 */
#[Layout('components.layouts.painel')]
#[Title('WhatsApp · Opt-out')]
class OptOut extends Component
{
    use WithPagination;

    /** Tipos de consentimento → coluna no cliente. */
    private const COLUNAS = [
        'geral' => 'whatsapp_optout',
        'marketing' => 'whatsapp_marketing_optout',
    ];

    /** Busca p/ encontrar um cliente e ajustar os consentimentos dele. */
    public string $busca = '';

    /** Confirmação de LIBERAR (re-consentir) — modal D65. */
    public ?int $confirmarId = null;

    public string $confirmarNome = '';

    public string $confirmarTipo = '';

    public function mount(): void
    {
        abort_unless(auth('web')->user()?->can('gerenciar_whatsapp'), 403);
    }

    /** Bloqueia um consentimento (imediato — parar de enviar é seguro). */
    public function bloquear(int $clienteId, string $tipo): void
    {
        abort_unless(auth('web')->user()?->can('gerenciar_whatsapp'), 403);
        $coluna = self::COLUNAS[$tipo] ?? null;
        if (! $coluna) {
            return;
        }

        Cliente::whereKey($clienteId)->update([$coluna => true]);
        $this->busca = '';
        $this->resetPage();
        Flux::toast($tipo === 'geral' ? 'Cliente bloqueado (não recebe nada).' : 'Cliente saiu do marketing.', variant: 'success');
    }

    /** Abre o modal antes de LIBERAR (o cliente volta a receber — consentimento). */
    public function confirmarLiberacao(int $clienteId, string $tipo): void
    {
        abort_unless(auth('web')->user()?->can('gerenciar_whatsapp'), 403);
        if (! isset(self::COLUNAS[$tipo])) {
            return;
        }

        $this->confirmarId = $clienteId;
        $this->confirmarTipo = $tipo;
        $this->confirmarNome = (string) (Cliente::whereKey($clienteId)->value('nome') ?? '');
        Flux::modal('optout-liberar')->show();
    }

    /** Libera (volta a poder receber) o consentimento confirmado. */
    public function liberar(int $clienteId, string $tipo): void
    {
        abort_unless(auth('web')->user()?->can('gerenciar_whatsapp'), 403);
        $coluna = self::COLUNAS[$tipo] ?? null;
        if (! $coluna) {
            return;
        }

        Cliente::whereKey($clienteId)->update([$coluna => false]);
        $this->reset('confirmarId', 'confirmarNome', 'confirmarTipo');
        Flux::modal('optout-liberar')->close();
        Flux::toast('Consentimento liberado.', variant: 'success');
    }

    public function render(): View
    {
        // Clientes com ALGUMA restrição (geral OU marketing).
        $restritos = Cliente::query()
            ->where(fn ($q) => $q->where('whatsapp_optout', true)->orWhere('whatsapp_marketing_optout', true))
            ->orderBy('nome')
            ->paginate(15);

        // Busca: qualquer cliente (para ajustar os consentimentos dele).
        $resultados = collect();
        if (mb_strlen(trim($this->busca)) >= 2) {
            $termo = '%'.trim($this->busca).'%';
            $resultados = Cliente::query()
                ->where(fn ($q) => $q->where('nome', 'like', $termo)->orWhere('telefone', 'like', $termo))
                ->orderBy('nome')
                ->limit(8)
                ->get(['id', 'nome', 'telefone', 'whatsapp_optout', 'whatsapp_marketing_optout']);
        }

        return view('livewire.painel.whatsapp.optout', [
            'restritos' => $restritos,
            'resultados' => $resultados,
        ]);
    }
}
