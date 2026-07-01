<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate de PERFIL COMPLETO do cliente (D94 CPF → D96 generaliza p/ telefone) — ponto
 * ÚNICO de exigência. Cliente logado com perfil INCOMPLETO (sem CPF OU sem telefone)
 * é levado a "Completar cadastro" antes de usar o portal.
 *
 * Motiva o telefone: o login com Google (D95) cria o cliente com telefone '' (a coluna
 * é NOT NULL e o Google não fornece), o que quebra o WhatsApp. Aqui garantimos que
 * NENHUM cliente circule sem telefone. Também pega o `telefone = ''` legado no próximo
 * acesso (backfill leve, sem operação destrutiva).
 *
 * Só age com o cliente autenticado; visitante passa direto. Configurável por
 * `nextgest.exigir_cpf_cliente` (mantido: liga/desliga o gate de perfil).
 *
 * Aliases: `perfil.completo` (canônico) e `cpf.cliente` (legado, D94) apontam para cá.
 */
class ExigirPerfilCompletoCliente
{
    public function handle(Request $request, Closure $next): Response
    {
        $cliente = Auth::guard('cliente')->user();

        if (
            $cliente
            && config('nextgest.exigir_cpf_cliente', true)
            && $cliente->perfilIncompleto()
            // Evita loop: a própria tela de completar (e o logout) não redirecionam.
            && ! $request->routeIs('cliente.completar-cadastro', 'cliente.logout')
        ) {
            return redirect()->route('cliente.completar-cadastro', ['tenant' => tenant('id')]);
        }

        return $next($request);
    }
}
