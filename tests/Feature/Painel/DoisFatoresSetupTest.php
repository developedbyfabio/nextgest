<?php

declare(strict_types=1);

use App\Livewire\Painel\Seguranca\DoisFatores;
use App\Models\User;
use App\Support\DoisFatores as Totp;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->tenant = criarTenant('loja2fa');
    tenancy()->initialize($this->tenant);
});

/** Código TOTP válido para o segredo atual (decifrado) do usuário. */
function codigoValido(User $user): string
{
    return (new Google2FA)->getCurrentOtp($user->fresh()->two_factor_secret);
}

it('Dono inativo: ativar gera segredo NÃO confirmado e mostra o QR', function () {
    $dono = usuarioComPapel('Dono', ['email' => 'dono@loja2fa.test']);
    $this->actingAs($dono, 'web');

    Livewire::test(DoisFatores::class)
        ->assertSet('emConfiguracao', false)
        ->call('ativar')
        ->assertSet('emConfiguracao', true)
        ->assertSee('Chave manual')
        ->assertSeeHtml('<svg'); // QR renderizado

    $dono->refresh();
    expect($dono->two_factor_secret)->not->toBeNull()
        ->and($dono->two_factor_confirmed_at)->toBeNull()  // ainda NÃO ativo
        ->and($dono->temDoisFatores())->toBeFalse();
});

it('NUNCA ativa sem um código de confirmação válido', function () {
    $dono = usuarioComPapel('Dono', ['email' => 'dono2@loja2fa.test']);
    $this->actingAs($dono, 'web');

    Livewire::test(DoisFatores::class)
        ->call('ativar')
        ->set('codigo', '000000') // errado
        ->call('confirmar')
        ->assertHasErrors('codigo');

    expect($dono->fresh()->temDoisFatores())->toBeFalse(); // não ativou
});

it('ativa com o código correto, marca confirmado e exibe 8 códigos de recuperação', function () {
    $dono = usuarioComPapel('Dono', ['email' => 'dono3@loja2fa.test']);
    $this->actingAs($dono, 'web');

    $c = Livewire::test(DoisFatores::class)->call('ativar');

    $c->set('codigo', codigoValido($dono))
        ->call('confirmar')
        ->assertHasNoErrors()
        ->assertSet('mostrarRecuperacao', true);

    $dono->refresh();
    expect($dono->temDoisFatores())->toBeTrue()
        ->and($dono->two_factor_confirmed_at)->not->toBeNull()
        ->and($dono->two_factor_recovery_codes)->toHaveCount(8);

    // Os códigos aparecem na tela uma vez (exibição inicial).
    $c->assertSee($dono->two_factor_recovery_codes[0]);
});

it('desativar exige senha: errada recusa, correta limpa tudo', function () {
    $dono = usuarioComPapel('Dono', ['email' => 'dono4@loja2fa.test', 'password' => 'senha-do-dono-123']);
    $this->actingAs($dono, 'web');

    $c = Livewire::test(DoisFatores::class)->call('ativar');
    $c->set('codigo', codigoValido($dono))->call('confirmar');
    expect($dono->fresh()->temDoisFatores())->toBeTrue();

    // senha errada → continua ativo
    $c->call('pedirSenha', 'desativar')
        ->set('senha', 'errada')
        ->call('desativar')
        ->assertHasErrors('senha');
    expect($dono->fresh()->temDoisFatores())->toBeTrue();

    // senha certa → desativa e limpa
    $c->set('senha', 'senha-do-dono-123')
        ->call('desativar')
        ->assertHasNoErrors();

    $dono->refresh();
    expect($dono->temDoisFatores())->toBeFalse()
        ->and($dono->two_factor_secret)->toBeNull()
        ->and($dono->two_factor_recovery_codes)->toBeNull()
        ->and($dono->two_factor_confirmed_at)->toBeNull();
});

it('regenerar (com senha) troca os códigos; reexibir mostra os atuais', function () {
    $dono = usuarioComPapel('Dono', ['email' => 'dono5@loja2fa.test', 'password' => 'senha-do-dono-123']);
    $this->actingAs($dono, 'web');

    $c = Livewire::test(DoisFatores::class)->call('ativar');
    $c->set('codigo', codigoValido($dono))->call('confirmar')->call('ocultarRecuperacao');

    $antigos = $dono->fresh()->two_factor_recovery_codes;

    $c->call('pedirSenha', 'regenerar')
        ->set('senha', 'senha-do-dono-123')
        ->call('regenerar')
        ->assertHasNoErrors()
        ->assertSet('mostrarRecuperacao', true);

    $novos = $dono->fresh()->two_factor_recovery_codes;
    expect($novos)->toHaveCount(8)->and($novos)->not->toBe($antigos);

    // reexibir mostra os atuais (com senha)
    $c->call('ocultarRecuperacao')
        ->call('pedirSenha', 'ver')
        ->set('senha', 'senha-do-dono-123')
        ->call('reexibir')
        ->assertHasNoErrors()
        ->assertSee($novos[0]);
});

it('o segredo e os códigos NÃO vazam no snapshot do Livewire (só HTML do QR/códigos)', function () {
    $dono = usuarioComPapel('Dono', ['email' => 'dono6@loja2fa.test']);
    $this->actingAs($dono, 'web');

    $c = Livewire::test(DoisFatores::class)->call('ativar');
    $segredo = $dono->fresh()->two_factor_secret;

    // O HTML mostra o segredo (chave manual) — é inerente ao setup (o Dono digita no app).
    expect($c->html())->toContain($segredo);

    // Mas o snapshot (estado serializado das propriedades públicas, enviado ao browser e
    // reusado a cada request) NÃO contém o segredo — ele não é propriedade pública.
    $snapshot = json_encode($c->snapshot);
    expect($snapshot)->toContain('emConfiguracao') // prova que o snapshot é real
        ->and($snapshot)->not->toContain($segredo);
});

it('gate só-Dono: Gerente recebe 403 na rota de 2FA; Dono recebe 200', function () {
    usuarioComPapel('Gerente', ['email' => 'ger@loja2fa.test']);
    usuarioComPapel('Dono', ['email' => 'donog@loja2fa.test', 'deve_trocar_senha' => false]);

    $this->actingAs(User::where('email', 'ger@loja2fa.test')->first(), 'web')
        ->get('/loja2fa/painel/2fa-inicial')
        ->assertForbidden();

    $this->actingAs(User::where('email', 'donog@loja2fa.test')->first(), 'web')
        ->get('/loja2fa/painel/2fa-inicial')
        ->assertOk()
        ->assertSee('Pular por enquanto');
});
