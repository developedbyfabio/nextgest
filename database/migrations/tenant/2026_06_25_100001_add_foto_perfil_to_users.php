<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foto de perfil do membro da equipe — coluna ADITIVA em users (banco do tenant).
 *
 * - foto_perfil: caminho relativo do arquivo no disco `public` do tenant
 *   (storage/tenant{id}/app/public/aparencia/...), servido por TenantArquivoController
 *   via Aparencia::urlArquivo(). String para acomodar o nome hashed do Storage::store.
 *   NULL = sem foto (a UI cai para as iniciais do nome no flux:avatar).
 *
 * Reaproveita o MESMO caminho de upload da Aparência (D36): WithFileUploads +
 * store('aparencia','public'); nada de disco/rota novos.
 *
 * Aditiva: usuários existentes ficam com foto_perfil NULL; nada é removido/alterado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('foto_perfil')->nullable()->after('ativo');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('foto_perfil');
        });
    }
};
