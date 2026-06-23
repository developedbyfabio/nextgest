<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Desconto concedido por um plano (banco do TENANT). A v1 do Clube usa
 * `tipo_desconto=percentual` em `aplica_em=todos` — aplicado na comanda do
 * assinante ativo (via Comanda::definirDesconto). Os recortes por serviço/produto/
 * categoria e desconto por valor são suportados pelo schema (D17) p/ evolução.
 */
class PlanoDesconto extends Model
{
    protected $table = 'plano_descontos';

    public const APLICA_TODOS = 'todos';

    public const APLICA_SERVICO = 'servico';

    public const APLICA_PRODUTO = 'produto';

    public const APLICA_CATEGORIA = 'categoria';

    public const TIPO_PERCENTUAL = 'percentual';

    public const TIPO_VALOR = 'valor';

    protected $fillable = [
        'plano_id',
        'aplica_em',
        'servico_id',
        'produto_id',
        'categoria_id',
        'tipo_desconto',
        'valor',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
        ];
    }

    public function plano(): BelongsTo
    {
        return $this->belongsTo(PlanoClube::class, 'plano_id');
    }
}
