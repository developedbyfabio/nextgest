<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plano do Clube de Assinatura (banco do TENANT). O pacote que o estabelecimento
 * vende ao cliente. "Excluir" = inativar (`ativo=false`), nunca apagar.
 */
class PlanoClube extends Model
{
    protected $table = 'planos_clube';

    protected $fillable = [
        'nome',
        'descricao',
        'preco_mensal',
        'periodicidade',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'preco_mensal' => 'decimal:2',
            'ativo' => 'boolean',
        ];
    }

    public function beneficios(): HasMany
    {
        return $this->hasMany(PlanoBeneficio::class, 'plano_id');
    }

    public function descontos(): HasMany
    {
        return $this->hasMany(PlanoDesconto::class, 'plano_id');
    }

    public function assinaturas(): HasMany
    {
        return $this->hasMany(AssinaturaClube::class, 'plano_id');
    }

    public function scopeAtivo($query)
    {
        return $query->where('ativo', true);
    }
}
