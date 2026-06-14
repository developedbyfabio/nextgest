<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Configuração de um gateway de pagamento do estabelecimento (tabela do TENANT
 * `gateways_pagamento`).
 *
 * Segurança (D21): `credenciais` é gravada CRIPTOGRAFADA (cast `encrypted:array`)
 * — nunca em texto puro. Não confundir esta classe (registro/config) com a
 * interface App\Services\Pagamentos\GatewayPagamento (contrato do adapter).
 */
class GatewayPagamento extends Model
{
    protected $table = 'gateways_pagamento';

    protected $fillable = [
        'provedor',
        'apelido',
        'credenciais',
        'modo',
        'ativo',
        'padrao',
    ];

    protected $hidden = [
        'credenciais',
    ];

    protected function casts(): array
    {
        return [
            'credenciais' => 'encrypted:array',
            'ativo' => 'boolean',
            'padrao' => 'boolean',
        ];
    }
}
