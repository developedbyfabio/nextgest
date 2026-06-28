<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Banco do TENANT — lembrete de serviço por WhatsApp (Fatia 4, D79). ADITIVA.
 *
 *  - `clientes.whatsapp_optout`: cliente que NÃO recebe mensagens (opt-out interno).
 *  - `lembretes_servico`: 1 linha por agendamento (agendamento_id ÚNICO) — a âncora de
 *    IDEMPOTÊNCIA (um lembrete por agendamento; re-run/remarcação não duplica) e a base
 *    de contagem do teto diário.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->boolean('whatsapp_optout')->default(false)->after('telefone');
        });

        Schema::create('lembretes_servico', function (Blueprint $table) {
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
        Schema::dropIfExists('lembretes_servico');
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('whatsapp_optout');
        });
    }
};
