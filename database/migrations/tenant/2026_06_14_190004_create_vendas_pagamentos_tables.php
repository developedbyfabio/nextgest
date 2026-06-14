<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Banco do TENANT — Vendas/comandas, estoque, usos do clube e Pagamentos
 * (Apêndice B). Dependem de tabelas criadas nos arquivos 190001 e 190003.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Comanda / venda (produtos + serviços, avulsa ou ligada a agendamento).
        Schema::create('vendas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidade_id')->constrained('unidades');
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('agendamento_id')->nullable()->constrained('agendamentos')->nullOnDelete();
            $table->enum('status', ['aberta', 'paga', 'cancelada'])->default('aberta');
            $table->decimal('valor_bruto', 10, 2)->default(0);
            $table->decimal('desconto', 10, 2)->default(0);
            $table->decimal('valor_total', 10, 2)->default(0);
            $table->foreignId('criado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('data');
            $table->timestamps();
            $table->index(['unidade_id', 'status']);
        });

        // Itens da comanda (serviço OU produto), com snapshots e comissão.
        Schema::create('venda_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venda_id')->constrained('vendas')->cascadeOnDelete();
            $table->enum('tipo', ['servico', 'produto']);
            $table->foreignId('servico_id')->nullable()->constrained('servicos')->nullOnDelete();
            $table->foreignId('produto_id')->nullable()->constrained('produtos')->nullOnDelete();
            $table->string('descricao'); // snapshot do nome
            $table->integer('quantidade')->default(1);
            $table->decimal('preco_unitario', 10, 2); // snapshot
            $table->decimal('subtotal', 10, 2);
            $table->foreignId('profissional_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('percentual_comissao', 5, 2)->nullable(); // snapshot
            $table->decimal('valor_comissao', 10, 2)->nullable();      // snapshot
            $table->boolean('coberto_por_assinatura')->default(false);
            $table->foreignId('assinatura_id')->nullable()->constrained('assinaturas_clube')->nullOnDelete();
            $table->timestamps();
        });

        // Histórico de estoque.
        Schema::create('movimentacoes_estoque', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades')->cascadeOnDelete();
            $table->enum('tipo', ['entrada', 'saida', 'ajuste']);
            $table->integer('quantidade');
            $table->string('motivo')->nullable();
            $table->foreignId('venda_id')->nullable()->constrained('vendas')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['produto_id', 'unidade_id']);
        });

        // Consumo de benefícios do clube.
        Schema::create('usos_clube', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assinatura_id')->constrained('assinaturas_clube')->cascadeOnDelete();
            $table->foreignId('plano_beneficio_id')->constrained('plano_beneficios');
            $table->foreignId('servico_id')->constrained('servicos');
            $table->foreignId('agendamento_id')->nullable()->constrained('agendamentos')->nullOnDelete();
            $table->foreignId('venda_item_id')->nullable()->constrained('venda_itens')->nullOnDelete();
            $table->string('periodo_referencia'); // ciclo, ex.: "2026-06"
            $table->dateTime('data');
            $table->timestamps();
            // Índice para contar usos da cota no ciclo.
            $table->index(['assinatura_id', 'periodo_referencia']);
        });

        // Pagamentos (online via gateway ou presencial).
        Schema::create('pagamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venda_id')->nullable()->constrained('vendas')->nullOnDelete();
            $table->foreignId('assinatura_id')->nullable()->constrained('assinaturas_clube')->nullOnDelete();
            $table->foreignId('gateway_id')->nullable()->constrained('gateways_pagamento')->nullOnDelete();
            $table->enum('metodo', ['pix', 'cartao_credito', 'cartao_debito', 'dinheiro', 'maquininha']);
            $table->decimal('valor', 10, 2);
            $table->enum('status', ['pendente', 'aprovado', 'recusado', 'estornado', 'cancelado'])->default('pendente');
            $table->string('gateway_transacao_id')->nullable();
            $table->text('pix_copia_cola')->nullable();
            $table->string('link_pagamento')->nullable();
            $table->dateTime('pago_em')->nullable();
            $table->foreignId('criado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('observacao')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        // Cartões tokenizados (SOMENTE token do gateway — nunca o cartão real).
        Schema::create('cartoes_tokenizados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('gateway_id')->constrained('gateways_pagamento')->cascadeOnDelete();
            $table->string('token');
            $table->string('bandeira')->nullable();
            $table->string('ultimos4')->nullable();
            $table->tinyInteger('validade_mes')->nullable();
            $table->smallInteger('validade_ano')->nullable();
            $table->boolean('padrao')->default(false);
            $table->timestamps();
        });

        // Auditoria de webhooks recebidos do gateway.
        Schema::create('webhooks_pagamento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gateway_id')->nullable()->constrained('gateways_pagamento')->nullOnDelete();
            $table->string('evento');
            $table->json('payload');
            $table->boolean('processado')->default(false);
            $table->dateTime('recebido_em');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhooks_pagamento');
        Schema::dropIfExists('cartoes_tokenizados');
        Schema::dropIfExists('pagamentos');
        Schema::dropIfExists('usos_clube');
        Schema::dropIfExists('movimentacoes_estoque');
        Schema::dropIfExists('venda_itens');
        Schema::dropIfExists('vendas');
    }
};
