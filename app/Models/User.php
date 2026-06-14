<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Equipe interna do estabelecimento (guard `web`). Vive no banco do TENANT.
 * Papéis e permissões via spatie/laravel-permission (HasRoles).
 *
 * Não confundir com App\Models\Admin (super-admin central) nem com
 * App\Models\Cliente (cliente final, guard `cliente`).
 */
class User extends Authenticatable
{
    use HasFactory;
    use HasRoles;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'e_profissional',
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
            'e_profissional' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    /**
     * Filiais em que o membro atua.
     */
    public function unidades(): BelongsToMany
    {
        return $this->belongsToMany(Unidade::class, 'user_unidade');
    }

    /**
     * Serviços que o profissional sabe executar.
     */
    public function servicos(): BelongsToMany
    {
        return $this->belongsToMany(Servico::class, 'servico_user');
    }

    /**
     * Faixas de horário de trabalho.
     */
    public function horariosTrabalho(): HasMany
    {
        return $this->hasMany(HorarioTrabalho::class);
    }

    /**
     * Bloqueios pontuais (folga/feriado/imprevisto).
     */
    public function bloqueios(): HasMany
    {
        return $this->hasMany(Bloqueio::class);
    }

    /**
     * Agendamentos em que atua como profissional.
     */
    public function agendamentos(): HasMany
    {
        return $this->hasMany(Agendamento::class, 'profissional_id');
    }
}
