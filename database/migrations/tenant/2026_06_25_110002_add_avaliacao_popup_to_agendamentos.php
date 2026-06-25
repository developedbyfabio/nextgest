<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Banco do TENANT — controle do popup de avaliação (D51).
 *
 * `avaliacao_popup_exibido_em`: marca quando o popup de avaliação JÁ foi mostrado
 * ao cliente para aquele atendimento concluído (aparece UMA vez). Ignorar o popup
 * só marca esta coluna (não cria avaliação); o atendimento segue avaliável pelo
 * histórico. Aditiva (coluna nullable) — não toca dados existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->timestamp('avaliacao_popup_exibido_em')->nullable()->after('observacoes');
        });
    }

    public function down(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->dropColumn('avaliacao_popup_exibido_em');
        });
    }
};
