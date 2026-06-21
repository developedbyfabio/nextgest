<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Serviço oferecido pelo estabelecimento. Vive no banco do TENANT.
 * Disponível em uma ou mais unidades (pivô servico_unidade) e executado por
 * profissionais (pivô servico_user).
 */
class Servico extends Model
{
    protected $table = 'servicos';

    protected $fillable = [
        'nome',
        'descricao',
        'duracao_minutos',
        'preco',
        'percentual_comissao',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'duracao_minutos' => 'integer',
            'preco' => 'decimal:2',
            'percentual_comissao' => 'decimal:2',
            'ativo' => 'boolean',
        ];
    }

    public function unidades(): BelongsToMany
    {
        return $this->belongsToMany(Unidade::class, 'servico_unidade');
    }

    public function profissionais(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'servico_user');
    }
}
