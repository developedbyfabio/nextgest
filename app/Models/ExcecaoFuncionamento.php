<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Exceção do horário de funcionamento numa data (feriado/fechamento/horário
 * especial). Vive no banco do TENANT. Consultada pelo MotorDisponibilidade como
 * camada sobre o horário semanal. Ver App\Services\Agendamento\Funcionamento.
 */
class ExcecaoFuncionamento extends Model
{
    protected $table = 'excecoes_funcionamento';

    protected $fillable = [
        'data',
        'tipo',
        'hora_inicio',
        'hora_fim',
        'descricao',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
        ];
    }
}
