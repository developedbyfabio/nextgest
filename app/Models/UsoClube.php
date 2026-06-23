<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Consumo de um benefício do clube (banco do TENANT). Desconta da cota e alimenta
 * relatórios. SCHEMA pronto (D15/D18) — o registro de uso (cobrir item na comanda) é
 * fase futura; a v1 do Clube aplica desconto %, sem consumir cota.
 */
class UsoClube extends Model
{
    protected $table = 'usos_clube';

    protected $fillable = [
        'assinatura_id',
        'plano_beneficio_id',
        'servico_id',
        'agendamento_id',
        'venda_item_id',
        'periodo_referencia',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'datetime',
        ];
    }

    public function assinatura(): BelongsTo
    {
        return $this->belongsTo(AssinaturaClube::class, 'assinatura_id');
    }

    public function beneficio(): BelongsTo
    {
        return $this->belongsTo(PlanoBeneficio::class, 'plano_beneficio_id');
    }

    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class);
    }
}
