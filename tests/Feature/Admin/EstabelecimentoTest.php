<?php

declare(strict_types=1);

use App\Livewire\Admin\OnboardingEstabelecimento as Onboarding;
use App\Livewire\Admin\Tenants;
use App\Models\Estabelecimento;
use App\Models\Tenant;
use App\Rules\CelularBr;
use App\Rules\Cnpj;
use App\Rules\Cpf;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;

/*
| Fase 3a (D56) — camada central `estabelecimentos` (1:1 com tenants) + validadores
| BR in-house + onboarding ampliado. Cobre validadores, captura central no onboarding
| e na criação rápida, e a relação/normalização.
*/

// admin() / criarTenant() — helpers globais (tests/Pest.php).

function passa($rule, string $valor): bool
{
    return Validator::make(['c' => $valor], ['c' => [$rule]])->passes();
}

// ---- Validadores in-house --------------------------------------------------

it('valida CPF (dígitos verificadores)', function () {
    expect(passa(new Cpf, '529.982.247-25'))->toBeTrue()
        ->and(passa(new Cpf, '52998224725'))->toBeTrue()
        ->and(passa(new Cpf, '111.111.111-11'))->toBeFalse()  // repetido
        ->and(passa(new Cpf, '123.456.789-00'))->toBeFalse()  // dígito errado
        ->and(passa(new Cpf, '529.982.247-2'))->toBeFalse();  // curto
});

it('valida CNPJ (dígitos verificadores)', function () {
    expect(passa(new Cnpj, '11.222.333/0001-81'))->toBeTrue()
        ->and(passa(new Cnpj, '11222333000181'))->toBeTrue()
        ->and(passa(new Cnpj, '11.222.333/0001-00'))->toBeFalse()
        ->and(passa(new Cnpj, '00.000.000/0000-00'))->toBeFalse();
});

it('valida celular BR (DDD + 8/9 dígitos)', function () {
    expect(passa(new CelularBr, '(41) 99154-1757'))->toBeTrue()  // celular 11d
        ->and(passa(new CelularBr, '4133330000'))->toBeTrue()    // fixo 10d
        ->and(passa(new CelularBr, '419915417'))->toBeFalse()    // 9 dígitos
        ->and(passa(new CelularBr, '01912345678'))->toBeFalse()  // DDD < 11
        ->and(passa(new CelularBr, '00000000000'))->toBeFalse(); // repetido
});

// ---- Model / relação -------------------------------------------------------

it('soDigitos normaliza e a relação Tenant<->Estabelecimento funciona', function () {
    expect(Estabelecimento::soDigitos('(41) 99154-1757'))->toBe('41991541757')
        ->and(Estabelecimento::soDigitos(''))->toBeNull()
        ->and(Estabelecimento::soDigitos(null))->toBeNull();

    $t = criarTenant('lojaum');
    $est = Estabelecimento::create(['tenant_id' => 'lojaum', 'nome_fantasia' => 'Loja Um']);

    expect($est->tenant->is($t))->toBeTrue()
        ->and(Tenant::find('lojaum')->estabelecimento->nome_fantasia)->toBe('Loja Um');
});

// ---- Onboarding: validação dos novos campos --------------------------------

it('etapa Responsável rejeita CPF e celular inválidos', function () {
    $this->actingAs(admin(), 'admin');

    Livewire::test(Onboarding::class)
        ->set('etapa', 2)
        ->set('donoNome', 'Ana')
        ->set('donoSobrenome', 'Lima')
        ->set('donoEmail', 'ana@x.com')
        ->set('donoCelular', '123')              // inválido
        ->set('donoCpf', '111.111.111-11')        // inválido
        ->set('donoSenha', 'senha-12345')
        ->call('proximo')
        ->assertHasErrors(['donoCelular', 'donoCpf'])
        ->assertSet('etapa', 2);
});

it('etapa Estabelecimento exige nome fantasia e valida documento conforme o tipo', function () {
    $this->actingAs(admin(), 'admin');

    // Sem nome fantasia → erro.
    Livewire::test(Onboarding::class)
        ->set('etapa', 3)
        ->set('nomeFantasia', '')
        ->call('proximo')
        ->assertHasErrors('nomeFantasia')
        ->assertSet('etapa', 3);

    // Documento informado com tipo CNPJ inválido → erro.
    Livewire::test(Onboarding::class)
        ->set('etapa', 3)
        ->set('nomeFantasia', 'Loja Boa')
        ->set('documentoTipo', 'cnpj')
        ->set('documento', '11.222.333/0001-00')
        ->call('proximo')
        ->assertHasErrors('documento')
        ->assertSet('etapa', 3);

    // Documento sem tipo → exige o tipo.
    Livewire::test(Onboarding::class)
        ->set('etapa', 3)
        ->set('nomeFantasia', 'Loja Boa')
        ->set('documentoTipo', '')
        ->set('documento', '12345678000199')
        ->call('proximo')
        ->assertHasErrors('documentoTipo')
        ->assertSet('etapa', 3);

    // Tudo válido (só nome fantasia) → avança para a etapa 4.
    Livewire::test(Onboarding::class)
        ->set('etapa', 3)
        ->set('nomeFantasia', 'Loja Boa')
        ->call('proximo')
        ->assertHasNoErrors()
        ->assertSet('etapa', 4);
});

// ---- Criação rápida + backfill do dono -------------------------------------

it('criação rápida grava o registro central mínimo (1:1)', function () {
    $this->actingAs(admin(), 'admin');

    Livewire::test(Tenants::class)
        ->set('nome', 'Loja Rápida')
        ->set('slug', 'lojarapida')
        ->call('criar')
        ->assertHasNoErrors();

    $est = Estabelecimento::where('tenant_id', 'lojarapida')->first();
    expect($est)->not->toBeNull()
        ->and($est->nome_fantasia)->toBe('Loja Rápida')
        ->and($est->dono_nome)->toBeNull(); // dono vem depois (modal/Dados)
});

it('criar dono faz backfill do contato no cadastro central (só campos vazios)', function () {
    criarTenant('lojax');
    Estabelecimento::create(['tenant_id' => 'lojax', 'nome_fantasia' => 'Loja X']);

    $this->actingAs(admin(), 'admin');

    Livewire::test(Tenants::class)
        ->call('abrirDono', 'lojax')
        ->set('donoNome', 'Carlos')
        ->set('donoEmail', 'carlos@lojax.com')
        ->set('donoSenha', 'senha-12345')
        ->call('criarDono')
        ->assertHasNoErrors();

    $est = Estabelecimento::where('tenant_id', 'lojax')->first();
    expect($est->dono_nome)->toBe('Carlos')
        ->and($est->dono_email)->toBe('carlos@lojax.com');
});
