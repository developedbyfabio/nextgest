<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate de CPF do cliente (D94) — ponto ÚNICO de exigência.
 *
 * Cliente logado (guard `cliente`) SEM CPF é levado a "Completar cadastro (CPF)"
 * antes de usar o portal. Fecha a brecha de contas duplicadas mesmo para clientes
 * ANTIGOS (que preenchem no próximo login) e é REUTILIZADO pelo fluxo do Google
 * (fatia seguinte), onde o novo usuário nunca traz CPF — sem duplicar a lógica.
 *
 * Só age com o cliente autenticado; visitante passa direto (rotas públicas seguem
 * públicas). Configurável por `nextgest.exigir_cpf_cliente` (default: exigir).
 */
class ExigirCpfCliente
{
    public function handle(Request $request, Closure $next): Response
    {
        $cliente = Auth::guard('cliente')->user();

        if (
            $cliente
            && config('nextgest.exigir_cpf_cliente', true)
            && blank($cliente->cpf)
            // Evita loop: a própria tela de completar (e o logout) não redirecionam.
            && ! $request->routeIs('cliente.completar-cadastro', 'cliente.logout')
        ) {
            return redirect()->route('cliente.completar-cadastro', ['tenant' => tenant('id')]);
        }

        return $next($request);
    }
}
