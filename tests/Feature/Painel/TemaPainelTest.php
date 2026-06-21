<?php

declare(strict_types=1);

use App\Livewire\Painel\Dashboard;
use App\Support\Aparencia;

/**
 * Etapa B — o shell do painel e o dashboard refletem a identidade do
 * estabelecimento (CSS vars), com modo escuro do Flux ligado automaticamente
 * quando a superfície da marca é escura (dark-safe, sem bg-white/zinc fixos).
 */
beforeEach(function () {
    $this->tenant = criarTenant('lojapainel');
    tenancy()->initialize($this->tenant);
    $this->dono = usuarioComPapel('Dono', ['email' => 'dono@p.test']);
});

it('painel com superfície ESCURA liga o .dark do Flux e emite a superfície da marca', function () {
    Aparencia::salvar(['cor_superficie' => '#0f172a', 'cor_fundo' => '#020617', 'cor_texto' => '#f8fafc']);

    $html = $this->actingAs($this->dono, 'web')->get('/lojapainel/painel')->assertOk()->content();

    expect($html)->toContain('class="dark"')                 // Flux em modo escuro
        ->and($html)->toContain('--cor-superficie: #0f172a') // superfície da marca aplicada
        ->and($html)->toContain('--cor-fundo: #020617');
});

it('painel com superfície CLARA não liga o .dark', function () {
    Aparencia::salvar(['cor_superficie' => '#ffffff', 'cor_fundo' => '#f4f4f5', 'cor_texto' => '#18181b']);

    $html = $this->actingAs($this->dono, 'web')->get('/lojapainel/painel')->assertOk()->content();

    expect($html)->not->toContain('class="dark"')
        ->and($html)->toContain('--cor-superficie: #ffffff');
});

it('a tela de login do painel é dark-safe com superfície escura', function () {
    Aparencia::salvar(['cor_superficie' => '#0f172a', 'cor_texto' => '#f8fafc']);
    tenancy()->end();

    $html = $this->get('/lojapainel/painel/login')->assertOk()->content();

    expect($html)->toContain('class="dark"')
        ->and($html)->toContain('--cor-superficie: #0f172a');
});

it('dashboard renderiza KPIs e cards na superfície da marca', function () {
    Aparencia::salvar(['cor_principal' => '#123456']);

    Livewire::actingAs($this->dono, 'web');

    Livewire::test(Dashboard::class)
        ->assertSet('erro', false)
        ->assertSee('Agendamentos')
        ->assertSee('Faturamento estimado')
        ->assertSee('Comparecimento')
        ->assertSeeHtml('ng-surface');           // cards usam a superfície temática
});

it('dashboard mostra estado vazio temático quando não há dados', function () {
    Livewire::actingAs($this->dono, 'web');

    Livewire::test(Dashboard::class)
        ->assertSee('Sem agendamentos no período')
        ->assertSeeHtml('ng-skeleton-portal');   // skeleton de loading presente no markup
});
