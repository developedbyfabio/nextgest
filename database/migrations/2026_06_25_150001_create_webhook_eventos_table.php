<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dedupe de webhooks de pagamento (D62). CENTRAL e ADITIVA. O Mercado Pago REENVIA
 * notificações — guardamos a chave do evento já processado (gateway + evento_id) para
 * nunca reprocessar/duplicar fatura. `evento_id` é a chave por recurso/estado montada
 * no ProcessadorWebhook (ex.: `authorized_payment:<id>`, `preapproval:<id>:<status>`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_eventos', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 30);
            $table->string('evento_id');
            $table->string('tipo')->nullable();
            $table->timestamp('processado_em')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'evento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_eventos');
    }
};
