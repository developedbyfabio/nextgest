<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LogoutController;
use App\Livewire\Auth\ClienteLogin;
use App\Livewire\Auth\ClienteRegistrar;
use App\Livewire\Auth\PainelLogin;
use App\Livewire\Painel\Dashboard as PainelDashboard;
use App\Livewire\Portal\Home as PortalHome;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas de TENANT (identificação por caminho)
|--------------------------------------------------------------------------
|
| Tudo sob o primeiro segmento da URL: nextgest.com.br/{tenant}/... — onde
| {tenant} é o slug/id do estabelecimento. O grupo "tenant" (bootstrap/app.php)
| inicializa o tenancy antes da sessão e escopa a sessão por tenant.
|
| {tenant} é restringido por regex para nunca casar com um slug reservado
| (config/nextgest.php) — assim /admin, /login (central), /api etc. ficam para o
| app central (routes/web.php).
|
*/

$reserved = implode('|', array_map(
    static fn (string $slug): string => preg_quote($slug, '/'),
    config('nextgest.reserved_slugs', [])
));

$tenantSlugPattern = '(?!('.$reserved.')$)[a-z0-9][a-z0-9-]*';

Route::middleware(['tenant'])
    ->prefix('{tenant}')
    ->where(['tenant' => $tenantSlugPattern])
    ->group(function () {
        /*
        | Portal do cliente (guard `cliente`) — mobile-first.
        */
        Route::get('/', PortalHome::class)->name('tenant.home');

        Route::middleware('guest:cliente')->group(function () {
            Route::get('login', ClienteLogin::class)->name('cliente.login');
            Route::get('registrar', ClienteRegistrar::class)->name('cliente.registrar');
        });

        Route::post('sair', [LogoutController::class, 'cliente'])
            ->middleware('auth:cliente')
            ->name('cliente.logout');

        /*
        | Painel da equipe (guard `web`).
        */
        Route::prefix('painel')->name('painel.')->group(function () {
            Route::get('login', PainelLogin::class)
                ->middleware('guest:web')
                ->name('login');

            Route::get('/', PainelDashboard::class)
                ->middleware('auth:web')
                ->name('dashboard');

            Route::post('sair', [LogoutController::class, 'painel'])
                ->middleware('auth:web')
                ->name('logout');
        });
    });
