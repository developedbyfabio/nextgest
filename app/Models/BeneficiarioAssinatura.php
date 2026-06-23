<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Beneficiário de uma assinatura do Clube (banco do TENANT). É (a) um Cliente cadastrado
 * (`cliente_id`, tem conta) OU (b) um perfil simples sem login (`nome`). O titular também
 * é beneficiário (`titular=true`, com seu `cliente_id`). A capacidade do plano limita
 * quantos beneficiários a assinatura pode ter (travada no serviço).
 */
class BeneficiarioAssinatura extends Model
{
    protected $table = 'beneficiarios_assinatura';

    protected $fillable = [
        'assinatura_id',
        'cliente_id',
        'nome',
        'titular',
    ];

    protected function casts(): array
    {
        return [
            'titular' => 'boolean',
        ];
    }

    public function assinatura(): BelongsTo
    {
        return $this->belongsTo(AssinaturaClube::class, 'assinatura_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /** Nome para exibição: do cliente (com conta) ou o nome livre (sem conta). */
    public function rotulo(): string
    {
        return $this->cliente?->nome ?? (string) $this->nome;
    }

    /** Tem conta (Cliente) — pode agendar pela própria conta (próximo prompt). */
    public function temConta(): bool
    {
        return ! is_null($this->cliente_id);
    }
}
