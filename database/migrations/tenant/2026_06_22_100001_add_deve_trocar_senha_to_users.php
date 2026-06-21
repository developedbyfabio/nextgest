<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Troca de senha obrigatória no 1º login (painel). O Dono criado pelo onboarding
 * nasce com a senha inicial definida pelo admin e `deve_trocar_senha = true`: ao
 * entrar no painel, é forçado a definir uma senha própria antes de seguir. Vale só
 * no painel (guard web); não afeta o portal do cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('deve_trocar_senha')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('deve_trocar_senha');
        });
    }
};
