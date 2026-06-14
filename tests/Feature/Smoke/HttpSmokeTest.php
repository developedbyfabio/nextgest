<?php

declare(strict_types=1);

use App\Models\Admin;
use Illuminate\Support\Facades\Route;

/**
 * Testes de fumaça em HTTP REAL (passando pelas rotas/middleware), que pegariam
 * o tipo de defeito que o Livewire::test (componente isolado) não pega — como o
 * endpoint /livewire/update respondendo 404.
 */
beforeEach(function () {
    criarTenant('lojaum');
});

it('todas as rotas GET respondem 200 ou 302 (nunca 404/500)', function () {
    $rotas = [
        // central
        '/', '/up', '/admin/login', '/admin', '/admin/estabelecimentos',
        '/admin/estabelecimentos/lojaum',
        // portal do tenant
        '/lojaum', '/lojaum/login', '/lojaum/registrar', '/lojaum/agendar',
        // painel do tenant
        '/lojaum/painel/login', '/lojaum/painel',
        '/lojaum/painel/unidades', '/lojaum/painel/servicos', '/lojaum/painel/equipe',
        '/lojaum/painel/bloqueios', '/lojaum/painel/papeis', '/lojaum/painel/agenda',
    ];

    foreach ($rotas as $rota) {
        $status = $this->get($rota)->getStatusCode();
        expect($status)->toBeIn([200, 302], "Rota {$rota} retornou {$status}");
    }
});

/**
 * Verifica que a URL de update emitida na página corresponde a uma rota POST
 * REGISTRADA (o bug do 404 era a página apontar para uma rota inexistente).
 */
function rotaDeUpdateExiste(string $html): bool
{
    if (! preg_match('#/livewire[a-z0-9-]*/update#', $html, $m)) {
        return false;
    }

    $uri = ltrim($m[0], '/');

    return collect(Route::getRoutes()->getRoutes())->contains(
        fn ($r) => $r->uri() === $uri && in_array('POST', $r->methods(), true)
    );
}

it('a página de tenant aponta para uma rota de update do Livewire registrada', function () {
    expect(rotaDeUpdateExiste($this->get('/lojaum/painel/login')->content()))->toBeTrue();
});

it('a página central aponta para uma rota de update do Livewire registrada', function () {
    expect(rotaDeUpdateExiste($this->get('/admin/login')->content()))->toBeTrue();
});

it('admin autenticado acessa /admin e a lista de estabelecimentos', function () {
    $admin = Admin::create([
        'name' => 'Super', 'email' => 'super@nextgest.test',
        'password' => 'senha-super-12345', 'ativo' => true,
    ]);

    $this->actingAs($admin, 'admin')->get('/admin')->assertOk();
    $this->actingAs($admin, 'admin')->get('/admin/estabelecimentos')->assertOk();
});
