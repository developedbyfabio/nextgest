<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índice ADITIVO em `assinaturas_clube.status` para os indicadores do Clube
 * (assinantes ativos / inadimplentes = contagem por status). O índice existente é
 * (cliente_id, status) — coluna líder errada para um filtro só por status. Só CREATE
 * INDEX (aditivo, reversível). Guard por nome p/ idempotência.
 */
return new class extends Migration
{
    public function up(): void
    {
        $jaTem = collect(Schema::getIndexes('assinaturas_clube'))
            ->contains(fn ($i) => $i['name'] === 'assinaturas_clube_status_idx');

        if ($jaTem) {
            return;
        }

        Schema::table('assinaturas_clube', function (Blueprint $table) {
            $table->index('status', 'assinaturas_clube_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('assinaturas_clube', function (Blueprint $table) {
            $table->dropIndex('assinaturas_clube_status_idx');
        });
    }
};
