<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Escopa a sessão ao tenant atual.
 *
 * Como a identificação é por caminho (mesmo domínio para todos os tenants),
 * o cookie de sessão padrão vazaria o login entre estabelecimentos. Aqui damos
 * a cada tenant um cookie de sessão próprio e restringimos o path do cookie ao
 * segmento do tenant, de modo que o navegador só o envie dentro daquele tenant.
 *
 * Deve rodar DEPOIS de InitializeTenancyByPath (precisa de tenant()) e ANTES de
 * StartSession (precisa estar configurado antes da sessão iniciar). Ver a ordem
 * no grupo de middleware "tenant" em bootstrap/app.php.
 */
class ScopeSessionToTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (tenancy()->initialized) {
            $tenantId = tenant('id');

            config([
                'session.cookie' => 'nextgest_tenant_' . $tenantId . '_session',
                'session.path' => '/' . $tenantId,
            ]);
        }

        return $next($request);
    }
}
