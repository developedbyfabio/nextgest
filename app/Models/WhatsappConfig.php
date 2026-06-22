<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Configuração da integração de WhatsApp do estabelecimento (tabela do TENANT
 * `whatsapp_config`). Singleton por tenant (uma linha).
 *
 * Segurança (D21 / Fase 0b): `token` é gravado CRIPTOGRAFADO (cast `encrypted`) —
 * nunca em texto puro, nunca logado, nunca renderizado de volta no input/HTML.
 * `$hidden` evita o token vazar em serialização/array/JSON. As demais colunas
 * (telefone/phone_number_id/business_account_id) são config NÃO-secreta.
 */
class WhatsappConfig extends Model
{
    protected $table = 'whatsapp_config';

    protected $fillable = [
        'telefone',
        'phone_number_id',
        'business_account_id',
        'token',
        'verificado',
        'ativo',
    ];

    protected $hidden = [
        'token',
    ];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'verificado' => 'boolean',
            'ativo' => 'boolean',
        ];
    }
}
