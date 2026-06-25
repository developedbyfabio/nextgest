<?php

declare(strict_types=1);

use App\Livewire\Admin\OnboardingEstabelecimento as Onboarding;
use App\Models\Configuracao;
use App\Models\Estabelecimento;
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

    // barbearia -> template "barbearia" (#b45309, fonte do sistema)
    Livewire::test(Onboarding::class)
        ->set('segmento', 'barbearia')
        ->assertSet('template', 'barbearia')
        ->assertSet('cor_principal', '#b45309')
        ->assertSet('fonte', 'ui-sans-serif, system-ui, sans-serif');

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
        ->set('etapa', 4) // funcionamento virou a etapa 4 (D56)
        ->set('funcionamento', $todosFechados)
        ->call('proximo')
        ->assertHasErrors('funcionamento')
        ->assertSet('etapa', 4);

    // Fim antes do início num dia aberto.
    Livewire::test(Onboarding::class)
        ->set('etapa', 4)
        ->set('funcionamento.0.aberto', true)
        ->set('funcionamento.0.inicio', '18:00')
        ->set('funcionamento.0.fim', '09:00')
        ->call('proximo')
        ->assertHasErrors('funcionamento.0.fim')
        ->assertSet('etapa', 4);
});

it('confirma e provisiona o tenant completo (banco, dono, tema, horário)', function () {
    $this->actingAs(admin(), 'admin');

    Livewire::test(Onboarding::class)
        ->set('nome', 'Studio Lumiere')
        ->set('slug', 'studiolumiere')
        ->set('segmento', 'estetica')
        ->set('descricao', 'Estética avançada com hora marcada.')
        ->set('donoNome', 'Ana')
        ->set('donoSobrenome', 'Lumiere')
        ->set('donoEmail', 'ana@studiolumiere.com')
        ->set('donoCelular', '(41) 99154-1757')
        ->set('donoCpf', '529.982.247-25')
        ->set('donoSenha', 'senha-inicial-123')
        ->set('nomeFantasia', 'Studio Lumiere Estética')
        ->set('faturamentoMensal', '25000.50')
        ->set('plano', 'profissional')
        ->call('confirmar')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.tenant.detalhe', ['tenantId' => 'studiolumiere']));

    $tenant = Tenant::find('studiolumiere');
    expect($tenant)->not->toBeNull()
        ->and($tenant->nome)->toBe('Studio Lumiere')
        ->and($tenant->segmento)->toBe('estetica')
        ->and($tenant->planoAtual())->toBe('profissional')               // plano aplicado (D55)
        ->and($tenant->recursosAtivos())->toBe(['clube', 'gateway']);    // recursos do plano, segmento preservado

    // Cadastro CENTRAL (1:1) gravado com dono + estabelecimento (D56); digitos normalizados.
    $est = Estabelecimento::where('tenant_id', 'studiolumiere')->first();
    expect($est)->not->toBeNull()
        ->and($est->nome_fantasia)->toBe('Studio Lumiere Estética')
        ->and($est->dono_nome)->toBe('Ana')
        ->and($est->dono_sobrenome)->toBe('Lumiere')
        ->and($est->dono_celular)->toBe('41991541757')   // só dígitos
        ->and($est->dono_cpf)->toBe('52998224725')        // só dígitos
        ->and((float) $est->faturamento_mensal)->toBe(25000.50);

    // O LOGIN do dono continua no tenant (só o nome), inalterado.
    $dono = $tenant->run(fn () => User::where('email', 'ana@studiolumiere.com')->first());
    expect($dono)->not->toBeNull()
        ->and($dono->name)->toBe('Ana')
        ->and($dono->deve_trocar_senha)->toBeTrue();

    $dados = $tenant->run(function () {
        $dono = User::where('email', 'ana@studiolumiere.com')->first();

        return [
            'papeis' => Role::count(),
            'dono' => $dono?->hasRole('Dono'),
            'deveTrocar' => $dono?->deve_trocar_senha,
            'cor' => Aparencia::doTenant()['cor_principal'],
            'descricao' => Configuracao::valor('descricao'),
            'horario' => Configuracao::valor('horario_funcionamento'),
        ];
    });

    expect($dados['papeis'])->toBeGreaterThan(0)
        ->and($dados['dono'])->toBeTrue()
        ->and($dados['deveTrocar'])->toBeTrue() // troca obrigatória no 1º login
        ->and($dados['cor'])->toBe('#111827') // template premium (segmento estética)
        ->and($dados['descricao'])->toBe('Estética avançada com hora marcada.');

    $horario = json_decode($dados['horario'], true);
    expect($horario)->toBeArray()->toHaveCount(7);
    expect(collect($horario)->firstWhere('dia', 0)['aberto'])->toBeFalse(); // domingo fechado
});

it('faz upload de logo, cabeçalho e fundo no disco do tenant ao confirmar', function () {
    Storage::fake('public');
    $this->actingAs(admin(), 'admin');

    Livewire::test(Onboarding::class)
        ->set('nome', 'Barbearia X')
        ->set('slug', 'barbeariax')
        ->set('segmento', 'barbearia')
        ->set('donoNome', 'Dono')
        ->set('donoSobrenome', 'X')
        ->set('donoEmail', 'dono@barbeariax.com')
        ->set('donoCelular', '(41) 99154-1757')
        ->set('donoCpf', '529.982.247-25')
        ->set('donoSenha', 'senha-inicial-123')
        ->set('nomeFantasia', 'Barbearia X')
        ->set('plano', 'basico')
        ->set('logoUpload', UploadedFile::fake()->image('logo.png', 64, 64))
        ->set('headerUpload', UploadedFile::fake()->image('capa.jpg', 800, 300))
        ->set('fundoUpload', UploadedFile::fake()->image('fundo.webp', 1200, 800))
        ->call('confirmar')
        ->assertHasNoErrors();

    $ap = Tenant::find('barbeariax')->run(fn () => Aparencia::doTenant());
    expect($ap['logo'])->toStartWith('aparencia/')
        ->and($ap['header_imagem'])->toStartWith('aparencia/')
        ->and($ap['fundo_imagem'])->toStartWith('aparencia/');

    // As 3 imagens aparecem no portal do estabelecimento criado.
    $html = $this->get('/barbeariax')->assertOk()->content();
    expect($html)->toContain('/barbeariax/arquivo/'.$ap['header_imagem'])  // capa no hero
        ->toContain('/barbeariax/arquivo/'.$ap['fundo_imagem']);           // fundo no <body>
});
