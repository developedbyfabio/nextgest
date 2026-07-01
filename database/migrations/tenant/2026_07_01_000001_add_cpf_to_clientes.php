<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CPF do cliente (banco do TENANT) — aditivo e não destrutivo.
 *
 * `cpf` NULLABLE + índice ÚNICO por tenant. O unique tolera múltiplos NULL (MySQL
 * e SQLite tratam NULLs como distintos), então clientes antigos sem CPF coexistem;
 * a OBRIGATORIEDADE é aplicada na validação da aplicação (autocadastro) e no gate,
 * não na coluna. Armazena 11 dígitos (sem máscara).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('cpf', 11)->nullable()->after('telefone');
            $table->unique('cpf');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropUnique(['cpf']);
            $table->dropColumn('cpf');
        });
    }
};
