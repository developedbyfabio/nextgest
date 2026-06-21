<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fatia 2C — comissão padrão por SERVIÇO (banco do TENANT). Aditivo: só acrescenta
 * `servicos.percentual_comissao` (nullable). Espelha `produtos.percentual_comissao`.
 * O override por profissional fica em `comissoes_profissional` (já existe, 190003).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicos', function (Blueprint $table) {
            $table->decimal('percentual_comissao', 5, 2)->nullable()->after('preco');
        });
    }

    public function down(): void
    {
        // Reverte apenas a coluna criada por esta migration.
        Schema::table('servicos', function (Blueprint $table) {
            $table->dropColumn('percentual_comissao');
        });
    }
};
