<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Banco do TENANT — consentimento de MARKETING separado (Broadcast Fatia 1, D86). ADITIVA.
 *
 * `clientes.whatsapp_marketing_optout`: opt-out SÓ de marketing/broadcast (notícias/avisos).
 * É INDEPENDENTE do `whatsapp_optout` (geral/transacional, D83): sair do marketing NÃO tira
 * os lembretes/avaliações. Consumido pela Fatia 2 (broadcast); os comandos transacionais
 * (D79/D81) continuam olhando só o opt-out geral.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->boolean('whatsapp_marketing_optout')->default(false)->after('whatsapp_optout');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('whatsapp_marketing_optout');
        });
    }
};
