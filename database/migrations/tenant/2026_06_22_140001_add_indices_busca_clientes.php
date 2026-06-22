<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance (PERF-004): índices ADITIVOS (somente CREATE INDEX) em `clientes`.
 *
 * `clientes.nome` — atende o `ORDER BY nome` (dropdown de clientes em Vendas que
 * carrega a lista ordenada, e o autocomplete do NovoAgendamento que ordena por nome
 * e limita a 8): permite varrer o índice em ordem em vez de filesort, e ajuda a
 * otimização ORDER BY + LIMIT.
 * `clientes.telefone` — atende buscas por telefone (prefixo/igualdade).
 *
 * Limitação honesta: a busca "contém" usa `LIKE '%termo%'` (curinga À ESQUERDA), que
 * NENHUM índice B-tree cobre. A solução real para busca textual em escala é FULLTEXT
 * (MySQL) — porém é específica do MySQL e NÃO é portável para o SQLite dos testes, então
 * fica como follow-up da Fase 1 (produção, MySQL garantido). Ver módulo "Performance".
 *
 * Nada destrutivo: não remove/altera coluna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->index('nome');     // clientes_nome_index
            $table->index('telefone'); // clientes_telefone_index
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropIndex('clientes_nome_index');
            $table->dropIndex('clientes_telefone_index');
        });
    }
};
