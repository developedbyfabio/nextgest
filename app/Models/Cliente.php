<?php

declare(strict_types=1);

namespace App\Models;

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
        ];
    }

    public function agendamentos(): HasMany
    {
        return $this->hasMany(Agendamento::class);
    }
}
