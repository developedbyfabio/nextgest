<?php

declare(strict_types=1);

use App\Models\Cliente;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * Login/cadastro do cliente via Google (D95) — SEM chamar o Google (Socialite
 * mockado). Rotas CENTRAIS; o slug do tenant viaja pela sessão; callback valida,
 * inicializa o tenancy, faz find-or-create e reusa o gate de CPF (D94).
 */
beforeEach(function () {
    // NÃO inicializa tenancy: a callback é central e inicializa por conta própria.
    $this->tenant = criarTenant('lojagoog');
});

/** Monta um "usuário do Google" falso do Socialite. */
function googleUserFake(string $id, string $email, ?string $nome = 'Cliente Google', bool $verificado = true): SocialiteUser
{
    $u = (new SocialiteUser)->map(['id' => $id, 'name' => $nome, 'email' => $email]);
    $u->user = ['email_verified' => $verificado];

    return $u;
}

/** Cria um cliente DENTRO do tenant e encerra a tenancy (a callback é central). */
function clienteNoTenant(array $attrs): Cliente
{
    tenancy()->initialize(\App\Models\Tenant::find('lojagoog'));
    $c = Cliente::create($attrs);
    tenancy()->end();

    return $c;
}

it('redirect guarda o slug do tenant na sessão e envia ao Google', function () {
    Socialite::shouldReceive('driver->redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));

    $this->get('/auth/google/redirect?tenant=lojagoog')
        ->assertRedirect('https://accounts.google.com/o/oauth2/auth')
        ->assertSessionHas('google_oauth_tenant', 'lojagoog');
});

it('redirect com tenant inexistente aborta para a landing (não vai ao Google)', function () {
    $this->get('/auth/google/redirect?tenant=naoexiste')
        ->assertRedirect(route('landing'))
        ->assertSessionMissing('google_oauth_tenant');
});

it('callback sem slug válido na sessão aborta para a landing', function () {
    $this->withSession(['google_oauth_tenant' => 'naoexiste'])
        ->get('/auth/google/callback')
        ->assertRedirect(route('landing'));

    $this->withSession([])->get('/auth/google/callback')->assertRedirect(route('landing'));
});

it('callback CRIA um novo cliente com google_id e autentica no guard cliente', function () {
    Socialite::shouldReceive('driver->user')->andReturn(googleUserFake('g-novo-1', 'novo@gmail.com', 'Fulano'));

    $this->withSession(['google_oauth_tenant' => 'lojagoog'])
        ->get('/auth/google/callback')
        ->assertRedirect(route('tenant.home', ['tenant' => 'lojagoog']));

    $this->assertAuthenticated('cliente');

    tenancy()->initialize($this->tenant);
    $cli = Cliente::where('google_id', 'g-novo-1')->first();
    expect($cli)->not->toBeNull();
    expect($cli->email)->toBe('novo@gmail.com');
    expect($cli->nome)->toBe('Fulano');
    expect($cli->cpf)->toBeNull(); // sem CPF → cai no gate (D94)
    tenancy()->end();
});

it('callback VINCULA o google_id a um cliente já existente pelo e-mail (sem duplicar)', function () {
    clienteNoTenant(['nome' => 'Já Existe', 'telefone' => '11', 'email' => 'existe@gmail.com', 'cpf' => '52998224725', 'password' => 'x']);

    Socialite::shouldReceive('driver->user')->andReturn(googleUserFake('g-link-1', 'existe@gmail.com', 'Já Existe'));

    $this->withSession(['google_oauth_tenant' => 'lojagoog'])
        ->get('/auth/google/callback')
        ->assertRedirect(route('tenant.home', ['tenant' => 'lojagoog']));

    tenancy()->initialize($this->tenant);
    expect(Cliente::where('email', 'existe@gmail.com')->count())->toBe(1); // não duplicou
    expect(Cliente::where('email', 'existe@gmail.com')->value('google_id'))->toBe('g-link-1'); // vinculou
    tenancy()->end();
});

it('gate de CPF: novo usuário do Google (sem CPF) é levado a completar o cadastro', function () {
    Socialite::shouldReceive('driver->user')->andReturn(googleUserFake('g-cpf-1', 'semcpf@gmail.com'));

    $this->withSession(['google_oauth_tenant' => 'lojagoog'])->get('/auth/google/callback');

    // Segue autenticado; ao acessar o portal, o gate (D94) redireciona.
    $this->get('/lojagoog')->assertRedirect(route('cliente.completar-cadastro', ['tenant' => 'lojagoog']));
});

it('gate de CPF: usuário do Google vinculado a conta COM CPF entra direto', function () {
    clienteNoTenant(['nome' => 'Com CPF', 'telefone' => '11', 'email' => 'comcpf@gmail.com', 'cpf' => '11144477735', 'password' => 'x']);
    Socialite::shouldReceive('driver->user')->andReturn(googleUserFake('g-ok-1', 'comcpf@gmail.com'));

    $this->withSession(['google_oauth_tenant' => 'lojagoog'])->get('/auth/google/callback');

    $this->get('/lojagoog')->assertOk();
});

it('callback com e-mail não verificado volta ao login com mensagem', function () {
    Socialite::shouldReceive('driver->user')->andReturn(googleUserFake('g-nv-1', 'naoverif@gmail.com', 'X', verificado: false));

    $this->withSession(['google_oauth_tenant' => 'lojagoog'])
        ->get('/auth/google/callback')
        ->assertRedirect(route('cliente.login', ['tenant' => 'lojagoog']));

    $this->assertGuest('cliente');
});

it('callback trata cancelamento/erro do Google voltando ao login', function () {
    Socialite::shouldReceive('driver->user')->andThrow(new \RuntimeException('cancelado'));

    $this->withSession(['google_oauth_tenant' => 'lojagoog'])
        ->get('/auth/google/callback')
        ->assertRedirect(route('cliente.login', ['tenant' => 'lojagoog']));

    $this->assertGuest('cliente');
});

it('botão do Google só renderiza com client_id setado (gate de config)', function () {
    // Sem client_id → não aparece.
    config()->set('services.google.client_id', null);
    $this->get('/lojagoog/login')->assertOk()->assertDontSee('Continuar com Google');

    // Com client_id → aparece no login e no registro.
    config()->set('services.google.client_id', 'fake-client-id.apps.googleusercontent.com');
    $this->get('/lojagoog/login')->assertOk()->assertSee('Continuar com Google');
    $this->get('/lojagoog/registrar')->assertOk()->assertSee('Continuar com Google');
});
