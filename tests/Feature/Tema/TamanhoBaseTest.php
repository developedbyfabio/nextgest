<?php

declare(strict_types=1);

use App\Support\Aparencia;

/**
 * Bug 2: o "tamanho base" não aplicava porque o font-size ia só no <body>, mas os
 * utilitários do Tailwind são rem (relativos ao <html>). A correção emite o
 * font-size base no <html>, escalando a UI. Testes do efeito (estado renderizado).
 */
it('aplica o tamanho base no <html> do portal (escala rem)', function () {
    $t = criarTenant('lojatam');
    tenancy()->initialize($t);
    Aparencia::salvar(['tamanho_base' => '18px']);

    $html = $this->get('/lojatam')->assertOk()->content();

    expect($html)->toMatch('/<html[^>]*style="[^"]*font-size: 18px/');
});

it('aplica o tamanho base no <html> do painel', function () {
    $t = criarTenant('lojatam2');
    tenancy()->initialize($t);
    Aparencia::salvar(['tamanho_base' => '14px']);
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    $html = $this->get('/lojatam2/painel')->assertOk()->content();

    expect($html)->toMatch('/<html[^>]*style="[^"]*font-size: 14px/');
});

it('o tamanho base salvo continua emitido também no acento do body (cssVarsAcento)', function () {
    $t = criarTenant('lojatam3');
    tenancy()->initialize($t);
    Aparencia::salvar(['tamanho_base' => '17px']);

    expect(Aparencia::cssVarsAcento(Aparencia::doTenant()))->toContain('font-size: 17px');
});
