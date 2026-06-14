<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Super-admin do Nextgest (operador do SaaS). Vive no banco CENTRAL (tabela
 * `admins`), guard `admin`. Pinado à conexão central para nunca cair no banco
 * de um tenant caso seja consultado dentro de um contexto de tenant.
 */
class Admin extends Authenticatable
{
    use Notifiable;

    protected $table = 'admins';

    protected string $guard = 'admin';

    /**
     * Conexão central (definida em DB_CONNECTION; é a central do tenancy).
     */
    protected $connection = 'mysql';

    protected $fillable = [
        'name',
        'email',
        'password',
        'ativo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'ativo' => 'boolean',
        ];
    }
}
