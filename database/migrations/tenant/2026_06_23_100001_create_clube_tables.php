<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clube de Assinatura — Fase A: tabela de EVENTOS (auditoria/churn).
 *
 * O schema rico do clube (planos_clube, plano_beneficios, plano_descontos,
 * assinaturas_clube, usos_clube — D15–D18) JÁ EXISTE desde
 * `2026_06_14_190003_create_produtos_clube_pagamentos_tables`. A única tabela NOVA da
 * Fase A é `eventos_assinatura_clube`: o histórico de mudanças de status, fonte da
 * "evolução"/churn dos indicadores. Aditiva; guard `hasTable` por segurança.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('eventos_assinatura_clube')) {
            return;
        }

        Schema::create('eventos_assinatura_clube', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assinatura_id')->constrained('assinaturas_clube')->cascadeOnDelete();
            $table->string('tipo'); // criada | renovada | pagamento_ok | pagamento_falhou | cancelada | reativada
            $table->dateTime('ocorrido_em');
            $table->json('payload')->nullable();
            $table->timestamps();

            // Agregações "novos/cancelados no mês" e "evolução" filtram por tipo + data.
            $table->index(['tipo', 'ocorrido_em'], 'eventos_clube_tipo_data_idx');
            $table->index('ocorrido_em', 'eventos_clube_data_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_assinatura_clube');
    }
};
