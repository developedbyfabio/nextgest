<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance (PERF-002 / PERF-003): índices ADITIVOS (somente CREATE INDEX).
 *
 * PERF-002 — `agendamentos.data_hora_inicio`: o dashboard agrega por RANGE de data
 * SEM filtrar por profissional. O índice composto `(profissional_id, data_hora_inicio)`
 * não cobre isso (coluna líder errada) → varredura do índice inteiro (EXPLAIN type=index,
 * ~todas as linhas). Um índice simples na data permite `range` (seek na faixa).
 *
 * PERF-003 — `vendas.data`: a lista de vendas ordena por `data DESC` (caso padrão SEM
 * filtro → EXPLAIN type=ALL + filesort) e o faturamento filtra por range de `data`. O
 * índice `(unidade_id, status)` não serve order/range por data. Índice simples em `data`
 * resolve a ordenação (scan de índice, sem filesort) e a faixa de data.
 *
 * Nada destrutivo: não remove/altera coluna nem outros índices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->index('data_hora_inicio'); // agendamentos_data_hora_inicio_index
        });

        Schema::table('vendas', function (Blueprint $table) {
            $table->index('data'); // vendas_data_index
        });
    }

    public function down(): void
    {
        // Reversível (somente índice — não toca dado).
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->dropIndex('agendamentos_data_hora_inicio_index');
        });

        Schema::table('vendas', function (Blueprint $table) {
            $table->dropIndex('vendas_data_index');
        });
    }
};
