<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Cliente final (guard `cliente`). Vive no banco do TENANT. Faz autoagendamento
 * pelo portal. Nunca recebe permissões de equipe (guard separado de `web`).
 */
class Cliente extends Authenticatable
{
    use Notifiable;

    protected $table = 'clientes';

    /**
     * Guard de autenticação deste model.
     */
    protected string $guard = 'cliente';

    protected $fillable = [
        'nome',
        'email',
        'telefone',
        'whatsapp_optout',
        'whatsapp_marketing_optout',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'whatsapp_optout' => 'boolean',
            // Opt-out SÓ de marketing/broadcast (D86) — independente do geral.
            'whatsapp_marketing_optout' => 'boolean',
        ];
    }

    public function agendamentos(): HasMany
    {
        return $this->hasMany(Agendamento::class);
    }

    /**
     * Aceita MARKETING/broadcast (D86)? Só se NÃO está no opt-out geral (transacional, D83)
     * E NÃO está no opt-out de marketing. Consumido pela Fatia 2 (broadcast). O transacional
     * (D79/D81) NÃO usa isto — depende só do `whatsapp_optout`.
     */
    public function aceitaMarketing(): bool
    {
        return ! $this->whatsapp_optout && ! $this->whatsapp_marketing_optout;
    }

    /**
     * Escopo: clientes que aceitam marketing (pré-seleção da Fatia 2). Nome no plural para
     * não colidir com o método de instância `aceitaMarketing()`.
     */
    public function scopeAceitamMarketing(Builder $query): Builder
    {
        return $query->where('whatsapp_optout', false)->where('whatsapp_marketing_optout', false);
    }
}
