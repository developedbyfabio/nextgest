<?php

declare(strict_types=1);

use App\Http\Middleware\InicializarTenancyArquivosLivewire;
use Illuminate\Http\Request;

/**
 * Regressão do 500 do upload (3ª vez): o endpoint GLOBAL `livewire.upload-file`
 * roda no grupo `web` (com sessão) mas SEM tenancy. O `ThrottleRequests` chama
 * `$request->user()` para a chave de rate-limit; sem tenancy, o usuário do tenant
 * é procurado no banco CENTRAL (`nextgest_central.users`, que não existe) → 500
 * ("Falha no upload"). A correção inicializa o tenancy ANTES do throttle, via
 * InicializarTenancyArquivosLivewire + prioridade de middleware.
 *
 * Estes testes guardam o MECANISMO (a parte que o teste-verde-mas-quebrado não
 * pegava): a ORDEM real do middleware e o comportamento do middleware.
 */

it('no upload-file, a tenancy inicializa ANTES do ThrottleRequests (ordem real, com prioridade)', function () {
    $route = app('router')->getRoutes()->getByName('livewire.upload-file');
    expect($route)->not->toBeNull();

    // gatherRouteMiddleware já aplica a PRIORIDADE (ordem de execução real).
    $sorted = app('router')->gatherRouteMiddleware($route);

    $idxTenancy = null;
    $idxThrottle = null;
    foreach ($sorted as $i => $m) {
        $m = (string) $m;
        if (str_contains($m, 'InicializarTenancyArquivosLivewire')) {
            $idxTenancy = $i;
        }
        if (str_contains($m, 'ThrottleRequests') && $idxThrottle === null) {
            $idxThrottle = $i;
        }
    }

    expect($idxTenancy)->not->toBeNull('tenancy-init ausente no upload-file');
    expect($idxThrottle)->not->toBeNull('throttle ausente no upload-file');
    expect($idxTenancy)->toBeLessThan($idxThrottle); // tenancy ANTES do throttle (senão 500)
});

it('o middleware inicializa o tenancy a partir do _tenant_sessao', function () {
    criarTenant('lojaupsess');
    if (tenancy()->initialized) {
        tenancy()->end();
    }

    $req = Request::create('/livewire-x/upload-file', 'POST');
    $store = app('session')->driver();
    $store->put('_tenant_sessao', 'lojaupsess');
    $req->setLaravelSession($store);

    $passou = false;
    (new InicializarTenancyArquivosLivewire)->handle($req, function () use (&$passou) {
        $passou = true;

        return response('ok');
    });

    expect($passou)->toBeTrue();
    expect(tenancy()->initialized)->toBeTrue();
    expect(tenant('id'))->toBe('lojaupsess');

    tenancy()->end();
});

it('o middleware inicializa o tenancy pelo primeiro segmento do Referer (fallback)', function () {
    criarTenant('lojaupref');
    if (tenancy()->initialized) {
        tenancy()->end();
    }

    $req = Request::create('/livewire-x/upload-file', 'POST');
    $req->headers->set('referer', 'http://exemplo.test/lojaupref/painel/aparencia');
    $req->setLaravelSession(app('session')->driver()); // sem _tenant_sessao

    (new InicializarTenancyArquivosLivewire)->handle($req, fn () => response('ok'));

    expect(tenancy()->initialized)->toBeTrue();
    expect(tenant('id'))->toBe('lojaupref');

    tenancy()->end();
});

it('o middleware NÃO inicializa tenancy para caminho central (admin)', function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }

    $req = Request::create('/livewire-x/upload-file', 'POST');
    $req->headers->set('referer', 'http://exemplo.test/admin/estabelecimentos/novo');
    $req->setLaravelSession(app('session')->driver()); // sem _tenant_sessao

    (new InicializarTenancyArquivosLivewire)->handle($req, fn () => response('ok'));

    expect(tenancy()->initialized)->toBeFalse();
});
