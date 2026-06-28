<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Whatsapp;

use App\Models\WhatsappConfig;
use App\Services\WhatsApp\Aquecimento as ServicoAquecimento;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Modo Aquecimento — curva de volume do número novo (D82). Tela gated
 * (`recurso:whatsapp` + `can('gerenciar_whatsapp')`) para editar a curva: liga/desliga,
 * fases (até o dia X → teto/dia) e o dia em que o broadcast é liberado. Defaults
 * conservadores já vêm preenchidos. Valores VALIDADOS (sãos) — não dá p/ anular o
 * aquecimento (teto da 1ª fase pequeno, ≤ normal, dias crescentes). Não dispara nada.
 */
#[Layout('components.layouts.painel')]
#[Title('WhatsApp · Aquecimento')]
class Aquecimento extends Component
{
    public bool $ativo = true;

    public int $broadcastDia = 11;

    /** @var array<int, array{ate_dia:int, limite_dia:int}> */
    public array $fases = [];

    public function mount(): void
    {
        abort_unless(auth('web')->user()?->can('gerenciar_whatsapp'), 403);

        $aq = app(ServicoAquecimento::class)->curva(WhatsappConfig::query()->first());

        $this->ativo = (bool) ($aq['ativo'] ?? true);
        $this->broadcastDia = (int) ($aq['broadcast_a_partir_dia'] ?? 11);
        $this->fases = collect($aq['fases'] ?? [])
            ->map(fn ($f) => ['ate_dia' => (int) $f['ate_dia'], 'limite_dia' => (int) $f['limite_dia']])
            ->values()->all();
    }

    public function salvar(): void
    {
        abort_unless(auth('web')->user()?->can('gerenciar_whatsapp'), 403);

        $normal = (int) config('whatsapp.lembretes.limite_por_dia', 150);

        $this->validate([
            'ativo' => ['boolean'],
            'broadcastDia' => ['required', 'integer', 'min:1', 'max:90'],
            'fases' => ['required', 'array', 'min:1', 'max:8'],
            'fases.*.ate_dia' => ['required', 'integer', 'min:1', 'max:90'],
            'fases.*.limite_dia' => ['required', 'integer', 'min:1', 'max:'.$normal],
        ], attributes: ['broadcastDia' => 'dia de liberação do broadcast']);

        // Sanidade da curva (não anular o aquecimento): dias crescentes, teto não
        // decrescente e 1ª fase pequena (número novo nasce apertado).
        $fases = collect($this->fases)->sortBy('ate_dia')->values();
        $erros = [];
        $primeiro = (int) ($fases->first()['limite_dia'] ?? 0);
        if ($primeiro > 30) {
            $erros[] = 'O teto da primeira fase deve ser baixo (até 30/dia) — número novo é frágil.';
        }
        for ($i = 1; $i < $fases->count(); $i++) {
            if ((int) $fases[$i]['ate_dia'] <= (int) $fases[$i - 1]['ate_dia']) {
                $erros[] = 'Os dias das fases precisam ser crescentes.';
                break;
            }
        }
        for ($i = 1; $i < $fases->count(); $i++) {
            if ((int) $fases[$i]['limite_dia'] < (int) $fases[$i - 1]['limite_dia']) {
                $erros[] = 'O teto por fase não pode diminuir ao longo da curva.';
                break;
            }
        }

        if ($erros) {
            Flux::toast(implode(' ', array_unique($erros)), variant: 'danger');

            return;
        }

        $cfg = WhatsappConfig::query()->first() ?? new WhatsappConfig;
        $cfg->aquecimento = [
            'ativo' => $this->ativo,
            'broadcast_a_partir_dia' => $this->broadcastDia,
            'fases' => $fases->map(fn ($f) => ['ate_dia' => (int) $f['ate_dia'], 'limite_dia' => (int) $f['limite_dia']])->all(),
        ];
        $cfg->save();

        Flux::toast('Aquecimento salvo.', variant: 'success');
    }

    public function render(): View
    {
        $svc = app(ServicoAquecimento::class);
        $cfg = WhatsappConfig::query()->first();

        return view('livewire.painel.whatsapp.aquecimento', [
            'conectado' => filled($cfg?->conectado_em),
            'diaAtual' => $svc->diaAtual($cfg),
            'tetoEfetivo' => $svc->tetoEfetivoDia($cfg),
            'broadcastLiberado' => $svc->broadcastLiberado($cfg),
        ]);
    }
}
