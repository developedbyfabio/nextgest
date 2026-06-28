<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Banco do TENANT — pedido de avaliação pós-serviço por WhatsApp (Fatia 5, D81). ADITIVA.
 *
 * Espelha `lembretes_servico` (D79): 1 linha por agendamento (`agendamento_id` ÚNICO) =
 * IDEMPOTÊNCIA (um pedido de avaliação por atendimento; re-run não duplica) + base do teto.
 * A avaliação em si continua em `avaliacoes` (D51) — aqui só o controle de envio do link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos_avaliacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agendamento_id')->unique()->constrained('agendamentos')->cascadeOnDelete();
            $table->enum('status', ['enfileirado', 'enviado', 'falhou'])->default('enfileirado');
            $table->timestamp('enfileirado_em')->nullable();
            $table->timestamp('enviado_em')->nullable();
            $table->timestamps();
            $table->index(['status', 'enfileirado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos_avaliacao');
    }
};
