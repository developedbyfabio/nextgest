<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
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

/**
 * Cria um usuário de equipe com um papel. Deve ser chamado DENTRO do contexto
 * de um tenant (após tenancy()->initialize()).
 */
function usuarioComPapel(string $papel, array $attrs = []): User
{
    $user = User::create(array_merge([
        'name' => 'Membro '.$papel,
        'email' => strtolower($papel).'@loja.test',
        'password' => 'senha-equipe-12345',
        'e_profissional' => $papel === 'Profissional',
        'ativo' => true,
    ], $attrs));

    $user->assignRole($papel);

    return $user;
}
