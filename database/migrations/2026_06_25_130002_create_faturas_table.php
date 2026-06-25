<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela CENTRAL `faturas` (1:N de `assinaturas`) — D58.
 *
 * Cada fatura é a competência de um mês. `valor` é SNAPSHOT (não reescreve quando
 * o preço do plano muda). Unique (assinatura_id, competencia) impede duplicar a
 * fatura do mês. Campos de gateway ficam nullable (preenchidos numa fase futura).
 *
 * Geração de faturas NÃO é desta fase (é a 4b). Roda na conexão CENTRAL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faturas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('assinatura_id')->constrained('assinaturas')->cascadeOnDelete();

            $table->date('competencia');           // 1º dia do mês de referência
            $table->decimal('valor', 10, 2);        // snapshot
            $table->date('data_vencimento');
            $table->string('status', 20)->default('aberta'); // aberta|paga|atrasada|cancelada
            $table->date('data_pagamento')->nullable();
            $table->string('forma_pagamento')->nullable();    // manual|mercadopago|asaas
            $table->string('link_pagamento')->nullable();     // preenchido pelo gateway depois
            $table->string('gateway_referencia')->nullable();

            $table->timestamps();

            $table->unique(['assinatura_id', 'competencia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faturas');
    }
};
