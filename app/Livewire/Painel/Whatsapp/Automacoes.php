<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Whatsapp;

use App\Enums\AutomacaoWhatsapp;
use App\Livewire\Painel\Whatsapp\Concerns\FocaPrimeiroErro;
use App\Models\MensagemWhatsapp;
use App\Models\WhatsappConfig;
use App\Services\WhatsApp\RegistroMensagem;
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
    use FocaPrimeiroErro;

    /** chave => bool */
    public array $ativo = [];

    /** chave => template (string) */
    public array $template = [];

    /** Número usado pelo botão "testar" (não toca a base de clientes). */
    public string $numeroTeste = '';

    /** Antecedência do lembrete de serviço (minutos antes), D79. */
    public int $antecedenciaLembrete = 120;

    /** Tempo após a conclusão para pedir avaliação (minutos), D81. */
    public int $aposAvaliacao = 120;

    /** Termo de risco aceito (na versão atual)? Trava a ativação até ser aceito (D80). */
    public bool $termoAceito = false;

    public function mount(): void
    {
        abort_unless(auth('web')->user()?->can('gerenciar_whatsapp'), 403);

        $cfg = WhatsappConfig::query()->first();
        $salvos = $cfg?->automacoes ?? [];
        $this->termoAceito = (bool) $cfg?->termoAceito();
        $this->numeroTeste = (string) ($cfg?->numero_teste ?? ''); // persistente por tenant (D84)

        foreach (AutomacaoWhatsapp::cases() as $a) {
            $this->ativo[$a->value] = (bool) ($salvos[$a->value]['ativo'] ?? false); // broadcast/tudo off por padrão
            $this->template[$a->value] = (string) ($salvos[$a->value]['template'] ?? $a->templatePadrao());
        }

        $this->antecedenciaLembrete = (int) ($salvos['lembrete_servico']['antecedencia_min']
            ?? config('whatsapp.lembretes.antecedencia_min_padrao', 120));

        $this->aposAvaliacao = (int) ($salvos['avaliacao_pos_servico']['apos_min']
            ?? config('whatsapp.avaliacao.apos_min_padrao', 120));
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

        if ($this->validarOuFocar($this->rules()) === null) {
            return; // erro → toast + foco no 1º campo inválido (D84)
        }

        $cfg = WhatsappConfig::query()->first() ?? new WhatsappConfig;

        // TRAVA (servidor): sem o termo aceito, NENHUMA automação liga — força tudo off,
        // mesmo que o request tente ligar (não basta esconder o toggle).
        $aceito = $cfg->termoAceito();
        $tentouLigar = collect($this->ativo)->contains(true);
        if (! $aceito && $tentouLigar) {
            Flux::toast('Aceite o termo de risco para ligar as automações.', variant: 'danger');
        }

        // Merge sobre o que já existe — preserva subchaves de cada card (ex.: a janela
        // própria gravada pela aba Janela, D83). Antes reconstruía do zero e apagava.
        $automacoes = $cfg->automacoes ?? [];
        foreach (AutomacaoWhatsapp::cases() as $a) {
            $automacoes[$a->value] = $this->entradaCard($a->value, $aceito, $automacoes[$a->value] ?? []);
        }

        $cfg->automacoes = $automacoes;
        $cfg->numero_teste = trim($this->numeroTeste) ?: null; // persiste por tenant (D84)
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

    /**
     * Salva SÓ um card (D85). Mesma persistência do global (`whatsapp_config.automacoes`),
     * mas mexe apenas naquela automação — os demais cards e suas subchaves (ex.: janela
     * própria, D83) ficam intactos. Reusa a trava do termo (D80) e o toast+foco (D84).
     */
    public function salvarCard(string $chave): void
    {
        abort_unless(auth('web')->user()?->can('gerenciar_whatsapp'), 403);

        $a = AutomacaoWhatsapp::tryFrom($chave);
        if (! $a) {
            return;
        }

        // Valida só os campos DESTE card (toast + foco no inválido, D84).
        $regras = [
            'ativo.'.$chave => ['boolean'],
            'template.'.$chave => ['nullable', 'string', 'max:2000'],
        ];
        if ($chave === 'lembrete_servico') {
            $regras['antecedenciaLembrete'] = ['required', 'integer', 'min:5', 'max:1440'];
        }
        if ($chave === 'avaliacao_pos_servico') {
            $regras['aposAvaliacao'] = ['required', 'integer', 'min:5', 'max:10080'];
        }
        if ($this->validarOuFocar($regras) === null) {
            return;
        }

        $cfg = WhatsappConfig::query()->first() ?? new WhatsappConfig;

        // TRAVA (servidor): sem termo aceito, este card não liga (não basta o toggle).
        $aceito = $cfg->termoAceito();
        $querLigar = (bool) ($this->ativo[$chave] ?? false);
        if (! $aceito && $querLigar) {
            Flux::toast('Aceite o termo de risco para ligar as automações.', variant: 'danger');
        }

        $automacoes = $cfg->automacoes ?? [];
        $automacoes[$chave] = $this->entradaCard($chave, $aceito, $automacoes[$chave] ?? []);
        $cfg->automacoes = $automacoes;
        $cfg->save();

        if (! $aceito && $querLigar) {
            $this->ativo[$chave] = false; // reflete a trava na tela
        }

        if ($aceito || ! $querLigar) {
            Flux::toast($a->rotulo().' salvo.', variant: 'success');
        }
    }

    /**
     * Monta a entrada de UM card preservando o que já estava salvo (ex.: a janela própria
     * da automação, D83). Só atualiza ativo/template e o campo extra de cada automação.
     *
     * @param  array<string, mixed>  $existente
     * @return array<string, mixed>
     */
    private function entradaCard(string $chave, bool $aceito, array $existente): array
    {
        $entrada = $existente;
        $entrada['ativo'] = $aceito ? (bool) ($this->ativo[$chave] ?? false) : false;
        $entrada['template'] = trim((string) ($this->template[$chave] ?? ''));

        // Antecedência (min) só faz sentido no lembrete de serviço (D79).
        if ($chave === 'lembrete_servico') {
            $entrada['antecedencia_min'] = max(5, (int) $this->antecedenciaLembrete);
        }
        // Tempo após a conclusão (min) só faz sentido na avaliação pós-serviço (D81).
        if ($chave === 'avaliacao_pos_servico') {
            $entrada['apos_min'] = max(5, (int) $this->aposAvaliacao);
        }

        return $entrada;
    }

    /** Renderiza o template com DADOS DE EXEMPLO e envia para o número informado (D75). */
    public function testar(string $chave): void
    {
        abort_unless(auth('web')->user()?->can('gerenciar_whatsapp'), 403);

        $a = AutomacaoWhatsapp::tryFrom($chave);
        if (! $a) {
            return;
        }

        if ($this->validarOuFocar(
            ['numeroTeste' => ['required', 'string', 'min:8', 'max:30']],
            attributes: ['numeroTeste' => 'número de teste'],
        ) === null) {
            return; // erro → toast + foco no campo (D84)
        }

        // Lembra o número testado por tenant (D84) — não precisa redigitar depois.
        $cfg = WhatsappConfig::query()->first() ?? new WhatsappConfig;
        $cfg->numero_teste = trim($this->numeroTeste) ?: null;
        $cfg->save();

        $vars = AutomacaoWhatsapp::exemplos();
        $vars['salao'] = (string) (tenant('nome') ?? $vars['salao']); // {salao} = nome do tenant

        $texto = RenderizadorTemplate::render(
            (string) ($this->template[$chave] ?? $a->templatePadrao()),
            $vars,
        );

        try {
            app(WhatsAppService::class)->enviarTexto($this->numeroTeste, $texto);
            RegistroMensagem::registrar([
                'automacao' => 'teste',
                'telefone' => $this->numeroTeste,
                'status' => MensagemWhatsapp::ENVIADO,
                'motivo' => 'teste manual ('.$chave.')',
                'conteudo' => $texto,
                'enviado_em' => now(),
            ]);
            Flux::toast('Mensagem de teste enviada.', variant: 'success');
        } catch (WhatsAppException $e) {
            RegistroMensagem::registrar([
                'automacao' => 'teste',
                'telefone' => $this->numeroTeste,
                'status' => MensagemWhatsapp::FALHOU,
                'motivo' => 'teste manual ('.$chave.'): falha no envio',
                'conteudo' => $texto,
            ]);
            Flux::toast($e->getMessage(), variant: 'danger');
        }
    }

    /** @return array<string, array<int, string|int>> */
    protected function rules(): array
    {
        $regras = [
            'antecedenciaLembrete' => ['required', 'integer', 'min:5', 'max:1440'],
            'aposAvaliacao' => ['required', 'integer', 'min:5', 'max:10080'],
        ];
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
