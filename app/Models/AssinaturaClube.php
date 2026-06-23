<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Assinatura de um cliente a um plano do Clube (banco do TENANT). O status é a fonte
 * de verdade de "quem está ativo/inadimplente". Na Fase A o status é MANUAL (definido
 * na UI/seed); quando o webhook do gateway recorrente entrar, ele passa a virar o
 * status sozinho. Toda mudança de status gera um EventoAssinaturaClube (churn/evolução).
 *
 * `gateway_assinatura_id`/`proxima_cobranca` ficam prontos p/ o Preapproval do MP (futuro).
 */
class AssinaturaClube extends Model
{
    protected $table = 'assinaturas_clube';

    // Enum real (migração 190003): ativa | suspensa | cancelada | inadimplente (default ativa).
    public const STATUS_ATIVA = 'ativa';

    public const STATUS_SUSPENSA = 'suspensa';

    public const STATUS_INADIMPLENTE = 'inadimplente';

    public const STATUS_CANCELADA = 'cancelada';

    public const STATUS_LABEL = [
        self::STATUS_ATIVA => 'Ativa',
        self::STATUS_SUSPENSA => 'Suspensa',
        self::STATUS_INADIMPLENTE => 'Inadimplente',
        self::STATUS_CANCELADA => 'Cancelada',
    ];

    protected $fillable = [
        'cliente_id',
        'plano_id',
        'status',
        'preco_contratado',
        'data_inicio',
        'data_fim',
        'proxima_cobranca',
        'gateway_id',
        'gateway_assinatura_id',
    ];

    protected function casts(): array
    {
        return [
            'preco_contratado' => 'decimal:2',
            'data_inicio' => 'date',
            'data_fim' => 'date',
            'proxima_cobranca' => 'date',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function plano(): BelongsTo
    {
        return $this->belongsTo(PlanoClube::class, 'plano_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EventoAssinaturaClube::class, 'assinatura_id');
    }

    public function usos(): HasMany
    {
        return $this->hasMany(UsoClube::class, 'assinatura_id');
    }

    public function beneficiarios(): HasMany
    {
        return $this->hasMany(BeneficiarioAssinatura::class, 'assinatura_id');
    }

    public function scopeAtivas($query)
    {
        return $query->where('status', self::STATUS_ATIVA);
    }

    public function scopeInadimplentes($query)
    {
        return $query->where('status', self::STATUS_INADIMPLENTE);
    }
}
