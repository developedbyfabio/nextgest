<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Banco do TENANT — número de teste persistente das automações (UI/UX, D84). ADITIVA.
 *
 * `whatsapp_config.numero_teste`: o número usado pelo botão "Testar" da aba Automações
 * passa a ser salvo POR TENANT (não-secreto), p/ não precisar redigitar a cada vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_config', function (Blueprint $table) {
            $table->string('numero_teste')->nullable()->after('janela');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_config', function (Blueprint $table) {
            $table->dropColumn('numero_teste');
        });
    }
};
