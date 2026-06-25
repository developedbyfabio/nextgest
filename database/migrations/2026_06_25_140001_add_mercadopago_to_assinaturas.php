<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Referência da recorrência do Mercado Pago na assinatura SaaS (D61). CENTRAL e
 * ADITIVA. Guarda o id do preapproval, o status espelhado do MP, o link de adesão
 * (init_point, onde o dono cadastra o cartão) e a flag de cobrança automática.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assinaturas', function (Blueprint $table) {
            $table->string('mp_preapproval_id')->nullable()->unique()->after('observacoes');
            $table->string('mp_status', 30)->nullable()->after('mp_preapproval_id');
            $table->text('link_adesao')->nullable()->after('mp_status');
            $table->boolean('cobranca_automatica')->default(false)->after('link_adesao');
        });
    }

    public function down(): void
    {
        Schema::table('assinaturas', function (Blueprint $table) {
            $table->dropUnique(['mp_preapproval_id']);
            $table->dropColumn(['mp_preapproval_id', 'mp_status', 'link_adesao', 'cobranca_automatica']);
        });
    }
};
