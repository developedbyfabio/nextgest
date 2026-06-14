<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Escopa a sessão ao tenant atual.
 *
 * Como a identificação é por caminho (mesmo domínio para todos os tenants), o
 * cookie de sessão padrão vazaria o login entre estabelecimentos. Aqui damos a
 * cada tenant um cookie de sessão com NOME próprio. O `path` fica em `/` (não no
 * segmento do tenant) de propósito: assim o cookie também é enviado à rota de
 * update do Livewire (`/{tenant}/livewire/update`) e a qualquer caminho, evitando
 * sessão vazia → token CSRF inválido (419). A isolação entre tenants vem do NOME
 * do cookie (cada tenant lê só o seu).
 *
 * Também define o parâmetro `tenant` como default das URLs (URL::defaults), para
 * que o Livewire gere a URL de update prefixada com o tenant atual.
 *
 * Deve rodar DEPOIS de InitializeTenancyByPath (precisa de tenant()) e ANTES de
 * StartSession (precisa estar configurado antes da sessão iniciar).
 */
class ScopeSessionToTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (tenancy()->initialized) {
            $tenantId = tenant('id');

            config([
                'session.cookie' => 'nextgest_tenant_'.$tenantId.'_session',
                'session.path' => '/',
            ]);

            // Para route('livewire.update') e demais URLs saírem com o tenant.
            URL::defaults(['tenant' => $tenantId]);
        }

        return $next($request);
    }
}
