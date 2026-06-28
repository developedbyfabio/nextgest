<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Agendamento (o encontro marcado). Vive no banco do TENANT.
 */
class Agendamento extends Model
{
    protected $table = 'agendamentos';

    /** Status que NÃO ocupam a agenda (liberam o horário). */
    public const STATUS_LIVRES = ['cancelado', 'nao_compareceu'];

    /** Transições de status permitidas (a partir de cada status). */
    public const TRANSICOES = [
        'pendente' => ['confirmado', 'em_andamento', 'concluido', 'cancelado', 'nao_compareceu'],
        'confirmado' => ['em_andamento', 'concluido', 'cancelado', 'nao_compareceu'],
        'em_andamento' => ['concluido', 'cancelado'],
        'concluido' => [],
        'cancelado' => [],
        'nao_compareceu' => [],
    ];

    protected $fillable = [
        'unidade_id',
        'cliente_id',
        'profissional_id',
        'data_hora_inicio',
        'data_hora_fim',
        'status',
        'origem',
        'criado_por_user_id',
        'valor_total',
        'observacoes',
        'avaliacao_popup_exibido_em',
    ];

    protected function casts(): array
    {
        return [
            'data_hora_inicio' => 'datetime',
            'data_hora_fim' => 'datetime',
            'valor_total' => 'decimal:2',
            'avaliacao_popup_exibido_em' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function profissional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'profissional_id');
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(AgendamentoServico::class);
    }

    /** Avaliação do cliente para este atendimento (1 atendimento = 1 avaliação). */
    public function avaliacao(): HasOne
    {
        return $this->hasOne(Avaliacao::class);
    }

    /** Controle do lembrete de serviço por WhatsApp (idempotência, D79). */
    public function lembreteServico(): HasOne
    {
        return $this->hasOne(LembreteServico::class);
    }

    /** Controle do pedido de avaliação pós-serviço por WhatsApp (idempotência, D81). */
    public function pedidoAvaliacao(): HasOne
    {
        return $this->hasOne(PedidoAvaliacao::class);
    }

    /**
     * Agendamentos que ocupam a agenda (exclui cancelado/nao_compareceu).
     */
    public function scopeOcupantes($query)
    {
        return $query->whereNotIn('status', self::STATUS_LIVRES);
    }

    public function podeTransicionarPara(string $novo): bool
    {
        return in_array($novo, self::TRANSICOES[$this->status] ?? [], true);
    }
}
