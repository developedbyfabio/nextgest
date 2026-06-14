<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Banco do TENANT — Núcleo de Agendamento (Apêndice B).
 * unidades, users (equipe/guard web), clientes (guard cliente), servicos,
 * pivôs, horarios_trabalho, bloqueios, agendamentos, agendamento_servico,
 * configuracoes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('endereco')->nullable();
            $table->string('telefone')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        // Equipe interna (guard web). Papéis/permissões via spatie.
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('e_profissional')->default(false);
            $table->boolean('ativo')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        // Clientes finais (guard cliente).
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('email')->nullable()->unique();
            $table->string('telefone');
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('servicos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->integer('duracao_minutos');
            $table->decimal('preco', 10, 2);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        // Em quais filiais o serviço é oferecido.
        Schema::create('servico_unidade', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servico_id')->constrained('servicos')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades')->cascadeOnDelete();
            $table->unique(['servico_id', 'unidade_id']);
        });

        // Quais profissionais executam quais serviços.
        Schema::create('servico_user', function (Blueprint $table) {
            $table->foreignId('servico_id')->constrained('servicos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['servico_id', 'user_id']);
        });

        // Em quais filiais o profissional atende.
        Schema::create('user_unidade', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades')->cascadeOnDelete();
            $table->primary(['user_id', 'unidade_id']);
        });

        Schema::create('horarios_trabalho', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades')->cascadeOnDelete();
            $table->tinyInteger('dia_semana'); // 0=domingo ... 6=sábado
            $table->time('hora_inicio');
            $table->time('hora_fim');
            $table->timestamps();
            $table->index(['user_id', 'dia_semana']);
        });

        Schema::create('bloqueios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('inicio');
            $table->dateTime('fim');
            $table->string('motivo')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'inicio']);
        });

        Schema::create('agendamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidade_id')->constrained('unidades');
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('profissional_id')->constrained('users');
            $table->dateTime('data_hora_inicio');
            $table->dateTime('data_hora_fim');
            $table->enum('status', [
                'pendente', 'confirmado', 'em_andamento', 'concluido', 'cancelado', 'nao_compareceu',
            ])->default('pendente');
            $table->enum('origem', ['cliente', 'equipe']);
            $table->foreignId('criado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('valor_total', 10, 2)->default(0);
            $table->text('observacoes')->nullable();
            $table->timestamps();
            // Índice para checagem de conflito de horário do profissional.
            $table->index(['profissional_id', 'data_hora_inicio']);
        });

        // Itens do agendamento (com snapshot de preço/duração).
        Schema::create('agendamento_servico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agendamento_id')->constrained('agendamentos')->cascadeOnDelete();
            $table->foreignId('servico_id')->constrained('servicos');
            $table->decimal('preco', 10, 2);          // snapshot
            $table->integer('duracao_minutos');        // snapshot
        });

        // Configurações do estabelecimento (chave/valor).
        Schema::create('configuracoes', function (Blueprint $table) {
            $table->id();
            $table->string('chave')->unique();
            $table->text('valor')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracoes');
        Schema::dropIfExists('agendamento_servico');
        Schema::dropIfExists('agendamentos');
        Schema::dropIfExists('bloqueios');
        Schema::dropIfExists('horarios_trabalho');
        Schema::dropIfExists('user_unidade');
        Schema::dropIfExists('servico_user');
        Schema::dropIfExists('servico_unidade');
        Schema::dropIfExists('servicos');
        Schema::dropIfExists('clientes');
        Schema::dropIfExists('users');
        Schema::dropIfExists('unidades');
    }
};
