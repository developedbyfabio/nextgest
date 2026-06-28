<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Clientes;

use App\Livewire\Painel\Agenda\Index as AgendaIndex;
use App\Models\Agendamento;
use App\Models\AssinaturaClube;
use App\Models\Cliente;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Aba "Clientes" (CRM — Fatia 1, SÓ leitura). Lista os clientes do TENANT com a
 * "última visita" (= último atendimento CONCLUÍDO, mesmo critério da agenda/D70) e o
 * selo de assinante do Clube; busca por nome; filtros por faixa de última visita e por
 * Clube; detalhe expansível com os últimos agendamentos. NENHUMA ação aqui (sem editar,
 * reset de senha, WhatsApp ou campanha — fatias seguintes).
 *
 * Performance (CRÍTICO): a última visita e o assinante vêm de SUBCONSULTAS AGREGADAS
 * (GROUP BY) anexadas via leftJoinSub — UMA query para a lista inteira, NUNCA uma por
 * cliente (proibido N+1). A lista é paginada. O detalhe é UMA query extra, só do cliente
 * aberto. Mesmo estilo set-based de App\Services\Painel\IndicadoresClientes.
 *
 * Gate por permissão `ver_clientes` (Dono/Gerente/Recepção), nunca por papel.
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

    public const POR_PAGINA = 15;

    /** Quantos agendamentos recentes mostrar no detalhe. */
    public const ULTIMOS_AGENDAMENTOS = 8;

    public function mount(): void
    {
        abort_unless(auth('web')->user()?->can('ver_clientes') ?? false, 403);
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
            'statusLabel' => AgendaIndex::STATUS_LABEL,
            'statusCor' => AgendaIndex::STATUS_COR,
        ]);
    }
}
