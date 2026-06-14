<?php

declare(strict_types=1);

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * Tenant do Nextgest (um estabelecimento).
 *
 * Identificação por caminho: o `id` do tenant é o próprio slug usado na URL
 * (nextgest.com.br/{slug}). O banco do tenant fica nomeado como
 * `tenant_{id}` (prefixo definido em config/tenancy.php).
 *
 * Colunas reais (não guardadas no JSON `data`): id, nome, slug, ativo.
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;

    /**
     * O id é o slug — string fornecida na criação, não autoincremento/UUID.
     */
    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Colunas que existem fisicamente na tabela `tenants` (as demais iriam
     * para a coluna JSON `data` do stancl).
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'nome',
            'slug',
            'ativo',
        ];
    }

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }
}
