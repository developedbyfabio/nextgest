<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Troca de senha obrigatória no 1º login do PAINEL (guard `web`).
 *
 * Se o usuário do painel tem `deve_trocar_senha = true`, é redirecionado para a
 * tela de troca (`painel.senha`) e bloqueado no resto do painel — exceto a própria
 * rota de troca, o logout e o sair do suporte. Vale só no painel: registrado no
 * grupo `auth:web` das rotas de tenant; o portal do cliente (guard `cliente`) não
 * é afetado. Roda DEPOIS da tenancy/sessão (grupo `tenant`) e do `auth:web`.
 */
class ForcarTrocaSenha
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('web')->user();

        if ($user
            && $user->deve_trocar_senha
            && ! $request->routeIs('painel.senha', 'painel.logout', 'painel.suporte.sair')
        ) {
            return redirect()->route('painel.senha', ['tenant' => tenant('id')]);
        }

        return $next($request);
    }
}
