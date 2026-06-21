<?php

declare(strict_types=1);

use App\Support\Aparencia;

/**
 * Etapa D — modo claro / escuro / sistema (Flux) no portal e no painel.
 * Modelo: marca = ACENTO (+ logo + tipografia), constante nos dois modos;
 * superfícies = tokens de claro/escuro controlados pelo modo. O `.dark` é aplicado
 * no CLIENTE (localStorage/sistema) pelo @fluxAppearance — não vem forçado do servidor.
 */
it('o portal respeita o modo (fluxAppearance) e oferece o seletor de tema', function () {
    criarTenant('lojamodo');

    $html = $this->get('/lojamodo')->assertOk()->content();

    expect($html)->toContain('Flux.applyAppearance')   // @fluxAppearance ativo
        ->and($html)->toContain('<html lang="pt-BR">') // sem .dark forçado no servidor
        ->and($html)->toContain('Claro')
        ->and($html)->toContain('Escuro')
        ->and($html)->toContain('Sistema');
});

it('o portal emite a marca como ACENTO e não pinta a superfície com a marca', function () {
    $t = criarTenant('lojamodo2');
    tenancy()->initialize($t);
    Aparencia::salvar(['cor_principal' => '#b45309']); // âmbar (marca)

    $html = $this->get('/lojamodo2')->assertOk()->content();

    expect($html)->toContain('--color-accent: #b45309')
        ->and($html)->toContain('--cor-principal: #b45309')
        ->and($html)->not->toContain('--cor-fundo: #')        // superfície = tokens claro/escuro
        ->and($html)->not->toContain('--cor-superficie: #');
});

it('o acento da marca é emitido server-side (igual para qualquer modo)', function () {
    $t = criarTenant('lojamodo3');
    tenancy()->initialize($t);
    Aparencia::salvar(['cor_principal' => '#0ea5e9']);

    // O modo (claro/escuro) só troca superfícies no cliente; o acento é o mesmo.
    $html = $this->get('/lojamodo3')->assertOk()->content();
    expect(substr_count($html, '--color-accent: #0ea5e9'))->toBeGreaterThan(0);
});

it('texto sobre o acento contrasta — acento CLARO usa texto escuro', function () {
    $t = criarTenant('lojacontraste');
    tenancy()->initialize($t);
    Aparencia::salvar(['cor_principal' => '#fde047']); // amarelo claro

    $html = $this->get('/lojacontraste')->assertOk()->content();
    expect($html)->toContain('--cor-sobre-principal: #18181b'); // texto escuro, legível
});

it('texto sobre o acento contrasta — acento ESCURO usa texto branco', function () {
    $t = criarTenant('lojacontraste2');
    tenancy()->initialize($t);
    Aparencia::salvar(['cor_principal' => '#111827']); // quase preto

    $html = $this->get('/lojacontraste2')->assertOk()->content();
    expect($html)->toContain('--cor-sobre-principal: #ffffff'); // texto branco, legível
});
