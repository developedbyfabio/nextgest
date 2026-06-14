<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas de TENANT (identificação por caminho)
|--------------------------------------------------------------------------
|
| Carregadas pelo TenancyServiceProvider. Todas vivem sob o primeiro segmento
| da URL: nextgest.com.br/{tenant}/... — onde {tenant} é o slug/id do
| estabelecimento. O grupo de middleware "tenant" (bootstrap/app.php) inicializa
| o tenancy (InitializeTenancyByPath) antes da sessão e escopa a sessão por
| tenant.
|
| O parâmetro {tenant} é restringido por regex para nunca casar com um slug
| reservado (config/nextgest.php) — assim /admin, /login, /api etc. ficam para
| o app central (routes/web.php).
|
*/

$reserved = implode('|', array_map(
    static fn (string $slug): string => preg_quote($slug, '/'),
    config('nextgest.reserved_slugs', [])
));

// slug válido: minúsculas/números/hífen, começando por alfanumérico, e que não
// seja um slug reservado.
$tenantSlugPattern = '(?!(' . $reserved . ')$)[a-z0-9][a-z0-9-]*';

Route::middleware(['tenant'])
    ->prefix('{tenant}')
    ->where(['tenant' => $tenantSlugPattern])
    ->group(function () {
        // Portal do estabelecimento (placeholder — implementado nos próximos blocos).
        Route::get('/', function () {
            return 'Tenant ativo: ' . tenant('nome') . ' (id/slug: ' . tenant('id') . ')';
        })->name('tenant.home');
    });
