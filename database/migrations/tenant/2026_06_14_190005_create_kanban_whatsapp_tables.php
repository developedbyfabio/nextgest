<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Banco do TENANT — Kanban (atendimento e CRM) e WhatsApp (API oficial Meta).
 * (Apêndice B / D22 / D23.)
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- Kanban ---
        Schema::create('kanban_quadros', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->enum('tipo', ['atendimento', 'crm']);
            $table->foreignId('unidade_id')->nullable()->constrained('unidades')->nullOnDelete();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('kanban_colunas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quadro_id')->constrained('kanban_quadros')->cascadeOnDelete();
            $table->string('nome');
            $table->integer('ordem')->default(0);
            $table->timestamps();
        });

        Schema::create('kanban_cartoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coluna_id')->constrained('kanban_colunas')->cascadeOnDelete();
            $table->string('titulo');
            $table->text('descricao')->nullable();
            $table->integer('ordem')->default(0);
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('agendamento_id')->nullable()->constrained('agendamentos')->nullOnDelete();
            $table->foreignId('responsavel_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('valor_estimado', 10, 2)->nullable();
            $table->date('prazo')->nullable();
            $table->timestamps();
            $table->index(['coluna_id', 'ordem']);
        });

        // --- WhatsApp (API oficial — Meta Cloud) ---
        Schema::create('whatsapp_config', function (Blueprint $table) {
            $table->id();
            $table->string('telefone')->nullable();
            $table->string('phone_number_id')->nullable();
            $table->string('business_account_id')->nullable();
            // token gravado criptografado via cast `encrypted` no model.
            $table->text('token')->nullable();
            $table->boolean('verificado')->default(false);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->text('conteudo');
            $table->string('categoria')->nullable();
            $table->string('idioma')->default('pt_BR');
            $table->enum('status_aprovacao', ['pendente', 'aprovado', 'rejeitado'])->default('pendente');
            $table->timestamps();
        });

        Schema::create('whatsapp_automacoes', function (Blueprint $table) {
            $table->id();
            $table->enum('evento', [
                'lembrete_agendamento',
                'confirmacao_agendamento',
                'cancelamento_agendamento',
                'aniversario_cliente',
            ]);
            $table->foreignId('template_id')->constrained('whatsapp_templates');
            $table->integer('antecedencia_minutos')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('whatsapp_mensagens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('telefone');
            $table->foreignId('template_id')->nullable()->constrained('whatsapp_templates')->nullOnDelete();
            $table->foreignId('automacao_id')->nullable()->constrained('whatsapp_automacoes')->nullOnDelete();
            $table->text('conteudo');
            $table->enum('status', ['enviado', 'entregue', 'lido', 'falhou'])->default('enviado');
            $table->string('gateway_message_id')->nullable();
            $table->dateTime('enviado_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_mensagens');
        Schema::dropIfExists('whatsapp_automacoes');
        Schema::dropIfExists('whatsapp_templates');
        Schema::dropIfExists('whatsapp_config');
        Schema::dropIfExists('kanban_cartoes');
        Schema::dropIfExists('kanban_colunas');
        Schema::dropIfExists('kanban_quadros');
    }
};
