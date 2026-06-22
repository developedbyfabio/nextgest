<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueia rotas de um recurso quando ele está DESLIGADO para o tenant atual.
 *
 * Uso: ->middleware('recurso:whatsapp') (idem 'recurso:clube', 'recurso:gateway').
 * Recurso off → 404 (o recurso "nem existe" para quem não o contratou). Reaproveita
 * tenant_tem_recurso(), que já trata sem-contexto e chave inválida (→ false).
 *
 * Convenção da Fase 0a: todo recurso futuro nasce embrulhado na sua flag — a rota
 * recebe este middleware e os blocos Blade usam @recurso('{slug}').
 */
class VerificaRecurso
{
    public function handle(Request $request, Closure $next, string $recurso): Response
    {
        abort_unless(tenant_tem_recurso($recurso), 404);

        return $next($request);
    }
}
