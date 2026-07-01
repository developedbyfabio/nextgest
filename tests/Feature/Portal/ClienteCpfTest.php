<?php

declare(strict_types=1);

use App\Livewire\Auth\ClienteRegistrar;
use App\Livewire\Painel\Clientes\Index as ClientesIndex;
use App\Livewire\Portal\CompletarCadastro;
use App\Models\Cliente;
use App\Models\Tenant;
use Livewire\Livewire;

/**
 * CPF do cliente (D94): obrigatório e único POR TENANT no autocadastro; gate que
 * força completar CPF; máscara/RBAC na exibição; não quebra criação fora do
 * autocadastro; não vaza em serialização (profissional). CPFs válidos de teste:
 * 52998224725 e 11144477735; inválidos: sequência/DV errado.
 */
beforeEach(function () {
    $this->tenant = criarTenant('lojacpf');
    tenancy()->initialize($this->tenant);
});

it('autocadastro EXIGE CPF (sem CPF → erro)', function () {
    Livewire::test(ClienteRegistrar::class)
        ->set('nome', 'Maria')
        ->set('telefone', '11999999999')
        ->set('email', 'maria@l.test')
        ->set('cpf', '')
        ->set('password', 'senha12345')
        ->set('password_confirmation', 'senha12345')
        ->call('registrar')
        ->assertHasErrors('cpf');
});

it('autocadastro rejeita CPF inválido (DV errado / sequência)', function () {
    foreach (['12345678900', '111.111.111-11'] as $invalido) {
        Livewire::test(ClienteRegistrar::class)
            ->set('nome', 'Ana')
            ->set('telefone', '11988887777')
            ->set('email', 'ana'.uniqid().'@l.test')
            ->set('cpf', $invalido)
            ->set('password', 'senha12345')
            ->set('password_confirmation', 'senha12345')
            ->call('registrar')
            ->assertHasErrors('cpf');
    }
});

it('autocadastro salva o CPF só com dígitos (aceita máscara no input)', function () {
    Livewire::test(ClienteRegistrar::class)
        ->set('nome', 'João')
        ->set('telefone', '11977776666')
        ->set('email', 'joao@l.test')
        ->set('cpf', '529.982.247-25') // mascarado
        ->set('password', 'senha12345')
        ->set('password_confirmation', 'senha12345')
        ->call('registrar')
        ->assertHasNoErrors();

    expect(Cliente::where('email', 'joao@l.test')->value('cpf'))->toBe('52998224725');
});

it('autocadastro rejeita CPF duplicado no MESMO tenant', function () {
    Cliente::create(['nome' => 'Titular', 'email' => 't@l.test', 'telefone' => '11', 'cpf' => '52998224725', 'password' => 'x']);

    Livewire::test(ClienteRegistrar::class)
        ->set('nome', 'Clone')
        ->set('telefone', '11955554444')
        ->set('email', 'clone@l.test')
        ->set('cpf', '529.982.247-25')
        ->set('password', 'senha12345')
        ->set('password_confirmation', 'senha12345')
        ->call('registrar')
        ->assertHasErrors('cpf');
});

it('unicidade é POR TENANT: o mesmo CPF pode existir em outro tenant', function () {
    Cliente::create(['nome' => 'Aqui', 'email' => 'aqui@l.test', 'telefone' => '11', 'cpf' => '52998224725', 'password' => 'x']);

    // Outro tenant = outro banco → mesmo CPF é permitido.
    $dois = criarTenant('lojadois');
    tenancy()->end();
    tenancy()->initialize($dois);

    Livewire::test(ClienteRegistrar::class)
        ->set('nome', 'Lá')
        ->set('telefone', '11933332222')
        ->set('email', 'la@l.test')
        ->set('cpf', '529.982.247-25')
        ->set('password', 'senha12345')
        ->set('password_confirmation', 'senha12345')
        ->call('registrar')
        ->assertHasNoErrors();

    expect(Cliente::where('cpf', '52998224725')->exists())->toBeTrue();
});

it('gate: cliente logado SEM CPF é redirecionado para completar o cadastro', function () {
    $cli = Cliente::create(['nome' => 'SemCpf', 'email' => 'sc@l.test', 'telefone' => '11', 'password' => 'x']);

    $this->actingAs($cli, 'cliente')
        ->get('/lojacpf')
        ->assertRedirect(route('cliente.completar-cadastro', ['tenant' => 'lojacpf']));
});

it('gate: cliente COM CPF acessa o portal normalmente', function () {
    $cli = Cliente::create(['nome' => 'ComCpf', 'email' => 'cc@l.test', 'telefone' => '11', 'cpf' => '52998224725', 'password' => 'x']);

    $this->actingAs($cli, 'cliente')
        ->get('/lojacpf')
        ->assertOk();
});

it('tela de completar salva o CPF e libera o portal', function () {
    $cli = Cliente::create(['nome' => 'Vai', 'email' => 'vai@l.test', 'telefone' => '11', 'password' => 'x']);
    $this->actingAs($cli, 'cliente');

    Livewire::test(CompletarCadastro::class)
        ->set('cpf', '111.444.777-35')
        ->call('salvar')
        ->assertHasNoErrors();

    expect($cli->fresh()->cpf)->toBe('11144477735');
});

it('gate é configurável: desligado, cliente sem CPF passa direto', function () {
    config()->set('nextgest.exigir_cpf_cliente', false);
    $cli = Cliente::create(['nome' => 'Livre', 'email' => 'lv@l.test', 'telefone' => '11', 'password' => 'x']);

    $this->actingAs($cli, 'cliente')->get('/lojacpf')->assertOk();
});

it('criação FORA do autocadastro (walk-in da equipe, sem CPF) não quebra', function () {
    // Mesmo padrão do NovoAgendamento::criarCliente (nome + telefone, sem CPF/senha)
    // e dos beneficiários do Clube (nome-avulso ou referência): não passam pela
    // validação do autocadastro, então a obrigatoriedade não os afeta.
    $walk = Cliente::create(['nome' => 'Balcão', 'telefone' => '11900000000']);

    expect($walk->exists)->toBeTrue();
    expect($walk->cpf)->toBeNull();
});

it('CRM mascara o CPF sem permissão e mostra completo com ver_cpf_cliente (LGPD)', function () {
    Cliente::create(['nome' => 'Fulano', 'email' => 'f@l.test', 'telefone' => '11', 'cpf' => '52998224725', 'password' => 'x']);

    // Recepção: vê Clientes, mas NÃO tem ver_cpf_cliente → mascarado.
    $this->actingAs(usuarioComPapel('Recepção'), 'web');
    $html = Livewire::test(ClientesIndex::class)->html();
    expect($html)->toContain('***.***.**7-25')->not->toContain('529.982.247-25');

    // Dono: tem ver_cpf_cliente → CPF completo.
    $this->actingAs(usuarioComPapel('Dono'), 'web');
    expect(Livewire::test(ClientesIndex::class)->html())->toContain('529.982.247-25');
});

it('CPF não vaza na serialização do modelo (views do profissional)', function () {
    $cli = Cliente::create(['nome' => 'Oculto', 'email' => 'o@l.test', 'telefone' => '11', 'cpf' => '52998224725', 'password' => 'x']);

    // $hidden → toArray/toJson (snapshots do Livewire, JSON) não expõem o CPF.
    expect($cli->toArray())->not->toHaveKey('cpf');
    expect($cli->fresh()->toArray())->not->toHaveKey('cpf');
});
