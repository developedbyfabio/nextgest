<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Venda / comanda (banco do TENANT): produtos + serviços, avulsa (balcão) ou
 * ligada a um agendamento. "Cancelar" = status `cancelada` (não apaga). Os totais
 * (`valor_bruto`/`valor_total`) são recalculados pelo serviço App\Services\Venda\Comanda.
 */
class Venda extends Model
{
    protected $table = 'vendas';

    public const STATUS_LABEL = [
        'aberta' => 'Aberta',
        'paga' => 'Paga',
        'cancelada' => 'Cancelada',
    ];

    protected $fillable = [
        'unidade_id',
        'cliente_id',
        'agendamento_id',
        'status',
        'valor_bruto',
        'desconto',
        'valor_total',
        'criado_por_user_id',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'valor_bruto' => 'decimal:2',
            'desconto' => 'decimal:2',
            'valor_total' => 'decimal:2',
            'data' => 'datetime',
        ];
    }

    public function itens(): HasMany
    {
        return $this->hasMany(VendaItem::class);
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(Pagamento::class);
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function agendamento(): BelongsTo
    {
        return $this->belongsTo(Agendamento::class);
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por_user_id');
    }
}
