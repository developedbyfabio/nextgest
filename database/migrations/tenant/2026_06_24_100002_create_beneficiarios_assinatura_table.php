<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Beneficiários de uma assinatura do Clube (banco do TENANT). A assinatura cobre pessoas
 * conforme a `capacidade` do plano. Cada beneficiário é (a) um `Cliente` cadastrado
 * (`cliente_id`, tem conta) OU (b) um perfil simples sem login (`nome`, ex.: criança). O
 * titular também é beneficiário (linha com seu `cliente_id`). Trava de capacidade no serviço.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('beneficiarios_assinatura')) {
            return;
        }

        Schema::create('beneficiarios_assinatura', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assinatura_id')->constrained('assinaturas_clube')->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete(); // null = sem conta
            $table->string('nome')->nullable(); // usado quando sem conta
            $table->boolean('titular')->default(false);
            $table->timestamps();

            $table->index('assinatura_id');
            $table->index('cliente_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiarios_assinatura');
    }
};
