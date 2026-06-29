<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Clientes;

use App\Livewire\Painel\Agenda\Index as AgendaIndex;
use App\Models\Agendamento;
use App\Models\AssinaturaClube;
use App\Models\Cliente;
use App\Models\WhatsappConfig;
use App\Rules\CelularBr;
use App\Services\WhatsApp\EnvioAvulso;
use App\Services\WhatsApp\WhatsAppException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Aba "Clientes" (CRM). Lista os clientes do TENANT com a "última visita" (= último
 * atendimento CONCLUÍDO, mesmo critério da agenda/D70) e o selo de assinante do Clube;
 * busca por nome; filtros por faixa de última visita e por Clube; detalhe expansível com
 * os últimos agendamentos (Fatia 1, D87).
 *
 * Fatia 2 (D88) — primeiras AÇÕES: (1) editar dados do cliente (nome/email/telefone,
 * validado) e (2) enviar WhatsApp AVULSO 1 a 1. O avulso passa pelos mesmos freios
 * anti-ban (App\Services\WhatsApp\EnvioAvulso): só conectado, consome teto/dia+minuto,
 * registra no histórico (D83); manda para quem está em opt-out só com confirmação (D65).
 * SEM reset de senha (Fatia 3) e SEM campanha (depende do broadcast).
 *
 * Performance (CRÍTICO): a última visita e o assinante vêm de SUBCONSULTAS AGREGADAS
 * (GROUP BY) anexadas via leftJoinSub — UMA query para a lista inteira, NUNCA uma por
 * cliente (proibido N+1). A lista é paginada. O detalhe é UMA query extra, só do cliente
 * aberto. Mesmo estilo set-based de App\Services\Painel\IndicadoresClientes.
 *
 * Gate por permissão `ver_clientes` (Dono/Gerente/Recepção), nunca por papel — tanto a
 * tela quanto editar e enviar WhatsApp avulso. O avulso só aparece com o recurso whatsapp.
 */
#[Layout('components.layouts.painel')]
#[Title('Clientes')]
class Index extends Component
{
    use WithPagination;

    public string $busca = '';

    /** Faixa de "última visita": todos | nunca | ate30 | de31a90 | mais90. */
    public string $visitaFiltro = 'todos';

    /** Clube (só com o recurso ligado): todos | assinantes | normais. */
    public string $clubeFiltro = 'todos';

    /** Cliente com o detalhe aberto (últimos agendamentos). Um por vez. */
    public ?int $clienteAbertoId = null;

    // --- Editar cliente (modal) ---
    public ?int $editId = null;

    public string $editNome = '';

    public string $editEmail = '';

    public string $editTelefone = '';

    // --- WhatsApp avulso (modal) ---
    public ?int $waClienteId = null;

    public string $waNome = '';

    public bool $waOptout = false;

    public bool $waConectado = false;

    public string $msgTexto = '';

    public const POR_PAGINA = 15;

    /** Quantos agendamentos recentes mostrar no detalhe. */
    public const ULTIMOS_AGENDAMENTOS = 8;

    /** Tamanho máximo da mensagem avulsa. */
    public const MAX_MENSAGEM = 1000;

    public function mount(): void
    {
        $this->autorizar();
    }

    /** Gate único (tela + ações): permissão de gestão de clientes, nunca por papel. */
    private function autorizar(): void
    {
        abort_unless(auth('web')->user()?->can('ver_clientes') ?? false, 403);
    }

    // -------- Editar cliente --------

    public function abrirEditar(int $clienteId): void
    {
        $this->autorizar();
        $cliente = Cliente::findOrFail($clienteId);

        $this->editId = $cliente->id;
        $this->editNome = (string) $cliente->nome;
        $this->editEmail = (string) ($cliente->email ?? '');
        $this->editTelefone = (string) $cliente->telefone;
        $this->resetValidation();

        Flux::modal('editar-cliente')->show();
    }

    public function salvarEditar(): void
    {
        $this->autorizar();

        $dados = $this->validate([
            'editNome' => ['required', 'string', 'max:255'],
            'editEmail' => ['nullable', 'email', 'max:255', Rule::unique('clientes', 'email')->ignore($this->editId)],
            'editTelefone' => ['required', 'string', 'max:30', new CelularBr],
        ], attributes: ['editNome' => 'nome', 'editEmail' => 'e-mail', 'editTelefone' => 'telefone']);

        $cliente = Cliente::findOrFail($this->editId);
        $cliente->update([
            'nome' => $dados['editNome'],
            'email' => $dados['editEmail'] !== '' ? $dados['editEmail'] : null,
            // Normaliza para dígitos (BR) — canônico p/ o WhatsApp (o gateway prefixa o 55).
            'telefone' => preg_replace('/\D+/', '', $dados['editTelefone']),
        ]);

        Flux::modal('editar-cliente')->close();
        Flux::toast('Cliente atualizado.', variant: 'success');
    }

    // -------- WhatsApp avulso --------

    public function abrirWhatsapp(int $clienteId): void
    {
        $this->autorizar();
        abort_unless(tenant_tem_recurso('whatsapp'), 404);

        $cliente = Cliente::findOrFail($clienteId);
        $this->waClienteId = $cliente->id;
        $this->waNome = (string) $cliente->nome;
        $this->waOptout = (bool) $cliente->whatsapp_optout;
        $this->waConectado = WhatsappConfig::query()->first()?->status_conexao === 'open';
        $this->msgTexto = '';
        $this->resetValidation();

        Flux::modal('whatsapp-cliente')->show();
    }

    /** Clicou "Enviar": valida o texto e, se opt-out, exige confirmação (D65) antes. */
    public function tentarEnviar(): void
    {
        $this->autorizar();
        $this->validarMensagem();

        $cliente = Cliente::findOrFail($this->waClienteId);

        if ($cliente->whatsapp_optout) {
            Flux::modal('confirmar-optout-wa')->show();

            return;
        }

        $this->executarEnvio($cliente);
    }

    /** Confirmou o envio mesmo com o cliente em opt-out. */
    public function confirmarEnvioOptout(): void
    {
        $this->autorizar();
        $this->validarMensagem();

        $cliente = Cliente::findOrFail($this->waClienteId);
        $this->executarEnvio($cliente);
    }

    private function validarMensagem(): void
    {
        $this->validate(
            ['msgTexto' => ['required', 'string', 'max:'.self::MAX_MENSAGEM]],
            attributes: ['msgTexto' => 'mensagem'],
        );
    }

    private function executarEnvio(Cliente $cliente): void
    {
        try {
            app(EnvioAvulso::class)->enviar($cliente, $this->msgTexto);
        } catch (WhatsAppException $e) {
            Flux::modal('confirmar-optout-wa')->close();
            Flux::toast($e->getMessage(), variant: 'danger');

            return;
        }

        Flux::modal('confirmar-optout-wa')->close();
        Flux::modal('whatsapp-cliente')->close();
        Flux::toast('Mensagem enviada.', variant: 'success');
    }

    /** Qualquer filtro/busca → volta à 1ª página e fecha o detalhe aberto. */
    public function updated(string $prop): void
    {
        if (in_array($prop, ['busca', 'visitaFiltro', 'clubeFiltro'], true)) {
            $this->resetPage();
            $this->clienteAbertoId = null;
        }
    }

    /** Abre/fecha o detalhe de um cliente (toggle). */
    public function alternarDetalhe(int $clienteId): void
    {
        $this->clienteAbertoId = $this->clienteAbertoId === $clienteId ? null : $clienteId;
    }

    public function render(): View
    {
        $clubeAtivo = tenant_tem_recurso('clube');

        // Última visita = MAX(data_hora_inicio) dos agendamentos CONCLUÍDOS por cliente.
        // Uma subconsulta agregada (GROUP BY) — sem N+1.
        $ultimaSub = DB::table('agendamentos')
            ->where('status', 'concluido')
            ->groupBy('cliente_id')
            ->selectRaw('cliente_id, MAX(data_hora_inicio) as ultima_visita');

        $query = Cliente::query()
            ->leftJoinSub($ultimaSub, 'uv', 'uv.cliente_id', '=', 'clientes.id')
            ->select('clientes.*', 'uv.ultima_visita');

        // Selo/filtro de assinante só quando o tenant tem o recurso Clube. Assinante =
        // titular OU dependente COM conta de uma assinatura ATIVA. beneficiarios_assinatura
        // já inclui o titular (titular=true, com cliente_id), então cobre os dois casos.
        if ($clubeAtivo) {
            $assinanteSub = DB::table('beneficiarios_assinatura as ba')
                ->join('assinaturas_clube as ac', 'ac.id', '=', 'ba.assinatura_id')
                ->where('ac.status', AssinaturaClube::STATUS_ATIVA)
                ->whereNotNull('ba.cliente_id')
                ->groupBy('ba.cliente_id')
                ->selectRaw('ba.cliente_id');

            $query->leftJoinSub($assinanteSub, 'asg', 'asg.cliente_id', '=', 'clientes.id')
                ->addSelect(DB::raw('CASE WHEN asg.cliente_id IS NOT NULL THEN 1 ELSE 0 END as assinante'));

            if ($this->clubeFiltro === 'assinantes') {
                $query->whereNotNull('asg.cliente_id');
            } elseif ($this->clubeFiltro === 'normais') {
                $query->whereNull('asg.cliente_id');
            }
        }

        // Busca por nome (server-side). O índice em clientes.nome ajuda o ORDER BY.
        if ($this->busca !== '') {
            $query->where('clientes.nome', 'like', '%'.$this->busca.'%');
        }

        // Faixas de última visita, sobre a coluna agregada do join (contíguas; "nunca" =
        // sem nenhum concluído). Comparações com NULL são falsas → "nunca" cai só no bucket.
        $hoje = Carbon::today();
        $d30 = $hoje->copy()->subDays(30);
        $d90 = $hoje->copy()->subDays(90);
        match ($this->visitaFiltro) {
            'nunca' => $query->whereNull('uv.ultima_visita'),
            'ate30' => $query->where('uv.ultima_visita', '>=', $d30),
            'de31a90' => $query->where('uv.ultima_visita', '<', $d30)->where('uv.ultima_visita', '>=', $d90),
            'mais90' => $query->where('uv.ultima_visita', '<', $d90),
            default => null,
        };

        $clientes = $query->orderBy('clientes.nome')->paginate(self::POR_PAGINA);

        // Detalhe: últimos agendamentos do cliente aberto. UMA query, só quando expandido.
        // Achatado em array (serviços já concatenados) para a view ficar sem lógica.
        $detalhe = null;
        if ($this->clienteAbertoId !== null) {
            $detalhe = Agendamento::query()
                ->where('cliente_id', $this->clienteAbertoId)
                ->with(['profissional:id,name', 'itens.servico:id,nome'])
                ->orderByDesc('data_hora_inicio')
                ->limit(self::ULTIMOS_AGENDAMENTOS)
                ->get()
                ->map(fn (Agendamento $ag) => [
                    'data' => $ag->data_hora_inicio,
                    'servicos' => $ag->itens->map(fn ($i) => $i->servico?->nome)->filter()->implode(', '),
                    'profissional' => $ag->profissional?->name,
                    'status' => $ag->status,
                ]);
        }

        return view('livewire.painel.clientes.index', [
            'clientes' => $clientes,
            'detalhe' => $detalhe,
            'clubeAtivo' => $clubeAtivo,
            'whatsappAtivo' => tenant_tem_recurso('whatsapp'),
            'statusLabel' => AgendaIndex::STATUS_LABEL,
            'statusCor' => AgendaIndex::STATUS_COR,
        ]);
    }
}
