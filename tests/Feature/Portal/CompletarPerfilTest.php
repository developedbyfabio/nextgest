<?php

declare(strict_types=1);

use App\Livewire\Portal\CompletarCadastro;
use App\Models\Cliente;
use Livewire\Livewire;

/**
 * Gate de PERFIL completo (D96 generaliza o de CPF/D94): perfil incompleto = sem CPF
 * OU sem telefone. A tela "Completar cadastro" coleta o que faltar num passo, reusando
 * CelularBr (telefone) e Cpf. CPF válido de teste: 52998224725; celular válido:
 * 41988887777 (DDD + 9…); inválidos: sequência/curto.
 */
beforeEach(function () {
    $this->tenant = criarTenant('lojaperfil');
    tenancy()->initialize($this->tenant);
});

it('cliente SEM telefone (mesmo com CPF) cai no gate', function () {
    $cli = Cliente::create(['nome' => 'A', 'email' => 'a@l.test', 'telefone' => '', 'cpf' => '52998224725', 'password' => 'x']);

    $this->actingAs($cli, 'cliente')
        ->get('/lojaperfil')
        ->assertRedirect(route('cliente.completar-cadastro', ['tenant' => 'lojaperfil']));
});

it('cliente SEM CPF (com telefone) cai no gate', function () {
    $cli = Cliente::create(['nome' => 'B', 'email' => 'b@l.test', 'telefone' => '41988887777', 'password' => 'x']);

    $this->actingAs($cli, 'cliente')
        ->get('/lojaperfil')
        ->assertRedirect(route('cliente.completar-cadastro', ['tenant' => 'lojaperfil']));
});

it('cliente com telefone E CPF passa direto', function () {
    $cli = Cliente::create(['nome' => 'C', 'email' => 'c@l.test', 'telefone' => '41988887777', 'cpf' => '52998224725', 'password' => 'x']);

    $this->actingAs($cli, 'cliente')->get('/lojaperfil')->assertOk();
});

it('completar REJEITA telefone inválido (reusa CelularBr)', function () {
    $cli = Cliente::create(['nome' => 'D', 'email' => 'd@l.test', 'telefone' => '', 'cpf' => '52998224725', 'password' => 'x']);
    $this->actingAs($cli, 'cliente');

    Livewire::test(CompletarCadastro::class)
        ->set('telefone', '123') // curto → inválido
        ->call('salvar')
        ->assertHasErrors('telefone');
});

it('completar salva o telefone em DÍGITOS e libera o portal', function () {
    $cli = Cliente::create(['nome' => 'E', 'email' => 'e@l.test', 'telefone' => '', 'cpf' => '52998224725', 'password' => 'x']);
    $this->actingAs($cli, 'cliente');

    Livewire::test(CompletarCadastro::class)
        ->set('telefone', '(41) 98888-7777') // mascarado
        ->call('salvar')
        ->assertHasNoErrors();

    expect($cli->fresh()->telefone)->toBe('41988887777'); // só dígitos
    $this->get('/lojaperfil')->assertOk(); // saiu do gate
});

it('completar exige SÓ o que falta: com CPF, não pede CPF de novo', function () {
    $cli = Cliente::create(['nome' => 'F', 'email' => 'f@l.test', 'telefone' => '', 'cpf' => '52998224725', 'password' => 'x']);
    $this->actingAs($cli, 'cliente');

    Livewire::test(CompletarCadastro::class)
        ->assertSet('precisaTelefone', true)
        ->assertSet('precisaCpf', false)
        ->set('telefone', '41988887777')
        ->call('salvar')
        ->assertHasNoErrors();

    expect($cli->fresh()->cpf)->toBe('52998224725'); // CPF intacto
});

it('cliente do Google (sem telefone e sem CPF) precisa completar OS DOIS', function () {
    // Estado do cliente criado pelo login com Google (D95): telefone '' + sem CPF.
    $cli = Cliente::create(['nome' => 'Google User', 'email' => 'g@l.test', 'telefone' => '', 'google_id' => 'g-xyz']);
    $this->actingAs($cli, 'cliente');

    $this->get('/lojaperfil')->assertRedirect(route('cliente.completar-cadastro', ['tenant' => 'lojaperfil']));

    $comp = Livewire::test(CompletarCadastro::class)
        ->assertSet('precisaTelefone', true)
        ->assertSet('precisaCpf', true);

    // Só telefone → ainda falta CPF.
    $comp->set('telefone', '41988887777')->set('cpf', '')->call('salvar')->assertHasErrors('cpf');

    // Os dois → libera.
    $comp->set('telefone', '41988887777')->set('cpf', '529.982.247-25')->call('salvar')->assertHasNoErrors();

    expect($cli->fresh()->telefone)->toBe('41988887777');
    expect($cli->fresh()->cpf)->toBe('52998224725');
    $this->get('/lojaperfil')->assertOk();
});
