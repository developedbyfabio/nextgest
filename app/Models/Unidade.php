<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Unidade (filial) do estabelecimento. Vive no banco do TENANT.
 */
class Unidade extends Model
{
    protected $table = 'unidades';

    protected $fillable = [
        'nome',
        'endereco',
        'telefone',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function servicos(): BelongsToMany
    {
        return $this->belongsToMany(Servico::class, 'servico_unidade');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_unidade');
    }
}
