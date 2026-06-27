<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Banco do TENANT — config da Evolution API por salão (WhatsApp Fatia 1, D75).
 *
 * ADITIVA: o cofre `whatsapp_config` ganha o que identifica o salão na Evolution
 * ÚNICA — `instancia` (nome) e `instancia_token` (token DAQUELA instância, cifrado
 * pelo cast `encrypted` no model). A API key GLOBAL da Evolution NÃO entra aqui
 * (fica só no .env). `status_conexao` guarda o último estado conhecido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_config', function (Blueprint $table) {
            $table->string('instancia')->nullable()->after('id');
            // token da instância (cifrado via cast `encrypted` no model) — escopo limitado.
            $table->text('instancia_token')->nullable()->after('instancia');
            $table->string('status_conexao')->nullable()->after('instancia_token');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_config', function (Blueprint $table) {
            $table->dropColumn(['instancia', 'instancia_token', 'status_conexao']);
        });
    }
};
