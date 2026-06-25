<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Cadastro CENTRAL de um estabelecimento (1:1 com Tenant) — D56.
 *
 * Fonte de verdade do admin/cobrança. Mora SEMPRE na conexão central
 * (CentralConnection): mesmo se consultado dentro do contexto de um tenant, não
 * cai no banco do tenant. Documentos/celular/CPF são guardados NORMALIZADOS
 * (só dígitos) — use soDigitos() ao gravar.
 */
class Estabelecimento extends Model
{
    use CentralConnection;

    protected $table = 'estabelecimentos';

    protected $fillable = [
        'tenant_id',
        'nome_fantasia',
        'cep',
        'logradouro',
        'numero',
        'complemento',
        'bairro',
        'cidade',
        'uf',
        'faturamento_mensal',
        'documento_tipo',
        'documento',
        'dono_nome',
        'dono_sobrenome',
        'dono_email',
        'dono_celular',
        'dono_cpf',
    ];

    protected function casts(): array
    {
        return [
            'faturamento_mensal' => 'decimal:2',
        ];
    }

    /** Normaliza para só dígitos (CPF/CNPJ/celular/CEP). null/'' → null. */
    public static function soDigitos(?string $valor): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $valor);

        return $d === '' ? null : $d;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
