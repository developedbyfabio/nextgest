<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Testes Feature usam o banco CENTRAL em sqlite :memory: (RefreshDatabase).
| Tenants em teste usam arquivos sqlite próprios (criados pelo stancl), limpos
| após cada teste — assim a isolação entre tenants é real, sem tocar MySQL.
*/
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->afterEach(function () {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        foreach (glob(database_path('tenant_*')) as $arquivo) {
            @unlink($arquivo);
        }
    })
    ->in('Feature');

/**
 * Cria um tenant de teste (dispara criação do banco sqlite, migrations e seed
 * de papéis). O id é o slug.
 */
function criarTenant(string $id = 'lojateste'): Tenant
{
    return Tenant::create([
        'id' => $id,
        'nome' => ucfirst($id),
        'slug' => $id,
        'ativo' => true,
    ]);
}
