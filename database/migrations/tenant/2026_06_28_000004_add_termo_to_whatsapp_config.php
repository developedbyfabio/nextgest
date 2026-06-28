<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Banco do TENANT — aceite do termo de risco do WhatsApp (Fatia 4.5, D80). ADITIVA.
 *
 * Sem aceite, NENHUMA automação liga (trava no servidor, ver Automacoes::salvar). Guarda
 * QUEM aceitou, QUANDO e a VERSÃO do termo (bump de versão re-exige aceite).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_config', function (Blueprint $table) {
            $table->timestamp('termo_aceito_em')->nullable()->after('automacoes');
            $table->string('termo_aceito_por')->nullable()->after('termo_aceito_em');
            $table->string('termo_versao')->nullable()->after('termo_aceito_por');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_config', function (Blueprint $table) {
            $table->dropColumn(['termo_aceito_em', 'termo_aceito_por', 'termo_versao']);
        });
    }
};
