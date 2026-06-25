<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela CENTRAL `estabelecimentos` (1:1 com `tenants`) — D56.
 *
 * Fonte de verdade do admin/cobrança: dados cadastrais do estabelecimento
 * (nome fantasia, endereço, faturamento, documento) + contato do dono
 * (sobrenome, celular, CPF). O usuário de login (Dono) continua no banco do
 * tenant; aqui o admin lê tudo sem entrar em cada tenant.
 *
 * Roda na conexão CENTRAL (migration de central, não de tenant). `tenant_id` é
 * string para casar com `tenants.id` (= slug). Quase tudo nullable: o onboarding
 * é que exige os campos na captura; a tela "Dados" (3b) completa depois.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estabelecimentos', function (Blueprint $table) {
            $table->id();

            // 1:1 com tenants (PK string = slug).
            $table->string('tenant_id')->unique();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            // Estabelecimento.
            $table->string('nome_fantasia')->nullable();
            $table->string('cep', 8)->nullable();
            $table->string('logradouro')->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('complemento')->nullable();
            $table->string('bairro')->nullable();
            $table->string('cidade')->nullable();
            $table->string('uf', 2)->nullable();
            $table->decimal('faturamento_mensal', 12, 2)->nullable();
            $table->string('documento_tipo', 4)->nullable(); // cpf | cnpj
            $table->string('documento', 14)->nullable();      // só dígitos

            // Contato do dono (fonte de verdade do admin; login fica no tenant).
            $table->string('dono_nome')->nullable();
            $table->string('dono_sobrenome')->nullable();
            $table->string('dono_email')->nullable();
            $table->string('dono_celular', 11)->nullable();   // só dígitos
            $table->string('dono_cpf', 11)->nullable();        // só dígitos

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estabelecimentos');
    }
};
