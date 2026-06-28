<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Banco do TENANT — Controle de mensagens de WhatsApp (D83). ADITIVA.
 *
 *  - `mensagens_whatsapp`: LOG de envios (metadados + conteúdo). O conteúdo é EXPURGADO
 *    automaticamente após um prazo (config), mantendo os metadados (LGPD). Registra ENVIO
 *    (não recebimento; não cruza com `avaliacoes` — anonimato D51).
 *  - `lembretes_servico.agendado_para` / `pedidos_avaliacao.agendado_para`: marco de
 *    REPRESAMENTO pela janela de horário (status `enfileirado` + `agendado_para` no futuro
 *    = adiado; o comando re-despacha quando vence). Sem mexer no enum de status.
 *  - `whatsapp_config.janela`: override GLOBAL da janela de horário permitido (defaults em
 *    config('whatsapp.janela'); override por automação mora em `automacoes[chave].janela`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensagens_whatsapp', function (Blueprint $table) {
            $table->id();
            $table->string('automacao')->index();                  // lembrete_servico|avaliacao_pos_servico|teste|...
            $table->foreignId('agendamento_id')->nullable()->constrained('agendamentos')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('telefone')->nullable();                // destino (metadado; sobrevive ao expurgo)
            $table->string('status');                              // enviado|falhou|descartado (string, sem enum no DB)
            $table->string('motivo')->nullable();                  // falha/descarte (curto, sem segredo)
            $table->text('conteudo')->nullable();                  // texto enviado — EXPURGÁVEL (link assinado mascarado)
            $table->timestamp('conteudo_expurgado_em')->nullable();
            $table->timestamp('enviado_em')->nullable();
            $table->timestamps();
            $table->index(['automacao', 'status']);
            $table->index('created_at');
        });

        Schema::table('lembretes_servico', function (Blueprint $table) {
            $table->timestamp('agendado_para')->nullable()->after('enfileirado_em')->index();
        });

        Schema::table('pedidos_avaliacao', function (Blueprint $table) {
            $table->timestamp('agendado_para')->nullable()->after('enfileirado_em')->index();
        });

        Schema::table('whatsapp_config', function (Blueprint $table) {
            $table->json('janela')->nullable()->after('aquecimento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensagens_whatsapp');

        Schema::table('lembretes_servico', function (Blueprint $table) {
            $table->dropColumn('agendado_para');
        });

        Schema::table('pedidos_avaliacao', function (Blueprint $table) {
            $table->dropColumn('agendado_para');
        });

        Schema::table('whatsapp_config', function (Blueprint $table) {
            $table->dropColumn('janela');
        });
    }
};
