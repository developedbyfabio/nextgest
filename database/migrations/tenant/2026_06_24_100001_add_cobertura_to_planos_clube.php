<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clube — benefício de COBERTURA (100%) no nível do PLANO (banco do TENANT). Migra o
 * modelo de "% desconto" (Fase A, depreciado) para "cobertura de serviços":
 *
 * - `ilimitado`: sem teto de usos.
 * - `limite_usos` + `periodo`: teto N por período (ex.: 8/mês) — COMPARTILHADO pela
 *   assinatura (a família divide), contado em `usos_clube` por período.
 * - `dias_semana`: dias elegíveis (json de ints 0=dom..6=sáb; null/[] = todos).
 * - `capacidade`: nº de contas/beneficiários (1 = individual, ≥2 = família).
 *
 * Os SERVIÇOS COBERTOS reusam a pivô `plano_beneficios` (plano_id+servico_id). O
 * `plano_descontos` (% ) fica DEPRECIADO — não é dropado. Tudo aditivo (guard hasColumn).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planos_clube', function (Blueprint $table) {
            if (! Schema::hasColumn('planos_clube', 'ilimitado')) {
                $table->boolean('ilimitado')->default(true)->after('preco_mensal');
            }
            if (! Schema::hasColumn('planos_clube', 'limite_usos')) {
                $table->integer('limite_usos')->nullable()->after('ilimitado');
            }
            if (! Schema::hasColumn('planos_clube', 'periodo')) {
                $table->string('periodo')->default('mes')->after('limite_usos');
            }
            if (! Schema::hasColumn('planos_clube', 'dias_semana')) {
                $table->json('dias_semana')->nullable()->after('periodo');
            }
            if (! Schema::hasColumn('planos_clube', 'capacidade')) {
                $table->unsignedInteger('capacidade')->default(1)->after('dias_semana');
            }
        });
    }

    public function down(): void
    {
        Schema::table('planos_clube', function (Blueprint $table) {
            $table->dropColumn(['ilimitado', 'limite_usos', 'periodo', 'dias_semana', 'capacidade']);
        });
    }
};
