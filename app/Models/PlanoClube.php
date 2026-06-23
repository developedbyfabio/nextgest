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
        // Cobertura (D44): limite/dias/capacidade no nível do plano.
        'ilimitado',
        'limite_usos',
        'periodo',
        'dias_semana',
        'capacidade',
    ];

    protected function casts(): array
    {
        return [
            'preco_mensal' => 'decimal:2',
            'ativo' => 'boolean',
            'ilimitado' => 'boolean',
            'limite_usos' => 'integer',
            'dias_semana' => 'array',
            'capacidade' => 'integer',
        ];
    }

    /** Ids dos serviços COBERTOS pelo plano (reusa a pivô plano_beneficios). */
    public function servicosCobertosIds(): array
    {
        return $this->beneficios()->pluck('servico_id')->map(fn ($id) => (int) $id)->all();
    }

    /** O plano cobre este serviço? */
    public function cobreServico(int $servicoId): bool
    {
        return in_array($servicoId, $this->servicosCobertosIds(), true);
    }

    /** O dia da semana (0=dom..6=sáb) é elegível? Vazio/null = todos os dias. */
    public function cobreDia(int $diaSemana): bool
    {
        $dias = $this->dias_semana ?? [];

        return $dias === [] || in_array($diaSemana, array_map('intval', $dias), true);
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
