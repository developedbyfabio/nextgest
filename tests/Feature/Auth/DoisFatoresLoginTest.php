<?php

declare(strict_types=1);

use App\Livewire\Auth\DesafioDoisFatores;
use App\Livewire\Auth\PainelLogin;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    tenancy()->initialize(criarTenant('login2fa'));
});

/** Cria um Dono com 2FA ATIVO (segredo + confirmado + códigos). Retorna [user, segredo]. */
function donoCom2fa(string $email = 'dono@login2fa.test', string $senha = 'senha-do-dono-123', array $recovery = ['AAAAA-BBBBB', 'CCCCC-DDDDD']): array
{
    $segredo = (new Google2FA)->generateSecretKey();

    $dono = usuarioComPapel('Dono', ['email' => $email, 'password' => $senha]);
    $dono->two_factor_secret = $segredo;
    $dono->two_factor_recovery_codes = $recovery;
    $dono->two_factor_confirmed_at = now();
    $dono->save();

    return [$dono, $segredo];
}

it('Dono COM 2FA: senha certa NÃO autentica — vai ao desafio com pendência na sessão', function () {
    [$dono] = donoCom2fa();

    Livewire::test(PainelLogin::class)
        ->set('email', 'dono@login2fa.test')
        ->set('password', 'senha-do-dono-123')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('painel.2fa.desafio', ['tenant' => 'login2fa']));

    $this->assertGuest('web'); // ainda NÃO logado
    expect(session('2fa.pendente.id'))->toBe($dono->id);
});

it('desafio com código TOTP correto efetiva o login', function () {
    [$dono, $segredo] = donoCom2fa();
    session()->put('2fa.pendente', ['id' => $dono->id, 'remember' => false]);

    Livewire::test(DesafioDoisFatores::class)
        ->set('codigo', (new Google2FA)->getCurrentOtp($segredo))
        ->call('verificar')
        ->assertHasNoErrors()
        ->assertRedirect(route('painel.dashboard', ['tenant' => 'login2fa']));

    $this->assertAuthenticatedAs($dono->fresh(), 'web');
    expect(session('2fa.pendente'))->toBeNull();
});

it('desafio com código errado é barrado (status cru, sem redirect) e não autentica', function () {
    [$dono] = donoCom2fa();
    session()->put('2fa.pendente', ['id' => $dono->id, 'remember' => false]);

    Livewire::test(DesafioDoisFatores::class)
        ->set('codigo', '000000')
        ->call('verificar')
        ->assertHasErrors('codigo')
        ->assertNoRedirect();

    $this->assertGuest('web');
});

it('código de recuperação loga UMA vez; reusar o mesmo é recusado', function () {
    [$dono] = donoCom2fa(recovery: ['AAAAA-BBBBB', 'CCCCC-DDDDD']);
    session()->put('2fa.pendente', ['id' => $dono->id, 'remember' => false]);

    // 1ª vez: funciona.
    Livewire::test(DesafioDoisFatores::class)
        ->set('codigo', 'AAAAA-BBBBB')
        ->call('verificar')
        ->assertHasNoErrors()
        ->assertRedirect(route('painel.dashboard', ['tenant' => 'login2fa']));
    $this->assertAuthenticatedAs($dono->fresh(), 'web');

    // O código foi consumido (sumiu do conjunto).
    expect($dono->fresh()->two_factor_recovery_codes)->not->toContain('AAAAA-BBBBB');

    // 2ª vez (deslogado, nova pendência): o MESMO código é recusado.
    auth('web')->logout();
    session()->put('2fa.pendente', ['id' => $dono->id, 'remember' => false]);

    Livewire::test(DesafioDoisFatores::class)
        ->set('codigo', 'AAAAA-BBBBB')
        ->call('verificar')
        ->assertHasErrors('codigo');
    $this->assertGuest('web');
});

it('desafio tem throttle: 6ª tentativa é bloqueada', function () {
    [$dono] = donoCom2fa();
    session()->put('2fa.pendente', ['id' => $dono->id, 'remember' => false]);

    $c = Livewire::test(DesafioDoisFatores::class)->set('codigo', '000000');
    for ($i = 0; $i < 5; $i++) {
        $c->call('verificar');
    }
    $c->call('verificar')->assertSee('Muitas tentativas');

    RateLimiter::clear('2fa|'.$dono->id.'|'.request()->ip());
});

it('sem pendência na sessão, o desafio volta ao login', function () {
    Livewire::test(DesafioDoisFatores::class)
        ->assertRedirect(route('painel.login', ['tenant' => 'login2fa']));
});

it('ordem: Dono com 2FA E deve_trocar_senha cai na troca de senha DEPOIS do 2FA', function () {
    [$dono, $segredo] = donoCom2fa(email: 'dono2@login2fa.test');
    $dono->update(['deve_trocar_senha' => true]);
    session()->put('2fa.pendente', ['id' => $dono->id, 'remember' => false]);

    // Passa o 2FA → loga e redireciona ao dashboard...
    Livewire::test(DesafioDoisFatores::class)
        ->set('codigo', (new Google2FA)->getCurrentOtp($segredo))
        ->call('verificar')
        ->assertRedirect(route('painel.dashboard', ['tenant' => 'login2fa']));

    // ...mas o middleware ForcarTrocaSenha então leva à troca de senha (HTTP real).
    $this->actingAs($dono->fresh(), 'web')
        ->get('/login2fa/painel')
        ->assertRedirect('/login2fa/painel/senha');
});
