<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft delete nos cartões do Kanban (banco do TENANT). "Remover" um cartão passa
 * a ser ARQUIVAR (inativar), não apagar — alinhado ao princípio do projeto
 * ("excluir = inativar"). Aditivo: só acrescenta a coluna `deleted_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kanban_cartoes', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        // Reverte apenas a coluna criada por ESTA migration (deleted_at). Não toca
        // em dados de cartões; cartões arquivados deixariam de ser distinguíveis.
        Schema::table('kanban_cartoes', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
