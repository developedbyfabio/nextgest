<?php

declare(strict_types=1);

use App\Models\Configuracao;
use Database\Seeders\TenantDatabaseSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * TenantDatabaseSeeder ADITIVO/IDEMPOTENTE: provisiona tenant novo completo, re-seed
 * não altera nada, e NUNCA revoga permissão extra nem reseta config customizada.
 */
beforeEach(function () {
    $this->tenant = criarTenant('lojaseed'); // criarTenant já roda o seeder do tenant
    tenancy()->initialize($this->tenant);
});

function reseed(): void
{
    test()->seed(TenantDatabaseSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

it('provisiona tenant novo com papéis, permissões base (incl. avaliações) e configs default', function () {
    foreach (['Dono', 'Gerente', 'Recepção', 'Profissional'] as $papel) {
        expect(Role::where('name', $papel)->where('guard_name', 'web')->exists())->toBeTrue();
    }

    expect(Role::findByName('Dono', 'web')->hasPermissionTo('ver_avaliacoes'))->toBeTrue();
    expect(Role::findByName('Gerente', 'web')->hasPermissionTo('ver_avaliacoes'))->toBeTrue();

    $prof = Role::findByName('Profissional', 'web');
    expect($prof->hasPermissionTo('ver_avaliacoes_proprias'))->toBeTrue();
    expect($prof->hasPermissionTo('ver_avaliacoes'))->toBeFalse();

    expect(Configuracao::valor('intervalo_slots_minutos'))->toBe('15');
    expect(Configuracao::valor('confirmacao_automatica'))->toBe('1');
    expect(Configuracao::valor('cancelamento_antecedencia_horas'))->toBe('2');
});

it('é idempotente: re-seed não altera permissões nem configs', function () {
    $antes = Role::findByName('Dono', 'web')->permissions->pluck('name')->sort()->values()->all();
    $antesConfig = Configuracao::valor('intervalo_slots_minutos');

    reseed();

    $depois = Role::findByName('Dono', 'web')->permissions->pluck('name')->sort()->values()->all();
    expect($depois)->toBe($antes);
    expect(Configuracao::valor('intervalo_slots_minutos'))->toBe($antesConfig);
});

it('preserva permissão EXTRA do Dono (não revoga no re-seed)', function () {
    $recepcao = Role::findByName('Recepção', 'web');
    Permission::findOrCreate('relatorio_secreto', 'web'); // permissão custom do Dono
    $recepcao->givePermissionTo('relatorio_secreto');     // Recepção normalmente não tem

    reseed();

    $recepcao = Role::findByName('Recepção', 'web');
    expect($recepcao->hasPermissionTo('relatorio_secreto'))->toBeTrue();   // extra preservada
    expect($recepcao->hasPermissionTo('ver_kanban_atendimento'))->toBeTrue(); // base garantida
});

it('regarante uma permissão base que foi removida (piso garantido)', function () {
    $recepcao = Role::findByName('Recepção', 'web');
    $recepcao->revokePermissionTo('ver_agenda');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    expect(Role::findByName('Recepção', 'web')->hasPermissionTo('ver_agenda'))->toBeFalse();

    reseed();

    expect(Role::findByName('Recepção', 'web')->hasPermissionTo('ver_agenda'))->toBeTrue();
});

it('NÃO reseta uma config que o Dono alterou', function () {
    Configuracao::updateOrCreate(['chave' => 'intervalo_slots_minutos'], ['valor' => '30']);

    reseed();

    expect(Configuracao::valor('intervalo_slots_minutos'))->toBe('30'); // mantém o ajuste do Dono
});
