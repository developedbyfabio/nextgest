<?php

declare(strict_types=1);

use App\Livewire\Admin\EstabelecimentoDados;
use App\Livewire\Admin\Tenants;
use App\Models\Estabelecimento;
use App\Models\Tenant;
use Livewire\Livewire;

/*
| Fase 3b (D57) — tela "Dados": ler/editar o cadastro central (firstOrNew cria sob
| demanda para tenants antigos). Reusa validadores + model da 3a. Edita o contato
| CADASTRAL do dono, não o login (que vive no tenant).
*/

// admin() / criarTenant() — helpers globais (tests/Pest.php).

it('exige super-admin para abrir a tela Dados', function () {
    criarTenant('lojaum');

    $this->get('/admin/estabelecimentos/lojaum/dados')
        ->assertRedirect(route('admin.login'));
});

it('carrega os dados existentes do estabelecimento', function () {
    criarTenant('lojaum');
    Estabelecimento::create([
        'tenant_id' => 'lojaum',
        'nome_fantasia' => 'Loja Um Fantasia',
        'cep' => '80010000',
        'cidade' => 'Curitiba',
        'uf' => 'PR',
        'dono_nome' => 'Ana',
        'dono_sobrenome' => 'Lima',
        'dono_email' => 'ana@lojaum.com',
        'dono_celular' => '41991541757',
        'dono_cpf' => '52998224725',
    ]);

    $this->actingAs(admin(), 'admin');

    Livewire::test(EstabelecimentoDados::class, ['tenantId' => 'lojaum'])
        ->assertSet('nomeFantasia', 'Loja Um Fantasia')
        ->assertSet('cidade', 'Curitiba')
        ->assertSet('donoCelular', '41991541757')
        ->assertSet('donoCpf', '52998224725');
});

it('edita e persiste os dados (normaliza dígitos)', function () {
    criarTenant('lojaum');
    Estabelecimento::create([
        'tenant_id' => 'lojaum',
        'nome_fantasia' => 'Antigo',
        'dono_nome' => 'Ana',
        'dono_sobrenome' => 'Lima',
        'dono_email' => 'ana@lojaum.com',
        'dono_celular' => '41991541757',
        'dono_cpf' => '52998224725',
    ]);

    $this->actingAs(admin(), 'admin');

    Livewire::test(EstabelecimentoDados::class, ['tenantId' => 'lojaum'])
        ->set('nomeFantasia', 'Novo Nome')
        ->set('cidade', 'São Paulo')
        ->set('donoCelular', '(11) 98888-7777')
        ->call('salvar')
        ->assertHasNoErrors();

    $est = Estabelecimento::where('tenant_id', 'lojaum')->first();
    expect($est->nome_fantasia)->toBe('Novo Nome')
        ->and($est->cidade)->toBe('São Paulo')
        ->and($est->dono_celular)->toBe('11988887777'); // só dígitos
});

it('cria o registro sob demanda para tenant antigo sem cadastro (firstOrNew)', function () {
    criarTenant('lojavelha'); // sem Estabelecimento

    $this->actingAs(admin(), 'admin');

    expect(Estabelecimento::where('tenant_id', 'lojavelha')->exists())->toBeFalse();

    Livewire::test(EstabelecimentoDados::class, ['tenantId' => 'lojavelha'])
        ->assertSet('nomeFantasia', '') // firstOrNew → formulário vazio
        ->set('nomeFantasia', 'Loja Velha')
        ->set('donoNome', 'Carlos')
        ->set('donoSobrenome', 'Souza')
        ->set('donoEmail', 'carlos@lojavelha.com')
        ->set('donoCelular', '(41) 99154-1757')
        ->set('donoCpf', '529.982.247-25')
        ->call('salvar')
        ->assertHasNoErrors();

    $est = Estabelecimento::where('tenant_id', 'lojavelha')->first();
    expect($est)->not->toBeNull()
        ->and($est->tenant_id)->toBe('lojavelha')
        ->and($est->nome_fantasia)->toBe('Loja Velha')
        ->and($est->dono_cpf)->toBe('52998224725');

    // Não duplica: só uma linha para o tenant (tenant_id é unique).
    expect(Estabelecimento::where('tenant_id', 'lojavelha')->count())->toBe(1);
});

it('barra o salvar com CPF/celular do dono inválidos', function () {
    criarTenant('lojaum');

    $this->actingAs(admin(), 'admin');

    Livewire::test(EstabelecimentoDados::class, ['tenantId' => 'lojaum'])
        ->set('nomeFantasia', 'Loja Um')
        ->set('donoNome', 'Ana')
        ->set('donoSobrenome', 'Lima')
        ->set('donoEmail', 'ana@lojaum.com')
        ->set('donoCelular', '123')              // inválido
        ->set('donoCpf', '111.111.111-11')        // inválido
        ->call('salvar')
        ->assertHasErrors(['donoCelular', 'donoCpf']);

    expect(Estabelecimento::where('tenant_id', 'lojaum')->exists())->toBeFalse();
});

it('valida o documento opcional conforme o tipo', function () {
    criarTenant('lojaum');

    $this->actingAs(admin(), 'admin');

    Livewire::test(EstabelecimentoDados::class, ['tenantId' => 'lojaum'])
        ->set('nomeFantasia', 'Loja Um')
        ->set('donoNome', 'Ana')
        ->set('donoSobrenome', 'Lima')
        ->set('donoEmail', 'ana@lojaum.com')
        ->set('donoCelular', '(41) 99154-1757')
        ->set('donoCpf', '529.982.247-25')
        ->set('documentoTipo', 'cnpj')
        ->set('documento', '11.222.333/0001-00') // CNPJ inválido
        ->call('salvar')
        ->assertHasErrors('documento');
});

it('mostra o botão "Dados" na lista de estabelecimentos', function () {
    criarTenant('lojaum');

    $this->actingAs(admin(), 'admin');

    Livewire::test(Tenants::class)->assertSee('Dados');
});
