<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Features\UserImpersonation;

/**
 * Impersonação de suporte: o super-admin entra no painel de um tenant como o
 * Dono, usando um token de uso único (stancl UserImpersonation). Roda em
 * contexto de tenant. A sessão marca "modo suporte" para sinalização e saída.
 */
class SuporteController extends Controller
{
    public function entrar(string $token): RedirectResponse
    {
        // Loga o usuário (web) no contexto do tenant e devolve o redirect.
        $resposta = UserImpersonation::makeResponse($token);

        session()->put('suporte_ativo', true);

        Log::info('Suporte: impersonação iniciada', [
            'tenant' => tenant('id'),
            'user_id' => Auth::guard('web')->id(),
            'ip' => request()->ip(),
        ]);

        return $resposta;
    }

    public function sair(Request $request): RedirectResponse
    {
        $tenantId = tenant('id');
        $userId = Auth::guard('web')->id();

        Auth::guard('web')->logout();
        $request->session()->forget('suporte_ativo');

        Log::info('Suporte: impersonação encerrada', [
            'tenant' => $tenantId,
            'user_id' => $userId,
        ]);

        return redirect()->route('admin.tenants');
    }
}
