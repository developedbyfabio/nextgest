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
        'cpf',
        'google_id',
        'whatsapp_optout',
        'whatsapp_marketing_optout',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        // CPF é dado pessoal (LGPD): fora da serialização (snapshots do Livewire,
        // toArray/JSON) para não vazar em telas do profissional. O acesso direto
        // ($cliente->cpf) segue disponível ao CRM (gated + mascarado).
        'cpf',
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

    /** Normaliza o CPF para só dígitos ao gravar (null se vazio) — nunca guarda máscara. */
    public function setCpfAttribute(?string $valor): void
    {
        $d = preg_replace('/\D+/', '', (string) $valor);
        $this->attributes['cpf'] = $d === '' ? null : $d;
    }

    /**
     * CPF mascarado para exibição (LGPD): esconde o miolo — "123.***.**9-00" vira
     * "***.***.**9-00". Retorna '—' quando não há CPF. Uso geral (o CPF completo só
     * para quem tem permissão explícita; ver a tela de Clientes).
     */
    public function cpfMascarado(): string
    {
        $d = $this->cpf;

        if (! $d || strlen($d) !== 11) {
            return $d ? $d : '—';
        }

        return '***.***.**'.substr($d, 8, 1).'-'.substr($d, 9, 2);
    }

    /** CPF completo formatado (999.999.999-99) — só para quem tem `ver_cpf_cliente`. */
    public function cpfFormatado(): string
    {
        $d = $this->cpf;

        if (! $d || strlen($d) !== 11) {
            return $d ? $d : '—';
        }

        return substr($d, 0, 3).'.'.substr($d, 3, 3).'.'.substr($d, 6, 3).'-'.substr($d, 9, 2);
    }

    /**
     * Perfil incompleto (D96): falta CPF OU telefone. É o que o gate
     * ExigirPerfilCompletoCliente checa para levar à tela "Completar cadastro".
     * Cobre o cliente do Google (telefone '' + sem CPF) e o `telefone = ''` legado —
     * sem WhatsApp, esses precisam completar antes de circular no portal.
     */
    public function perfilIncompleto(): bool
    {
        return blank($this->cpf) || blank($this->telefone);
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
