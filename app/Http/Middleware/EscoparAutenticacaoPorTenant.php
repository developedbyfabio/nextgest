<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Isola o login entre tenants numa sessão compartilhada.
 *
 * A sessão usa um cookie único (mesmo domínio, identificação por caminho). Como
 * os guards `web`/`cliente` carregam o usuário do banco do tenant ATUAL, uma
 * sessão autenticada no tenant A não pode valer no tenant B (ids podem colidir).
 * Aqui, ao detectar que a sessão pertence a outro tenant, encerramos os logins
 * de tenant — assim cada estabelecimento exige login próprio.
 *
 * Roda nas rotas de tenant, depois de StartSession e da inicialização do tenancy.
 * Não afeta o guard `admin` (central).
 */
class EscoparAutenticacaoPorTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (tenancy()->initialized) {
            $atual = tenant('id');
            $marcado = $request->session()->get('_tenant_sessao');

            // Só encerra se a sessão pertencer a OUTRO tenant. Marcador vazio
            // (sessão nova) apenas adota o tenant atual, sem deslogar.
            if ($marcado !== null && $marcado !== $atual) {
                Auth::guard('web')->logout();
                Auth::guard('cliente')->logout();
            }

            $request->session()->put('_tenant_sessao', $atual);
        }

        return $next($request);
    }
}
