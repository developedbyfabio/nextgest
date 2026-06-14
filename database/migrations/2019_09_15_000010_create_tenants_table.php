<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            // O id do tenant é o próprio slug usado na URL (nextgest.com.br/{slug}).
            $table->string('id')->primary();

            // Colunas customizadas do Nextgest (ver App\Models\Tenant::getCustomColumns()).
            $table->string('nome');
            $table->string('slug')->unique();
            $table->boolean('ativo')->default(true);

            $table->timestamps();
            $table->json('data')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
}
