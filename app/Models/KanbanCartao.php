<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cartão de um quadro Kanban (banco do TENANT). Vínculos OPCIONAIS a um cliente
 * e/ou agendamento, e a um responsável da equipe. Ordenado por `ordem` dentro
 * da coluna.
 *
 * Soft delete: "remover" um cartão é ARQUIVAR (inativar), não apagar.
 */
class KanbanCartao extends Model
{
    use SoftDeletes;

    protected $table = 'kanban_cartoes';

    protected $fillable = [
        'coluna_id',
        'titulo',
        'descricao',
        'ordem',
        'cliente_id',
        'agendamento_id',
        'responsavel_user_id',
        'valor_estimado',
        'prazo',
    ];

    protected function casts(): array
    {
        return [
            'valor_estimado' => 'decimal:2',
            'prazo' => 'date',
        ];
    }

    public function coluna(): BelongsTo
    {
        return $this->belongsTo(KanbanColuna::class, 'coluna_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function agendamento(): BelongsTo
    {
        return $this->belongsTo(Agendamento::class);
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_user_id');
    }
}
