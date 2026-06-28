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

    /** Antecedência do lembrete de serviço (minutos antes), D79. */
    public int $antecedenciaLembrete = 120;

    /** Termo de risco aceito (na versão atual)? Trava a ativação até ser aceito (D80). */
    public bool $termoAceito = false;

    public function mount(): void
    {
        abort_unless(auth('web')->user()?->can('gerenciar_whatsapp'), 403);

        $cfg = WhatsappConfig::query()->first();
        $salvos = $cfg?->automacoes ?? [];
        $this->termoAceito = (bool) $cfg?->termoAceito();

        foreach (AutomacaoWhatsapp::cases() as $a) {
            $this->ativo[$a->value] = (bool) ($salvos[$a->value]['ativo'] ?? false); // broadcast/tudo off por padrão
            $this->template[$a->value] = (string) ($salvos[$a->value]['template'] ?? $a->templatePadrao());
        }

        $this->antecedenciaLembrete = (int) ($salvos['lembrete_servico']['antecedencia_min']
            ?? config('whatsapp.lembretes.antecedencia_min_padrao', 120));
    }

    /** Registra o aceite do termo de risco (quem/quando/versão) — libera os toggles. */
    public function aceitarTermo(): void
    {
        abort_unless(auth('web')->user()?->can('gerenciar_whatsapp'), 403);

        $cfg = WhatsappConfig::query()->first() ?? new WhatsappConfig;
        $cfg->termo_aceito_em = now();
        $cfg->termo_aceito_por = (string) (auth('web')->user()?->name ?? '');
        $cfg->termo_versao = (string) config('whatsapp.termo_versao');
        $cfg->save();

        $this->termoAceito = true;
        Flux::toast('Termo aceito. As automações já podem ser ligadas.', variant: 'success');
    }

    public function salvar(): void
    {
        abort_unless(auth('web')->user()?->can('gerenciar_whatsapp'), 403);

        $this->validate($this->rules());

        $cfg = WhatsappConfig::query()->first() ?? new WhatsappConfig;

        // TRAVA (servidor): sem o termo aceito, NENHUMA automação liga — força tudo off,
        // mesmo que o request tente ligar (não basta esconder o toggle).
        $aceito = $cfg->termoAceito();
        $tentouLigar = collect($this->ativo)->contains(true);
        if (! $aceito && $tentouLigar) {
            Flux::toast('Aceite o termo de risco para ligar as automações.', variant: 'danger');
        }

        $map = [];
        foreach (AutomacaoWhatsapp::cases() as $a) {
            $map[$a->value] = [
                'ativo' => $aceito ? (bool) ($this->ativo[$a->value] ?? false) : false,
                'template' => trim((string) ($this->template[$a->value] ?? '')),
            ];
        }
        // Antecedência (min) só faz sentido no lembrete de serviço (D79).
        $map['lembrete_servico']['antecedencia_min'] = max(5, (int) $this->antecedenciaLembrete);

        $cfg->automacoes = $map;
        $cfg->save();

        // Reflete na tela o que de fato foi salvo (se travado, voltam desligadas).
        if (! $aceito) {
            foreach (AutomacaoWhatsapp::cases() as $a) {
                $this->ativo[$a->value] = false;
            }
        }

        if ($aceito || ! $tentouLigar) {
            Flux::toast('Automações salvas.', variant: 'success');
        }
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

    /** @return array<string, array<int, string|int>> */
    protected function rules(): array
    {
        $regras = ['antecedenciaLembrete' => ['required', 'integer', 'min:5', 'max:1440']];
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
