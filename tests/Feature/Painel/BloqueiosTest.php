<?php

declare(strict_types=1);

use App\Livewire\Painel\Bloqueios\Index;
use App\Models\Bloqueio;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = criarTenant('lojaum');
    tenancy()->initialize($this->tenant);
    $this->prof = usuarioComPapel('Profissional', ['email' => 'prof@lojaum.com']);
});

it('cria um bloqueio', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    Livewire::test(Index::class)
        ->set('user_id', $this->prof->id)
        ->set('inicio', '2026-07-10T12:00')
        ->set('fim', '2026-07-10T13:00')
        ->set('motivo', 'Almoço')
        ->call('salvar')
        ->assertHasNoErrors();

    expect(Bloqueio::where('user_id', $this->prof->id)->where('motivo', 'Almoço')->exists())->toBeTrue();
});

it('exige fim depois do início', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    Livewire::test(Index::class)
        ->set('user_id', $this->prof->id)
        ->set('inicio', '2026-07-10T13:00')
        ->set('fim', '2026-07-10T12:00')
        ->call('salvar')
        ->assertHasErrors('fim');
});

it('remove um bloqueio', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');
    $bloqueio = Bloqueio::create([
        'user_id' => $this->prof->id,
        'inicio' => '2026-07-10 12:00',
        'fim' => '2026-07-10 13:00',
    ]);

    Livewire::test(Index::class)->call('excluir', $bloqueio->id);

    expect(Bloqueio::find($bloqueio->id))->toBeNull();
});

it('bloqueia Profissional (403) na página de bloqueios', function () {
    $this->actingAs(User::where('email', 'prof@lojaum.com')->first(), 'web')
        ->get('/lojaum/painel/bloqueios')
        ->assertForbidden();
});
