<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exceções do horário de funcionamento (por estabelecimento): feriados,
 * fechamentos e horários especiais marcados com antecedência. Camada POR CIMA do
 * horário semanal (Configuracao `horario_funcionamento`); o MotorDisponibilidade
 * consulta para fechar o dia ou restringir à faixa especial. Aditiva — não mexe
 * em tabelas existentes. Uma exceção por data (única).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('excecoes_funcionamento', function (Blueprint $table) {
            $table->id();
            $table->date('data')->unique();
            $table->enum('tipo', ['fechado', 'horario_especial']);
            $table->time('hora_inicio')->nullable(); // só em horario_especial
            $table->time('hora_fim')->nullable();
            $table->string('descricao')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('excecoes_funcionamento');
    }
};
