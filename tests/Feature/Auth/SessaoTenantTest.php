<?php

declare(strict_types=1);

/**
 * Regressão do 419 no login de tenant: a sessão é escopada por NOME de cookie,
 * com path "/" (não no segmento do tenant). Path "/" garante que o cookie chega
 * à rota de update do Livewire (/{tenant}/livewire/update) — senão a sessão fica
 * vazia e o token CSRF não bate (419). A isolação entre tenants vem do nome.
 */
it('usa cookie de sessão por tenant com path /', function () {
    criarTenant('lojaum');

    $resp = $this->get('/lojaum/painel/login')->assertOk();

    $cookie = collect($resp->headers->getCookies())
        ->first(fn ($c) => str_contains($c->getName(), 'lojaum'));

    expect($cookie)->not->toBeNull()
        ->and($cookie->getName())->toBe('nextgest_tenant_lojaum_session')
        ->and($cookie->getPath())->toBe('/');
});
