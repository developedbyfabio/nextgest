<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Quadro Kanban do estabelecimento (banco do TENANT). Dois tipos (D22):
 * `atendimento` (operação/fila do balcão) e `crm` (funil de relacionamento).
 */
class KanbanQuadro extends Model
{
    protected $table = 'kanban_quadros';

    protected $fillable = ['nome', 'tipo', 'unidade_id', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function colunas(): HasMany
    {
        return $this->hasMany(KanbanColuna::class, 'quadro_id')->orderBy('ordem');
    }
}
