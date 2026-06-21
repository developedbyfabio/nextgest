<?php

declare(strict_types=1);

use App\Livewire\Auth\TrocarSenha;
use App\Livewire\Painel\AlterarSenha;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = criarTenant('lojasenha');
    tenancy()->initialize($this->tenant);
});

it('1º login com deve_trocar_senha redireciona para a troca; após trocar, não mais', function () {
    $dono = usuarioComPapel('Dono', ['email' => 'dono@senha.test', 'deve_trocar_senha' => true]);

    // Flag true → qualquer rota do painel cai na troca de senha.
    $this->actingAs($dono, 'web')
        ->get('/lojasenha/painel')
        ->assertRedirect('/lojasenha/painel/senha');

    // A própria tela de troca NÃO é bloqueada (senão dava loop).
    $this->actingAs($dono, 'web')->get('/lojasenha/painel/senha')->assertOk();

    // Depois de trocar (flag false), o painel abre normalmente.
    $dono->update(['deve_trocar_senha' => false]);
    $this->actingAs($dono, 'web')->get('/lojasenha/painel')->assertOk();
});

it('a troca forçada atualiza o hash e limpa a flag', function () {
    $dono = usuarioComPapel('Dono', ['email' => 'dono2@senha.test', 'deve_trocar_senha' => true, 'password' => 'senha-inicial-1']);
    $this->actingAs($dono, 'web');

    Livewire::test(TrocarSenha::class)
        ->set('password', 'nova-senha-segura-9')
        ->set('password_confirmation', 'nova-senha-segura-9')
        ->call('salvar')
        ->assertRedirect('/lojasenha/painel');

    $dono->refresh();
    expect($dono->deve_trocar_senha)->toBeFalse()
        ->and(Hash::check('nova-senha-segura-9', $dono->password))->toBeTrue();
});

it('a troca forçada rejeita senha sem confirmação / fraca', function () {
    $dono = usuarioComPapel('Dono', ['email' => 'dono3@senha.test', 'deve_trocar_senha' => true]);
    $this->actingAs($dono, 'web');

    Livewire::test(TrocarSenha::class)
        ->set('password', 'curta')
        ->set('password_confirmation', 'curta')
        ->call('salvar')
        ->assertHasErrors('password');

    expect($dono->fresh()->deve_trocar_senha)->toBeTrue(); // não trocou
});

it('a tela de troca redireciona ao painel se a flag já está limpa', function () {
    $dono = usuarioComPapel('Dono', ['email' => 'dono4@senha.test', 'deve_trocar_senha' => false]);

    $this->actingAs($dono, 'web')
        ->get('/lojasenha/painel/senha')
        ->assertRedirect('/lojasenha/painel');
});

it('self-service: senha atual errada é recusada (não troca)', function () {
    $dono = usuarioComPapel('Dono', ['email' => 'dono5@senha.test', 'password' => 'minha-senha-atual-1']);
    $this->actingAs($dono, 'web');

    Livewire::test(AlterarSenha::class)
        ->set('atual', 'errada')
        ->set('password', 'outra-senha-boa-9')
        ->set('password_confirmation', 'outra-senha-boa-9')
        ->call('salvar')
        ->assertHasErrors('atual');

    expect(Hash::check('minha-senha-atual-1', $dono->fresh()->password))->toBeTrue(); // inalterada
});

it('self-service: senha nova fraca é recusada', function () {
    $dono = usuarioComPapel('Dono', ['email' => 'dono6@senha.test', 'password' => 'minha-senha-atual-1']);
    $this->actingAs($dono, 'web');

    Livewire::test(AlterarSenha::class)
        ->set('atual', 'minha-senha-atual-1')
        ->set('password', '123')
        ->set('password_confirmation', '123')
        ->call('salvar')
        ->assertHasErrors('password');
});

it('self-service: troca a senha com a atual correta — para os 4 papéis', function () {
    foreach (['Dono', 'Gerente', 'Recepção', 'Profissional'] as $i => $papel) {
        $u = usuarioComPapel($papel, ['email' => 'u'.$i.'@senha.test', 'password' => 'atual-correta-'.$i]);
        $this->actingAs($u, 'web');

        Livewire::test(AlterarSenha::class)
            ->set('atual', 'atual-correta-'.$i)
            ->set('password', 'nova-senha-forte-'.$i.'-x')
            ->set('password_confirmation', 'nova-senha-forte-'.$i.'-x')
            ->call('salvar')
            ->assertHasNoErrors();

        expect(Hash::check('nova-senha-forte-'.$i.'-x', $u->fresh()->password))->toBeTrue();
    }
});

it('o PORTAL do cliente não é afetado pela flag do painel (guard web)', function () {
    // Um usuário do painel com a flag não interfere no portal (guard cliente).
    usuarioComPapel('Dono', ['email' => 'dono7@senha.test', 'deve_trocar_senha' => true]);

    // A home do portal (pública) abre normalmente — o middleware é só do painel.
    $this->get('/lojasenha')->assertOk();
});
