<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Coluna de um quadro Kanban (banco do TENANT). Ordenada por `ordem`.
 */
class KanbanColuna extends Model
{
    protected $table = 'kanban_colunas';

    protected $fillable = ['quadro_id', 'nome', 'ordem'];

    public function quadro(): BelongsTo
    {
        return $this->belongsTo(KanbanQuadro::class, 'quadro_id');
    }

    public function cartoes(): HasMany
    {
        return $this->hasMany(KanbanCartao::class, 'coluna_id')->orderBy('ordem');
    }
}
