<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Banco do TENANT — conexão OAuth do gateway (Modelo A, D78).
 *
 * ADITIVA no cofre `gateways_pagamento`: dados PÚBLICOS da conta conectada (para
 * exibir na tela; nunca o token) + quando conectou. Os TOKENS OAuth (access/refresh)
 * continuam no `credenciais` (já cifrado). A chave-mestra do app (client_id/secret)
 * NÃO entra no banco — só no .env.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gateways_pagamento', function (Blueprint $table) {
            $table->string('conta_externa_id')->nullable()->after('provedor');     // id da conta no MP (público)
            $table->string('conta_externa_nome')->nullable()->after('conta_externa_id'); // nickname/e-mail (público)
            $table->timestamp('conectado_em')->nullable()->after('conta_externa_nome');
        });
    }

    public function down(): void
    {
        Schema::table('gateways_pagamento', function (Blueprint $table) {
            $table->dropColumn(['conta_externa_id', 'conta_externa_nome', 'conectado_em']);
        });
    }
};
