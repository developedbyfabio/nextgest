<?php

declare(strict_types=1);

use App\Models\Cliente;

/*
| Separação portal (guard `cliente`) × painel (guard `web`).
|
| Estes testes COMPROVAM O ESTADO ATUAL (auditoria, não mudam comportamento):
|  1) o portal do cliente NÃO vaza nenhum link para /{slug}/painel (clareza/superfície);
|  2) a tela de login da equipe é ALCANÇÁVEL (200) — login alcançável não é falha (D40);
|  3) acesso anônimo ao painel REDIRECIONA limpo ao login da equipe (auth de fato protege).
|
| Premissa (D40): a defesa é auth+authz, não esconder a URL. Esconder seria obscuridade.
*/

it('portal do cliente (visitante) não contém link para o painel da equipe', function () {
    criarTenant('semvaz');

    $html = $this->get(route('tenant.home', ['tenant' => 'semvaz']))
        ->assertOk()
        ->getContent();

    expect($html)
        ->not->toContain('/semvaz/painel')
        ->not->toContain('painel.login');
});

it('a tela de login do cliente não contém link para o painel da equipe', function () {
    criarTenant('semvaz2');

    $html = $this->get(route('cliente.login', ['tenant' => 'semvaz2']))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('/semvaz2/painel');
});

it('portal do cliente (logado) não contém link para o painel da equipe', function () {
    tenancy()->initialize(criarTenant('semvaz3'));
    $cliente = Cliente::create(['nome' => 'Maria', 'telefone' => '1199', 'email' => 'maria@x.test', 'cpf' => '52998224725']);

    $html = $this->actingAs($cliente, 'cliente')
        ->get(route('tenant.home', ['tenant' => 'semvaz3']))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('/semvaz3/painel');
});

it('a tela de login da equipe é alcançável (200) — login alcançável não é falha', function () {
    criarTenant('alcancavel');

    $this->get(route('painel.login', ['tenant' => 'alcancavel']))
        ->assertOk()
        ->assertSee('Acesso da equipe');
});

it('acesso anônimo ao painel redireciona ao login da equipe (auth protege; sem dado vazado)', function () {
    criarTenant('protegido');

    $this->get(route('painel.dashboard', ['tenant' => 'protegido']))
        ->assertRedirect(route('painel.login', ['tenant' => 'protegido']));
});
