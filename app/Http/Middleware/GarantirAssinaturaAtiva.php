<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Assinatura;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Suspensão por pagamento (D60) — bloqueia o PAINEL (guard `web`) quando a assinatura
 * SaaS está `suspensa` ou `cancelada`, redirecionando para a tela de suspensão amigável.
 *
 * Enforcement AO VIVO via Assinatura::situacaoAcesso() (sem cron, sem ler status gravado):
 * marcar a fatura paga (tela de Faturamento) libera no próximo request. NÃO bloqueia
 * `atrasada` (carência) — esse caso só mostra o banner no painel do dono.
 *
 * Aplicado SÓ no grupo do painel (em routes/tenant.php), portanto roda DEPOIS de a
 * tenancy inicializar e do GarantirTenantAtivo (lição 4). NUNCA no portal/cliente nem no
 * /admin. Distinto do `ativo=false` (404 do GarantirTenantAtivo) — aqui é tela própria.
 *
 * Auto-isento na PRÓPRIA tela de suspensão (evita loop) e no logout (deixa o dono sair).
 */
class GarantirAssinaturaAtiva
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('painel.assinatura.suspensa') || $request->routeIs('painel.logout')) {
            return $next($request);
        }

        $tenant = tenant();

        if (! $tenant instanceof Tenant) {
            return $next($request); // defesa: sem tenant resolvido, não bloqueia
        }

        $assinatura = $tenant->assinatura; // hasOne central; null = tenant não provisionado

        $bloqueado = $assinatura !== null && in_array(
            $assinatura->situacaoAcesso(),
            [Assinatura::SUSPENSA, Assinatura::CANCELADA],
            true,
        );

        if ($bloqueado) {
            return redirect()->route('painel.assinatura.suspensa', ['tenant' => $tenant->getKey()]);
        }

        return $next($request);
    }
}
