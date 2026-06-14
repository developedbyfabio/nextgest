<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Banco do TENANT — base de Pagamentos (gateways), Produtos e Clube (Apêndice B).
 * Criadas antes de Vendas porque venda_itens referencia assinaturas_clube e
 * pagamentos referencia gateways_pagamento.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- Pagamentos: configuração de gateway por estabelecimento ---
        Schema::create('gateways_pagamento', function (Blueprint $table) {
            $table->id();
            $table->enum('provedor', ['mercadopago', 'asaas']);
            $table->string('apelido')->nullable();
            // Credenciais (JSON) gravadas criptografadas via cast `encrypted` no model.
            $table->text('credenciais')->nullable();
            $table->enum('modo', ['sandbox', 'producao'])->default('sandbox');
            $table->boolean('ativo')->default(true);
            $table->boolean('padrao')->default(false);
            $table->timestamps();
        });

        // --- Produtos e Vendas (parte de cadastro) ---
        Schema::create('categorias_produto', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('produtos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_id')->nullable()->constrained('categorias_produto')->nullOnDelete();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->string('sku')->nullable();
            $table->decimal('preco_venda', 10, 2);
            $table->decimal('preco_custo', 10, 2)->nullable();
            $table->boolean('controla_estoque')->default(false);
            $table->decimal('percentual_comissao', 5, 2)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        // Estoque por filial.
        Schema::create('produto_unidade', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades')->cascadeOnDelete();
            $table->integer('quantidade')->default(0);
            $table->unique(['produto_id', 'unidade_id']);
        });

        // Override de comissão por profissional.
        Schema::create('comissoes_profissional', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('servico_id')->nullable()->constrained('servicos')->cascadeOnDelete();
            $table->foreignId('produto_id')->nullable()->constrained('produtos')->cascadeOnDelete();
            $table->decimal('percentual', 5, 2);
        });

        // --- Clube de Assinatura ---
        Schema::create('planos_clube', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->decimal('preco_mensal', 10, 2);
            $table->enum('periodicidade', ['mensal'])->default('mensal');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('plano_beneficios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plano_id')->constrained('planos_clube')->cascadeOnDelete();
            $table->foreignId('servico_id')->constrained('servicos');
            $table->enum('tipo', ['ilimitado', 'cota']);
            $table->integer('cota_quantidade')->nullable();
            $table->json('dias_semana_permitidos')->nullable();
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fim')->nullable();
            $table->timestamps();
        });

        Schema::create('plano_descontos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plano_id')->constrained('planos_clube')->cascadeOnDelete();
            $table->enum('aplica_em', ['servico', 'produto', 'categoria', 'todos']);
            $table->foreignId('servico_id')->nullable()->constrained('servicos')->nullOnDelete();
            $table->foreignId('produto_id')->nullable()->constrained('produtos')->nullOnDelete();
            $table->foreignId('categoria_id')->nullable()->constrained('categorias_produto')->nullOnDelete();
            $table->enum('tipo_desconto', ['percentual', 'valor']);
            $table->decimal('valor', 10, 2);
            $table->timestamps();
        });

        Schema::create('assinaturas_clube', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('plano_id')->constrained('planos_clube');
            $table->enum('status', ['ativa', 'suspensa', 'cancelada', 'inadimplente'])->default('ativa');
            $table->decimal('preco_contratado', 10, 2); // snapshot na adesão
            $table->date('data_inicio');
            $table->date('data_fim')->nullable();
            $table->date('proxima_cobranca')->nullable();
            $table->foreignId('gateway_id')->nullable()->constrained('gateways_pagamento')->nullOnDelete();
            $table->string('gateway_assinatura_id')->nullable();
            $table->timestamps();
            $table->index(['cliente_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assinaturas_clube');
        Schema::dropIfExists('plano_descontos');
        Schema::dropIfExists('plano_beneficios');
        Schema::dropIfExists('planos_clube');
        Schema::dropIfExists('comissoes_profissional');
        Schema::dropIfExists('produto_unidade');
        Schema::dropIfExists('produtos');
        Schema::dropIfExists('categorias_produto');
        Schema::dropIfExists('gateways_pagamento');
    }
};
