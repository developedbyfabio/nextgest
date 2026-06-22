<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indicadores (Fase I) — índice ADITIVO para a agregação por cliente sobre comandas
 * PAGAS: última visita / contagem / intervalos (frequência, risco, retenção).
 *
 * Já existia índice SIMPLES em `cliente_id` (FK), mas a agregação filtra `status='paga'`
 * e usa `data` (MIN/MAX/ordenação). Com o FK simples o plano vira full index scan +
 * table lookup p/ ler status/data (EXPLAIN: type=index, Using where). O composto
 * (cliente_id, status, data) COBRE a agregação (group por cliente → filtro de status →
 * data disponível), evitando o lookup por linha. Só CREATE INDEX (aditivo, reversível).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->index(['cliente_id', 'status', 'data'], 'vendas_cliente_status_data_index');
        });
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropIndex('vendas_cliente_status_data_index');
        });
    }
};
