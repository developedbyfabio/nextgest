<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Configuração chave/valor do estabelecimento (banco do TENANT).
 */
class Configuracao extends Model
{
    protected $table = 'configuracoes';

    protected $fillable = ['chave', 'valor'];

    public $timestamps = true;

    /**
     * Valor cru de uma chave (string) ou $default.
     */
    public static function valor(string $chave, ?string $default = null): ?string
    {
        return static::query()->where('chave', $chave)->value('valor') ?? $default;
    }

    public static function inteiro(string $chave, int $default = 0): int
    {
        $valor = static::valor($chave);

        return $valor === null ? $default : (int) $valor;
    }

    public static function booleano(string $chave, bool $default = false): bool
    {
        $valor = static::valor($chave);

        return $valor === null ? $default : in_array($valor, ['1', 'true', 'sim'], true);
    }
}
