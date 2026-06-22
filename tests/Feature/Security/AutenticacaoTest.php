<?php

declare(strict_types=1);

use App\Livewire\Auth\PainelLogin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/*
| T5 — Autenticação: throttle (brute force), hash de senha, sem enumeração de usuário.
| Os 3 guards (admin/web/cliente) compartilham App\Livewire\Auth\Concerns\AutenticaPorGuard
| (throttle 5/min + session regenerate). Testamos o painel (web) como representante.
*/

beforeEach(function () {
    tenancy()->initialize(criarTenant('segauth'));
});

it('[T5] login da equipe tem throttle: 6ª tentativa é bloqueada (anti brute force)', function () {
    usuarioComPapel('Dono', ['email' => 'alvo@x.test', 'password' => 'senha-correta-1']);

    $c = Livewire::test(PainelLogin::class)->set('email', 'alvo@x.test')->set('password', 'errada');
    for ($i = 0; $i < 5; $i++) {
        $c->call('login'); // 5 falhas (cada uma incrementa o RateLimiter)
    }

    $c->call('login')->assertSee('Muitas tentativas'); // 6ª → throttled
});

it('[T5] mensagem de login é genérica (não revela se o e-mail existe)', function () {
    usuarioComPapel('Dono', ['email' => 'existe@x.test', 'password' => 'senha-correta-1']);

    Livewire::test(PainelLogin::class)->set('email', 'naoexiste@x.test')->set('password', 'qualquer-1')->call('login')
        ->assertSee('As credenciais informadas estão incorretas');

    Livewire::test(PainelLogin::class)->set('email', 'existe@x.test')->set('password', 'errada-1')->call('login')
        ->assertSee('As credenciais informadas estão incorretas'); // MESMA mensagem
});

it('[T5] senha é sempre hasheada no banco (nenhum texto puro)', function () {
    usuarioComPapel('Dono', ['email' => 'hash@x.test', 'password' => 'minha-senha-pura-1']);

    $cru = DB::table('users')->where('email', 'hash@x.test')->value('password');

    expect($cru)->not->toBe('minha-senha-pura-1')
        ->and(Hash::check('minha-senha-pura-1', $cru))->toBeTrue();
});

it('[T5] não há fluxo de reset/esqueci senha (sem superfície de enumeração)', function () {
    expect(Route::has('password.request'))->toBeFalse()
        ->and(Route::has('password.reset'))->toBeFalse()
        ->and(Route::has('password.email'))->toBeFalse();
});
