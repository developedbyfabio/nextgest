<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Banco do TENANT — Avaliações de atendimento (D51, coleta).
 *
 * Cada avaliação se ancora num AGENDAMENTO concluído (1 atendimento = 1 avaliação,
 * via `agendamento_id` UNIQUE). profissional/cliente/unidade são denormalizados do
 * agendamento (facilita os filtros do painel no Prompt 2). O(s) serviço(s) são
 * derivados do agendamento (itens) — atendimento pode ter vários serviços.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avaliacoes', function (Blueprint $table) {
            $table->id();
            // 1 atendimento = 1 avaliação.
            $table->foreignId('agendamento_id')->unique()->constrained('agendamentos')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('profissional_id')->constrained('users');
            $table->foreignId('unidade_id')->constrained('unidades');
            $table->unsignedTinyInteger('nota'); // 1..5
            $table->text('comentario')->nullable();
            $table->timestamps();

            // Filtros do painel (Prompt 2): por profissional, unidade, nota.
            $table->index('profissional_id');
            $table->index('unidade_id');
            $table->index('nota');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avaliacoes');
    }
};
