<?php

declare(strict_types=1);

/**
 * Regressão do bug "o seletor Claro/Escuro/Sistema não alterna".
 *
 * Causa raiz: o seletor ligava o x-model a uma CÓPIA LOCAL
 * (`x-data="{ appearance: $flux.appearance }" x-model="appearance"`), que só lia o
 * valor inicial. O Flux aplica `.dark` por um `Alpine.effect` que observa
 * `$flux.appearance` (objeto reativo global); como a cópia local nunca o mutava, o
 * effect não disparava → seletor inerte (ficava preso no tema do sistema).
 *
 * Estes testes verificam o MECANISMO (não só "o seletor existe"):
 *  - o x-model liga DIRETO em `$flux.appearance` (e não na cópia local quebrada);
 *  - o `@fluxAppearance` está presente UMA vez e inicializa do localStorage/sistema;
 *  - o servidor NÃO força `.dark` no `<html>` (a decisão é do cliente).
 */

it('o seletor do portal liga o x-model DIRETO em $flux.appearance (não cópia local)', function () {
    criarTenant('lojatema');

    $html = $this->get('/lojatema')->assertOk()->content();

    // Liga no objeto reativo do Flux (dispara o effect que aplica .dark + persiste).
    expect($html)->toContain('x-model="$flux.appearance"');

    // NÃO pode reaparecer o padrão quebrado (cópia local inerte).
    expect($html)
        ->not->toContain('x-model="appearance"')
        ->not->toContain('appearance: $flux.appearance');
});

it('o painel liga o seletor de tema em $flux.appearance', function () {
    $t = criarTenant('lojatema2');
    tenancy()->initialize($t); // o Dono vive no banco do tenant
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    $html = $this->get('/lojatema2/painel')->assertOk()->content();

    expect($html)->toContain('x-model="$flux.appearance"')
        ->not->toContain('x-model="appearance"');
});

it('o @fluxAppearance aparece uma vez e inicializa do localStorage/sistema', function () {
    criarTenant('lojatema3');

    $html = $this->get('/lojatema3')->assertOk()->content();

    // Definição do script de aparência: exatamente uma vez (sem duplicar).
    expect(substr_count($html, 'applyAppearance (appearance)'))->toBe(1);

    // Inicializa a partir do valor salvo (ou 'system'): anti-flash e persistência.
    expect($html)->toContain("applyAppearance(window.localStorage.getItem('flux.appearance') || 'system')");
});

it('o servidor não força .dark no <html> (a alternância é do cliente)', function () {
    criarTenant('lojatema4');

    $html = $this->get('/lojatema4')->assertOk()->content();

    // <html> sai sem classe dark; quem aplica/remove é o Flux no cliente.
    expect($html)->toContain('<html lang="pt-BR">')
        ->not->toContain('<html lang="pt-BR" class="dark"')
        ->not->toContain('class="dark"');
});
