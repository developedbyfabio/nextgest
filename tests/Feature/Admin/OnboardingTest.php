<?php

declare(strict_types=1);

use App\Livewire\Admin\OnboardingEstabelecimento as Onboarding;
use App\Models\Configuracao;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Aparencia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

// admin() é definido em tests/Feature/Admin/TenantsTest.php (helper global do Pest).

afterEach(function () {
    foreach (glob(database_path('tenant_*')) as $arquivo) {
        @unlink($arquivo);
    }
});

it('exige super-admin para acessar o onboarding', function () {
    $this->get('/admin/estabelecimentos/novo')->assertRedirect(route('admin.login'));
});

it('renderiza o wizard para o super-admin', function () {
    $this->actingAs(admin(), 'admin')
        ->get('/admin/estabelecimentos/novo')
        ->assertOk()
        ->assertSee('Onboarding guiado');
});

it('sugere o slug a partir do nome', function () {
    $this->actingAs(admin(), 'admin');

    Livewire::test(Onboarding::class)
        ->set('nome', 'Barbearia do Jorge')
        ->assertSet('slug', 'barbearia-do-jorge');
});

it('não avança a etapa 1 com dados incompletos', function () {
    $this->actingAs(admin(), 'admin');

    Livewire::test(Onboarding::class)
        ->call('proximo')
        ->assertHasErrors(['nome', 'segmento', 'slug'])
        ->assertSet('etapa', 1);
});

it('sugere o template conforme o segmento', function () {
    $this->actingAs(admin(), 'admin');

    // barbearia -> template "barbearia" (#b45309, ícone sólido)
    Livewire::test(Onboarding::class)
        ->set('segmento', 'barbearia')
        ->assertSet('template', 'barbearia')
        ->assertSet('cor_principal', '#b45309')
        ->assertSet('icone_estilo', 'solid');

    // estetica -> template "premium"
    Livewire::test(Onboarding::class)
        ->set('segmento', 'estetica')
        ->assertSet('template', 'premium')
        ->assertSet('cor_principal', '#111827');
});

it('valida o horário de funcionamento', function () {
    $this->actingAs(admin(), 'admin');

    $todosFechados = collect(range(0, 6))
        ->map(fn ($d) => ['dia' => $d, 'rotulo' => 'X', 'aberto' => false, 'inicio' => '09:00', 'fim' => '18:00'])
        ->all();

    Livewire::test(Onboarding::class)
        ->set('etapa', 3)
        ->set('funcionamento', $todosFechados)
        ->call('proximo')
        ->assertHasErrors('funcionamento')
        ->assertSet('etapa', 3);

    // Fim antes do início num dia aberto.
    Livewire::test(Onboarding::class)
        ->set('etapa', 3)
        ->set('funcionamento.0.aberto', true)
        ->set('funcionamento.0.inicio', '18:00')
        ->set('funcionamento.0.fim', '09:00')
        ->call('proximo')
        ->assertHasErrors('funcionamento.0.fim')
        ->assertSet('etapa', 3);
});

it('confirma e provisiona o tenant completo (banco, dono, tema, horário)', function () {
    $this->actingAs(admin(), 'admin');

    Livewire::test(Onboarding::class)
        ->set('nome', 'Studio Lumiere')
        ->set('slug', 'studiolumiere')
        ->set('segmento', 'estetica')
        ->set('descricao', 'Estética avançada com hora marcada.')
        ->set('donoNome', 'Ana Lumiere')
        ->set('donoEmail', 'ana@studiolumiere.com')
        ->set('donoSenha', 'senha-inicial-123')
        ->call('confirmar')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.tenant.detalhe', ['tenantId' => 'studiolumiere']));

    $tenant = Tenant::find('studiolumiere');
    expect($tenant)->not->toBeNull()
        ->and($tenant->nome)->toBe('Studio Lumiere')
        ->and($tenant->segmento)->toBe('estetica');

    $dados = $tenant->run(function () {
        return [
            'papeis' => Role::count(),
            'dono' => User::where('email', 'ana@studiolumiere.com')->first()?->hasRole('Dono'),
            'cor' => Aparencia::doTenant()['cor_principal'],
            'descricao' => Configuracao::valor('descricao'),
            'horario' => Configuracao::valor('horario_funcionamento'),
        ];
    });

    expect($dados['papeis'])->toBeGreaterThan(0)
        ->and($dados['dono'])->toBeTrue()
        ->and($dados['cor'])->toBe('#111827') // template premium (segmento estética)
        ->and($dados['descricao'])->toBe('Estética avançada com hora marcada.');

    $horario = json_decode($dados['horario'], true);
    expect($horario)->toBeArray()->toHaveCount(7);
    expect(collect($horario)->firstWhere('dia', 0)['aberto'])->toBeFalse(); // domingo fechado
});

it('faz upload da logo no disco do tenant ao confirmar', function () {
    Storage::fake('public');
    $this->actingAs(admin(), 'admin');

    Livewire::test(Onboarding::class)
        ->set('nome', 'Barbearia X')
        ->set('slug', 'barbeariax')
        ->set('segmento', 'barbearia')
        ->set('donoNome', 'Dono X')
        ->set('donoEmail', 'dono@barbeariax.com')
        ->set('donoSenha', 'senha-inicial-123')
        ->set('logoUpload', UploadedFile::fake()->image('logo.png', 64, 64))
        ->call('confirmar')
        ->assertHasNoErrors();

    $logo = Tenant::find('barbeariax')->run(fn () => Aparencia::doTenant()['logo']);
    expect($logo)->toStartWith('aparencia/');
});
