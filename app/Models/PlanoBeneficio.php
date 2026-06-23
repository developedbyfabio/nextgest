<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Benefício de um plano (banco do TENANT): serviço incluído, ilimitado ou com cota,
 * com restrição opcional de dias/horário. SCHEMA pronto (D15/D16) — a APLICAÇÃO
 * (cobrir item na comanda / consumir cota) é fase futura; a v1 aplica desconto %.
 */
class PlanoBeneficio extends Model
{
    protected $table = 'plano_beneficios';

    public const TIPO_ILIMITADO = 'ilimitado';

    public const TIPO_COTA = 'cota';

    protected $fillable = [
        'plano_id',
        'servico_id',
        'tipo',
        'cota_quantidade',
        'dias_semana_permitidos',
        'hora_inicio',
        'hora_fim',
    ];

    protected function casts(): array
    {
        return [
            'cota_quantidade' => 'integer',
            'dias_semana_permitidos' => 'array',
        ];
    }

    public function plano(): BelongsTo
    {
        return $this->belongsTo(PlanoClube::class, 'plano_id');
    }

    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class);
    }
}
