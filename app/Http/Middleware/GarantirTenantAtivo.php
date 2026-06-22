<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueia (404) o acesso a um estabelecimento INATIVO (`tenant.ativo === false`).
 *
 * Roda no grupo de tenant, DEPOIS do InitializeTenancyByPath (precisa do `Tenant`
 * carregado para ler `ativo`). Vale para painel (guard `web`) E portal (guard
 * `cliente`) — inclusive o LOGIN do tenant (não se loga em salão suspenso).
 *
 * NÃO afeta o /admin (central): as rotas centrais não passam por este grupo. A
 * reativação é feita pelo super-admin (/admin → "Ativar"). Inativar não apaga dado
 * (reversível) — só barra o acesso.
 */
class GarantirTenantAtivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if ($tenant instanceof Tenant && $tenant->ativo === false) {
            abort(404);
        }

        return $next($request);
    }
}
