<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Whatsapp;

use App\Enums\AutomacaoWhatsapp;
use App\Livewire\Painel\Whatsapp\Concerns\FocaPrimeiroErro;
use App\Models\WhatsappConfig;
use App\Services\WhatsApp\JanelaEnvio;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Janela de horário permitido para envios de WhatsApp (Controle de mensagens, D83). Padrão
 * GLOBAL + override por automação (cada uma nasce com a global; pode ter a sua). Decidida
 * NO ENVIO (servidor, JanelaEnvio) — aqui só edita a configuração. Fuso APP_TIMEZONE.
 * Gated (`recurso:whatsapp` + `can('gerenciar_whatsapp')`).
 */
#[Layout('components.layouts.painel')]
#[Title('WhatsApp · Janela de horário')]
class Janela extends Component
{
    use FocaPrimeiroErro;

    public bool $globalAtiva = true;

    public string $globalInicio = '08:00';

    public string $globalFim = '20:00';

    /** chave => array{usar:bool, inicio:string, fim:string} (override por automação). */
    public array $overrides = [];

    public function mount(): void
    {
        abort_unless(auth('web')->user()?->can('gerenciar_whatsapp'), 403);

        $cfg = WhatsappConfig::query()->first();
        $global = is_array($cfg?->janela) ? $cfg->janela : (array) config('whatsapp.janela');

        $this->globalAtiva = (bool) ($global['ativa'] ?? true);
        $this->globalInicio = $this->hhmm($global['inicio'] ?? '08:00');
        $this->globalFim = $this->hhmm($global['fim'] ?? '20:00');

        $automacoes = $cfg?->automacoes ?? [];
        foreach (AutomacaoWhatsapp::cases() as $a) {
            $j = $automacoes[$a->value]['janela'] ?? null;
            $this->overrides[$a->value] = [
                'usar' => is_array($j),
                'inicio' => $this->hhmm($j['inicio'] ?? $this->globalInicio),
                'fim' => $this->hhmm($j['fim'] ?? $this->globalFim),
            ];
        }
    }

    public function salvar(): void
    {
        abort_unless(auth('web')->user()?->can('gerenciar_whatsapp'), 403);

        $ok = $this->validarOuFocar([
            'globalAtiva' => ['boolean'],
            'globalInicio' => ['required', 'date_format:H:i'],
            'globalFim' => ['required', 'date_format:H:i', 'after:globalInicio'],
            'overrides.*.usar' => ['boolean'],
            'overrides.*.inicio' => ['required', 'date_format:H:i'],
            'overrides.*.fim' => ['required', 'date_format:H:i'],
        ], attributes: [
            'globalInicio' => 'início (global)',
            'globalFim' => 'fim (global)',
        ]);
        if ($ok === null) {
            return; // erro → toast + foco no 1º campo inválido (D84)
        }

        // Override habilitado precisa de fim > início (validação cruzada por automação).
        foreach ($this->overrides as $chave => $o) {
            if (($o['usar'] ?? false) && ! ($o['fim'] > $o['inicio'])) {
                Flux::toast('Na janela própria, o horário final precisa ser maior que o inicial.', variant: 'danger');

                return;
            }
        }

        $cfg = WhatsappConfig::query()->first() ?? new WhatsappConfig;

        $cfg->janela = [
            'ativa' => $this->globalAtiva,
            'inicio' => $this->globalInicio,
            'fim' => $this->globalFim,
        ];

        // Preserva o restante de cada automação (ativo/template/antecedência…); só mexe na
        // subchave `janela`: presente = override próprio; ausente = usa a global.
        $automacoes = $cfg->automacoes ?? [];
        foreach (AutomacaoWhatsapp::cases() as $a) {
            $o = $this->overrides[$a->value] ?? null;
            $entrada = $automacoes[$a->value] ?? [];

            if ($o && ($o['usar'] ?? false)) {
                $entrada['janela'] = ['ativa' => true, 'inicio' => $o['inicio'], 'fim' => $o['fim']];
            } else {
                unset($entrada['janela']);
            }

            $automacoes[$a->value] = $entrada;
        }
        $cfg->automacoes = $automacoes;
        $cfg->save();

        Flux::toast('Janela de horário salva.', variant: 'success');
    }

    /** Normaliza para "HH:MM" (defensivo contra valores antigos/parciais). */
    private function hhmm(string $valor): string
    {
        $p = explode(':', $valor);
        $h = str_pad((string) max(0, min(23, (int) ($p[0] ?? 0))), 2, '0', STR_PAD_LEFT);
        $m = str_pad((string) max(0, min(59, (int) ($p[1] ?? 0))), 2, '0', STR_PAD_LEFT);

        return $h.':'.$m;
    }

    public function render(): View
    {
        $cfg = WhatsappConfig::query()->first();
        $svc = app(JanelaEnvio::class);

        // Prévia: a janela global está aberta agora? (fuso APP_TIMEZONE)
        $janelaGlobal = $svc->paraAutomacao('__global__', $cfg);

        return view('livewire.painel.whatsapp.janela', [
            'automacoes' => AutomacaoWhatsapp::cases(),
            'abertaAgora' => $svc->aberta($janelaGlobal),
        ]);
    }
}
