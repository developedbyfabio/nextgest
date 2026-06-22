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
        'deve_trocar_senha',
        'e_profissional',
        'ativo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        // Segredos do 2FA: nunca em serialização/array/JSON/snapshot (padrão D38).
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'deve_trocar_senha' => 'boolean',
            'e_profissional' => 'boolean',
            'ativo' => 'boolean',
            // 2FA (TOTP) do Dono: segredo e códigos de recuperação SEMPRE cifrados (D38);
            // nunca texto puro, nunca em log. Os campos two_factor_* NÃO entram em
            // $fillable de propósito — são gravados explicitamente por App\Support\DoisFatores.
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * 2FA está ATIVO (confirmado)? Só após o Dono digitar um código válido na ativação
     * (two_factor_confirmed_at preenchido). Um segredo sem confirmação = "em
     * configuração", NÃO exige segundo fator no login.
     */
    public function temDoisFatores(): bool
    {
        return ! is_null($this->two_factor_secret)
            && ! is_null($this->two_factor_confirmed_at);
    }

    /**
     * Consome um código de recuperação (USO ÚNICO): se bater com algum dos códigos
     * guardados, remove-o e salva (não pode ser reutilizado). Comparação normalizada
     * (sem espaços, maiúsculas). Retorna true se consumiu, false se não havia match.
     */
    public function consumirCodigoRecuperacao(string $codigo): bool
    {
        $alvo = strtoupper(str_replace(' ', '', trim($codigo)));

        if ($alvo === '') {
            return false;
        }

        $codigos = $this->two_factor_recovery_codes ?? [];

        $restantes = array_values(array_filter(
            $codigos,
            fn (string $c): bool => strtoupper($c) !== $alvo,
        ));

        if (count($restantes) === count($codigos)) {
            return false; // nenhum código bateu
        }

        $this->two_factor_recovery_codes = $restantes;
        $this->save();

        return true;
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
