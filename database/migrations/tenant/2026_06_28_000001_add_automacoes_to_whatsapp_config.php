<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Banco do TENANT — config das automações de WhatsApp (Fatia 3, D77).
 *
 * ADITIVA: o cofre `whatsapp_config` ganha um JSON `automacoes` com os OVERRIDES por
 * tenant: `{ "<chave>": { "ativo": bool, "template": string }, ... }`. O CATÁLOGO
 * (categoria, variáveis, template padrão, rótulo) vive no código (App\Enums\AutomacaoWhatsapp),
 * não no banco. Nada dispara nesta fatia — só persiste config. (A tabela legada
 * `whatsapp_automacoes`, da era API Cloud da Meta, segue sem uso — não tocada.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_config', function (Blueprint $table) {
            $table->json('automacoes')->nullable()->after('status_conexao');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_config', function (Blueprint $table) {
            $table->dropColumn('automacoes');
        });
    }
};
