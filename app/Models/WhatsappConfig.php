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
        'instancia',
        'instancia_token',
        'status_conexao',
        'automacoes',
        'termo_aceito_em',
        'termo_aceito_por',
        'termo_versao',
        'telefone',
        'phone_number_id',
        'business_account_id',
        'token',
        'verificado',
        'ativo',
    ];

    protected $hidden = [
        'token',
        'instancia_token',
    ];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            // Token DAQUELA instância (Evolution) — cifrado; nunca em texto/serialização/log.
            'instancia_token' => 'encrypted',
            // Overrides das automações por tenant: {chave: {ativo, template}}. Catálogo no enum.
            'automacoes' => 'array',
            'termo_aceito_em' => 'datetime',
            'verificado' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    /**
     * Termo de risco aceito E na versão ATUAL? (D80) Bump da versão re-exige aceite.
     * Sem isso, nenhuma automação liga (trava no servidor).
     */
    public function termoAceito(): bool
    {
        return $this->termo_aceito_em !== null
            && (string) $this->termo_versao === (string) config('whatsapp.termo_versao');
    }
}
