<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Inicializa o tenancy nos endpoints GLOBAIS de arquivo do Livewire
 * (`upload-file` / `preview-file`).
 *
 * Esses endpoints não têm `{tenant}` no caminho e NÃO passam pelo persistent
 * middleware que reinicializa o tenancy no `/update`. Como ficam no grupo `web`
 * (com sessão), o `ThrottleRequests` chama `$request->user()` para montar a chave
 * de rate-limit; sem tenancy, o guard `web` tenta carregar o usuário do tenant
 * (que vive no banco do tenant) contra o banco CENTRAL → `nextgest_central.users`
 * não existe → 500 ("Falha no upload"). Ver
 * [[Bug - Upload 500 (usuario do tenant resolvido no banco central)]].
 *
 * Sinal do tenant (sessão compartilhada por caminho): `_tenant_sessao`, gravado
 * por EscoparAutenticacaoPorTenant quando o dono navega no painel. Fallback: o
 * primeiro segmento do Referer (a URL da página que disparou o upload). Uploads
 * centrais (onboarding do super-admin) ficam sem tenant — correto.
 */
class InicializarTenancyArquivosLivewire
{
    /** Slugs reservados que nunca são tenant. */
    private const RESERVADOS = ['admin', 'livewire', 'storage', 'up', 'webhooks'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! tenancy()->initialized) {
            $slug = $this->slugDaSessao($request) ?? $this->slugDoReferer($request);

            if ($slug !== null && ! in_array($slug, self::RESERVADOS, true)) {
                $tenant = Tenant::find($slug);

                if ($tenant !== null) {
                    tenancy()->initialize($tenant);
                }
            }
        }

        return $next($request);
    }

    private function slugDaSessao(Request $request): ?string
    {
        return $request->hasSession()
            ? $request->session()->get('_tenant_sessao')
            : null;
    }

    private function slugDoReferer(Request $request): ?string
    {
        $referer = $request->headers->get('referer');
        if (! $referer) {
            return null;
        }

        $path = ltrim((string) (parse_url($referer, PHP_URL_PATH) ?? ''), '/');
        if ($path === '') {
            return null;
        }

        return explode('/', $path)[0];
    }
}
