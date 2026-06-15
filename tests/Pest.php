<?php

declare(strict_types=1);

use App\Models\HorarioTrabalho;
use App\Models\Tenant;
use App\Models\Unidade;
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

        // Diretórios de storage por tenant criados em testes (uploads/disco fake).
        foreach (glob(storage_path('tenant*'), GLOB_ONLYDIR) as $dir) {
            \Illuminate\Support\Facades\File::deleteDirectory($dir);
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

/**
 * Cria um profissional vinculado a uma unidade e serviços, com faixas de
 * horário. $horarios: lista de [dia_semana, 'HH:MM', 'HH:MM']. Dentro do tenant.
 */
function profissionalAgenda(Unidade $unidade, array $servicos, array $horarios, array $attrs = []): User
{
    $prof = usuarioComPapel('Profissional', array_merge([
        'email' => 'prof'.uniqid().'@loja.test',
        'e_profissional' => true,
    ], $attrs));

    $prof->unidades()->sync([$unidade->id]);
    $prof->servicos()->sync(collect($servicos)->pluck('id')->all());

    foreach ($horarios as [$dia, $ini, $fim]) {
        HorarioTrabalho::create([
            'user_id' => $prof->id,
            'unidade_id' => $unidade->id,
            'dia_semana' => $dia,
            'hora_inicio' => $ini,
            'hora_fim' => $fim,
        ]);
    }

    return $prof;
}
