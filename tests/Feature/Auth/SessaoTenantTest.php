<?php

declare(strict_types=1);

use App\Models\Cliente;

/**
 * Sessão compartilhada (cookie único) com isolamento de login por tenant:
 * uma sessão autenticada no tenant A não permanece logada ao acessar o tenant B
 * (ver App\Http\Middleware\EscoparAutenticacaoPorTenant). É o que impede
 * vazamento de login entre estabelecimentos sem quebrar o endpoint do Livewire.
 */
it('não mantém o login de um tenant ao acessar outro', function () {
    criarTenant('lojaum');
    $b = criarTenant('lojadois');
    $cliente = $b->run(fn () => Cliente::create(['nome' => 'Maria', 'telefone' => '11', 'email' => 'maria@b.test']));

    // Sessão "vem" do tenant lojaum, mas acessamos o portal do lojadois.
    $resp = $this->actingAs($cliente, 'cliente')
        ->withSession(['_tenant_sessao' => 'lojaum'])
        ->get('/lojadois');

    $resp->assertOk()
        ->assertSee('Criar conta e agendar'); // conteúdo de visitante (deslogado)

    $this->assertGuest('cliente');
});
