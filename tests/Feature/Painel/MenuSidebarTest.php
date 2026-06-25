<?php

declare(strict_types=1);

/**
 * Menu/sidebar: o grupo da ROTA ATUAL vem expandido; Início (sem grupo) → nenhum
 * aberto. (A decisão D47 "grupos fechados no load" segue, com a exceção do grupo
 * ativo.) Acordeão "só um aberto" é client-side (verificado no navegador).
 */
beforeEach(function () {
    $this->tenant = criarTenant('lojamenu');
    tenancy()->initialize($this->tenant);
    $this->dono = usuarioComPapel('Dono', ['email' => 'dono@menu.test']);
});

/** Nº de grupos do sidebar renderizados ABERTOS (ui-disclosure com `open`). */
function gruposAbertos(string $html): int
{
    return (int) preg_match_all('/<ui-disclosure[^>]*\bopen\b[^>]*data-flux-sidebar-group/', $html);
}

it('Início (sem grupo): nenhum grupo do menu vem aberto', function () {
    $html = $this->actingAs($this->dono, 'web')->get('/lojamenu/painel')->assertOk()->content();

    expect(gruposAbertos($html))->toBe(0);
});

it('rota de Operação: exatamente um grupo vem aberto (o da página)', function () {
    $html = $this->actingAs($this->dono, 'web')->get('/lojamenu/painel/avaliacoes')->assertOk()->content();

    expect(gruposAbertos($html))->toBe(1);
});

it('rota de Gestão: exatamente um grupo vem aberto', function () {
    $html = $this->actingAs($this->dono, 'web')->get('/lojamenu/painel/unidades')->assertOk()->content();

    expect(gruposAbertos($html))->toBe(1);
});

it('rota de Financeiro: exatamente um grupo vem aberto', function () {
    $html = $this->actingAs($this->dono, 'web')->get('/lojamenu/painel/financeiro')->assertOk()->content();

    expect(gruposAbertos($html))->toBe(1);
});
