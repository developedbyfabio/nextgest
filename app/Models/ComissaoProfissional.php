<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Override de comissão por profissional (banco do TENANT): % específica de um
 * profissional para um serviço OU um produto. Quando existe, tem precedência sobre
 * a % padrão do serviço/produto (ver App\Services\Venda\Comanda). Tabela enxuta,
 * sem timestamps (migration 190003).
 */
class ComissaoProfissional extends Model
{
    protected $table = 'comissoes_profissional';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'servico_id',
        'produto_id',
        'percentual',
    ];

    protected function casts(): array
    {
        return [
            'percentual' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}
