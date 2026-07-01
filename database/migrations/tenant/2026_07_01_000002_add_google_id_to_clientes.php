<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vínculo do cliente com a conta Google (D95) — banco do TENANT, aditivo.
 *
 * `google_id` NULLABLE + índice ÚNICO por tenant (o unique tolera múltiplos NULL:
 * clientes sem Google coexistem; evita duas contas com o mesmo Google no tenant).
 * Guardamos só o identificador — nada de tokens (segurança).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('google_id')->nullable()->after('cpf');
            $table->unique('google_id');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn('google_id');
        });
    }
};
