<?php

declare(strict_types=1);

use App\Models\Cliente;
use App\Support\Aparencia;

/**
 * Etapa D — a MARCA do tenant entra como ACENTO (+ logo + tipografia) no portal,
 * nas telas de auth e no painel; as SUPERFÍCIES seguem o modo claro/escuro/sistema
 * (Flux), não a cor da marca. O app central (/admin, landing) segue na marca
 * Nextgest, sem tema de tenant.
 */
beforeEach(function () {
    $this->tenant = criarTenant('lojaum');
    tenancy()->initialize($this->tenant);
    Aparencia::salvar(['cor_principal' => '#123456']); // marca escura → frente branca
    tenancy()->end();
});

it('telas de auth do tenant refletem o acento da marca', function () {
    foreach (['/lojaum/login', '/lojaum/registrar', '/lojaum/painel/login'] as $rota) {
        $html = $this->get($rota)->assertOk()->content();
        expect($html)->toContain('--color-accent: #123456');
        expect($html)->toContain('--cor-principal: #123456');
        expect($html)->toContain('--cor-sobre-principal'); // contraste do texto (6B)
    }
});

it('o painel aplica a marca como acento; superfícies pelo modo claro/escuro', function () {
    tenancy()->initialize($this->tenant);
    $dono = usuarioComPapel('Dono');

    $html = $this->actingAs($dono, 'web')->get('/lojaum/painel')->assertOk()->content();

    expect($html)->toContain('--color-accent: #123456');
    expect($html)->toContain('--cor-principal: #123456');
    expect($html)->toContain('--cor-sobre-principal');
    // Etapa D: a marca NÃO pinta o fundo; superfícies vêm dos tokens claro/escuro.
    expect($html)->not->toContain('--cor-fundo: #');
    expect($html)->toContain('Flux.applyAppearance'); // respeita o modo
});

it('o app central (admin) NÃO recebe tema de tenant — segue Nextgest', function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }

    $html = $this->get('/admin/login')->assertOk()->content();

    expect($html)->toContain('Nextgest');
    expect($html)->not->toContain('--cor-principal');
});

it('a landing central segue na marca Nextgest, sem tema de tenant', function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }

    $html = $this->get('/')->assertOk()->content();

    expect($html)->not->toContain('--cor-principal');
});

it('a home logada do portal renderiza as seções esperadas sob o tema', function () {
    tenancy()->initialize($this->tenant);
    $cliente = Cliente::create(['nome' => 'Ana Cliente', 'telefone' => '11999', 'email' => 'ana@l.test', 'cpf' => '52998224725']);

    $html = $this->actingAs($cliente, 'cliente')->get('/lojaum')->assertOk()->content();

    expect($html)->toContain('Ana');                       // saudação (primeiro nome)
    expect($html)->toContain('Próximos agendamentos');
    expect($html)->toContain('Nenhum agendamento futuro');  // estado vazio bonito
    expect($html)->toContain('Meus dados');
    expect($html)->toContain('ana@l.test');                 // dados do cliente
    expect($html)->toContain('Clube de assinatura');
    expect($html)->toContain('--cor-principal');            // portal sob o tema completo
});
