<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Whatsapp;

use App\Enums\AutomacaoWhatsapp;
use App\Models\WhatsappConfig;
use App\Services\WhatsApp\RenderizadorTemplate;
use App\Services\WhatsApp\WhatsAppException;
use App\Services\WhatsApp\WhatsAppService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Configuração das automações de WhatsApp (Fatia 3, D77). Liga/desliga + template
 * editável (com variáveis) por automação, em duas categorias (transacional / broadcast).
 * Mesma rota/gating do WhatsApp (recurso `whatsapp` + permissão `gerenciar_whatsapp`).
 *
 * NADA DISPARA aqui: só persiste config (JSON `whatsapp_config.automacoes`) e o botão
 * "testar" (manual) que renderiza o template com DADOS DE EXEMPLO e envia via D75.
 * Broadcast é sensível (massa → risco de ban): off por padrão e marcado na tela.
 */
#[Layout('components.layouts.painel')]
#[Title('WhatsApp · Automações')]
class Automacoes extends Component
{
    /** chave => bool */
    public array $ativo = [];

    /** chave => template (string) */
    public array $template = [];

    /** Número usado pelo botão "testar" (não toca a base de clientes). */
    public string $numeroTeste = '';

    public function mount(): void
    {
        abort_unless(auth('web')->user()?->can('gerenciar_whatsapp'), 403);

        $salvos = WhatsappConfig::query()->first()?->automacoes ?? [];

        foreach (AutomacaoWhatsapp::cases() as $a) {
            $this->ativo[$a->value] = (bool) ($salvos[$a->value]['ativo'] ?? false); // broadcast/tudo off por padrão
            $this->template[$a->value] = (string) ($salvos[$a->value]['template'] ?? $a->templatePadrao());
        }
    }

    public function salvar(): void
    {
        abort_unless(auth('web')->user()?->can('gerenciar_whatsapp'), 403);

        $this->validate($this->rules());

        $cfg = WhatsappConfig::query()->first() ?? new WhatsappConfig;

        $map = [];
        foreach (AutomacaoWhatsapp::cases() as $a) {
            $map[$a->value] = [
                'ativo' => (bool) ($this->ativo[$a->value] ?? false),
                'template' => trim((string) ($this->template[$a->value] ?? '')),
            ];
        }

        $cfg->automacoes = $map;
        $cfg->save();

        Flux::toast('Automações salvas.', variant: 'success');
    }

    /** Renderiza o template com DADOS DE EXEMPLO e envia para o número informado (D75). */
    public function testar(string $chave): void
    {
        abort_unless(auth('web')->user()?->can('gerenciar_whatsapp'), 403);

        $a = AutomacaoWhatsapp::tryFrom($chave);
        if (! $a) {
            return;
        }

        $this->validate(
            ['numeroTeste' => ['required', 'string', 'min:8', 'max:30']],
            attributes: ['numeroTeste' => 'número de teste'],
        );

        $vars = AutomacaoWhatsapp::exemplos();
        $vars['salao'] = (string) (tenant('nome') ?? $vars['salao']); // {salao} = nome do tenant

        $texto = RenderizadorTemplate::render(
            (string) ($this->template[$chave] ?? $a->templatePadrao()),
            $vars,
        );

        try {
            app(WhatsAppService::class)->enviarTexto($this->numeroTeste, $texto);
            Flux::toast('Mensagem de teste enviada.', variant: 'success');
        } catch (WhatsAppException $e) {
            Flux::toast($e->getMessage(), variant: 'danger');
        }
    }

    /** @return array<string, array<int, string>> */
    protected function rules(): array
    {
        $regras = [];
        foreach (AutomacaoWhatsapp::cases() as $a) {
            $regras['ativo.'.$a->value] = ['boolean'];
            $regras['template.'.$a->value] = ['nullable', 'string', 'max:2000'];
        }

        return $regras;
    }

    public function render(): View
    {
        return view('livewire.painel.whatsapp.automacoes', [
            'transacionais' => AutomacaoWhatsapp::transacionais(),
            'broadcasts' => AutomacaoWhatsapp::broadcasts(),
        ]);
    }
}
