<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;
use Stancl\Tenancy\Resolvers\PathTenantResolver;

/**
 * Inicializa o tenancy por caminho APENAS quando há um parâmetro `tenant` na
 * rota com valor. Usado na rota de update do Livewire `/{tenant?}/livewire/update`,
 * compartilhada por:
 *  - páginas de tenant  → posta em /{tenant}/livewire/update → inicializa o tenant;
 *  - páginas centrais    → posta em /livewire/update → segue sem tenant.
 */
class InitializeTenancyByPathQuandoPresente extends InitializeTenancyByPath
{
    public function handle(Request $request, Closure $next)
    {
        $route = $request->route();
        $primeiroParametro = $route?->parameterNames()[0] ?? null;

        if ($primeiroParametro === PathTenantResolver::$tenantParameterName
            && $request->route(PathTenantResolver::$tenantParameterName) !== null) {
            return parent::handle($request, $next);
        }

        return $next($request);
    }
}
