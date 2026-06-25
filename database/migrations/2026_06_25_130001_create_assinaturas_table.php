<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela CENTRAL `assinaturas` (1:1 com `tenants`) — D58.
 *
 * Mensalidade do estabelecimento ao Nextgest (salão → Nextgest). NÃO é o Clube
 * (cliente → salão, no banco do tenant). `valor_mensal` é SNAPSHOT do preço no
 * momento — mudar o catálogo (config/planos.php) depois não reescreve histórico.
 *
 * Roda na conexão CENTRAL (migrate, não tenants:migrate). `tenant_id` string p/
 * casar com `tenants.id` (= slug).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assinaturas', function (Blueprint $table) {
            $table->id();

            $table->string('tenant_id')->unique();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->string('plano')->nullable();              // snapshot do slug do plano
            $table->decimal('valor_mensal', 10, 2)->default(0); // snapshot do preço
            $table->date('data_inicio');
            $table->unsignedInteger('trial_dias')->nullable();
            $table->date('data_primeira_cobranca')->nullable(); // sobrescreve o cálculo do trial
            $table->unsignedTinyInteger('dia_vencimento')->nullable(); // 1..28 (clamp p/ mês curto)
            $table->string('status', 20)->default('em_teste'); // em_teste|ativa|atrasada|suspensa|cancelada
            $table->text('observacoes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assinaturas');
    }
};
