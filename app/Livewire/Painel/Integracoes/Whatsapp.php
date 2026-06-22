<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Integracoes;

use App\Models\WhatsappConfig;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Editor da integração WhatsApp (cofre reusado: tabela `whatsapp_config`, model
 * App\Models\WhatsappConfig, `token` cast `encrypted`).
 *
 * Rota gated por `recurso:whatsapp` (flag 0a) + `can:gerenciar_whatsapp`.
 *
 * Segredo WRITE-ONLY: o token NUNCA é renderizado de volta — carrega vazio; salvar
 * vazio MANTÉM, preenchido SUBSTITUI. Telefone / phone_number_id / business_account_id
 * são config NÃO-secreta (podem ser exibidos/editados). Esta fase NÃO chama a API.
 */
#[Layout('components.layouts.painel')]
#[Title('Integração · WhatsApp')]
class Whatsapp extends Component
{
    public bool $ativo = false;

    public string $telefone = '';

    public string $phone_number_id = '';

    public string $business_account_id = '';

    /** Write-only: sempre vazio ao carregar; nunca recebe o segredo. */
    public string $token = '';

    public bool $configurado = false;

    public string $mascara = '';

    public function mount(): void
    {
        abort_unless(auth('web')->user()?->can('gerenciar_whatsapp'), 403);

        if ($c = WhatsappConfig::query()->first()) {
            $this->ativo = (bool) $c->ativo;
            $this->telefone = (string) $c->telefone;
            $this->phone_number_id = (string) $c->phone_number_id;
            $this->business_account_id = (string) $c->business_account_id;
            $this->configurado = filled($c->token);
            $this->mascara = $this->mascarar($c->token);
        }
    }

    public function salvar(): void
    {
        abort_unless(auth('web')->user()?->can('gerenciar_whatsapp'), 403);

        $this->validate([
            'telefone' => ['nullable', 'string', 'max:30'],
            'phone_number_id' => ['nullable', 'string', 'max:100'],
            'business_account_id' => ['nullable', 'string', 'max:100'],
            'token' => ['nullable', 'string', 'max:1000'],
        ]);

        $c = WhatsappConfig::query()->first() ?? new WhatsappConfig;
        $c->telefone = $this->telefone ?: null;
        $c->phone_number_id = $this->phone_number_id ?: null;
        $c->business_account_id = $this->business_account_id ?: null;

        // Write-only: só toca no segredo se veio preenchido.
        if (filled($this->token)) {
            $c->token = trim($this->token);
        }

        $c->ativo = $this->ativo;
        $c->save();

        $this->token = ''; // nunca mantém o segredo no estado do componente
        $this->configurado = filled($c->token);
        $this->mascara = $this->mascarar($c->token);

        Flux::toast('Integração salva.', variant: 'success');
    }

    private function mascarar(?string $valor): string
    {
        return blank($valor) ? '' : '••••'.substr($valor, -4);
    }

    public function render(): View
    {
        return view('livewire.painel.integracoes.whatsapp');
    }
}
