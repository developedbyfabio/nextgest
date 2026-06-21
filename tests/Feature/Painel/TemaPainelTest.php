<?php

declare(strict_types=1);

use App\Livewire\Painel\Dashboard;
use App\Support\Aparencia;

/**
 * Etapa D — modelo de tema novo (substitui A/B): a MARCA do tenant é só o ACENTO
 * (+ logo + tipografia), constante nos dois modos; as SUPERFÍCIES seguem o modo
 * CLARO / ESCURO / SISTEMA do Flux (@fluxAppearance), não a cor da marca. O painel
 * não força `.dark` no servidor — o modo é aplicado no cliente.
 */
beforeEach(function () {
    $this->tenant = criarTenant('lojapainel');
    tenancy()->initialize($this->tenant);
    $this->dono = usuarioComPapel('Dono', ['email' => 'dono@p.test']);
});

it('painel emite a marca como ACENTO e não pinta a superfície com a marca', function () {
    Aparencia::salvar(['cor_principal' => '#123456']);

    $html = $this->actingAs($this->dono, 'web')->get('/lojapainel/painel')->assertOk()->content();

    expect($html)->toContain('--color-accent: #123456')        // marca alimenta o Flux
        ->and($html)->toContain('--cor-principal: #123456')
        ->and($html)->not->toContain('--cor-fundo: #')         // superfície NÃO é da marca…
        ->and($html)->not->toContain('--cor-superficie: #');   // …vem dos tokens claro/escuro
});

it('painel não força .dark no servidor e ativa o @fluxAppearance', function () {
    $html = $this->actingAs($this->dono, 'web')->get('/lojapainel/painel')->assertOk()->content();

    expect($html)->toContain('<html lang="pt-BR">')   // sem class="dark" forçada por luminância
        ->and($html)->toContain('Flux.applyAppearance'); // modo claro/escuro/sistema ativo
});

it('painel tem o seletor Claro/Escuro/Sistema no menu de perfil', function () {
    $html = $this->actingAs($this->dono, 'web')->get('/lojapainel/painel')->assertOk()->content();

    expect($html)->toContain('Claro')
        ->and($html)->toContain('Escuro')
        ->and($html)->toContain('Sistema');
});

it('dashboard renderiza KPIs e cards na superfície (ng-surface)', function () {
    Aparencia::salvar(['cor_principal' => '#123456']);

    Livewire::actingAs($this->dono, 'web');

    Livewire::test(Dashboard::class)
        ->assertSet('erro', false)
        ->assertSee('Agendamentos')
        ->assertSee('Faturamento')
        ->assertSee('Comparecimento')
        ->assertSeeHtml('ng-surface');
});

it('dashboard mostra estado vazio temático quando não há dados', function () {
    Livewire::actingAs($this->dono, 'web');

    Livewire::test(Dashboard::class)
        ->assertSee('Sem agendamentos no período')
        ->assertSeeHtml('ng-skeleton-portal');
});
