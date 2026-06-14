<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Super-admin do Nextgest (operador do SaaS). Vive no banco CENTRAL (tabela
 * `admins`), guard `admin`. A trait CentralConnection garante que o model use
 * sempre a conexão central (config tenancy.database.central_connection), nunca
 * o banco de um tenant — mesmo se consultado dentro de um contexto de tenant.
 */
class Admin extends Authenticatable
{
    use CentralConnection;
    use Notifiable;

    protected $table = 'admins';

    protected string $guard = 'admin';

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
