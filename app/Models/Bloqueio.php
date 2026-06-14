<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bloqueio pontual da agenda de um profissional (folga, feriado, imprevisto).
 * Vive no banco do TENANT.
 */
class Bloqueio extends Model
{
    protected $table = 'bloqueios';

    protected $fillable = [
        'user_id',
        'inicio',
        'fim',
        'motivo',
    ];

    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'fim' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
