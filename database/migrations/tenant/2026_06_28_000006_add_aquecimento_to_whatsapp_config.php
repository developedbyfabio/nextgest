<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Banco do TENANT — Modo Aquecimento do WhatsApp (Fatia, D82). ADITIVA.
 *
 *  - `conectado_em`: marco do DIA 1 da curva (quando o número conectou). Reinicia se o
 *    número TROCAR (ver WhatsAppService::status, compara `numero_conectado`).
 *  - `numero_conectado`: identificador da conta conectada (ownerJid) — detecta troca.
 *  - `aquecimento`: override da curva por tenant (JSON); null = usa os defaults do config.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_config', function (Blueprint $table) {
            $table->timestamp('conectado_em')->nullable()->after('status_conexao');
            $table->string('numero_conectado')->nullable()->after('conectado_em');
            $table->json('aquecimento')->nullable()->after('numero_conectado');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_config', function (Blueprint $table) {
            $table->dropColumn(['conectado_em', 'numero_conectado', 'aquecimento']);
        });
    }
};
