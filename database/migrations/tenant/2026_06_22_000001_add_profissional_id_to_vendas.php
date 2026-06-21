<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Quem vendeu/atendeu" (profissional responsável da comanda). Em comandas de
 * finalização de atendimento vem do agendamento (travado); em avulsas é escolhido
 * e pré-preenche o profissional dos itens novos. A comissão continua por ITEM
 * (venda_itens.profissional_id) — esta coluna é só o responsável/padrão da venda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->foreignId('profissional_id')->nullable()->after('cliente_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('profissional_id');
        });
    }
};
