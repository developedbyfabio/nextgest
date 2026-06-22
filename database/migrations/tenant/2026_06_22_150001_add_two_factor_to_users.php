<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2FA (TOTP) OPCIONAL do Dono — colunas ADITIVAS em users (banco do tenant).
 *
 * - two_factor_secret: segredo TOTP, cifrado (cast `encrypted` no model, D38). Texto
 *   (o valor cifrado é bem maior que o segredo base32 cru). Enquanto
 *   two_factor_confirmed_at for NULL, o 2FA está "em configuração" e NÃO é exigido no
 *   login (espelha o modelo do Fortify).
 * - two_factor_confirmed_at: marca a ativação efetiva. NULL = não ativo. Só vira NOT
 *   NULL após o Dono digitar um código válido (prova que o app sincronizou).
 * - two_factor_recovery_codes: códigos de recuperação (uso único), cifrados como JSON
 *   (cast `encrypted:array`).
 *
 * Aditiva: nada é removido/alterado de forma destrutiva; usuários existentes ficam
 * com os três campos NULL (sem 2FA) — o login só-senha não muda para ninguém.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('deve_trocar_senha');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
            ]);
        });
    }
};
