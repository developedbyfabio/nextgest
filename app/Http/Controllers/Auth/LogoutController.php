<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Logout das três áreas. Encerra a sessão do guard, invalida a sessão e
 * regenera o token CSRF.
 */
class LogoutController extends Controller
{
    public function admin(Request $request): RedirectResponse
    {
        return $this->sair($request, 'admin', route('admin.login'));
    }

    public function painel(Request $request): RedirectResponse
    {
        return $this->sair($request, 'web', route('painel.login', ['tenant' => tenant('id')]));
    }

    public function cliente(Request $request): RedirectResponse
    {
        return $this->sair($request, 'cliente', route('tenant.home', ['tenant' => tenant('id')]));
    }

    protected function sair(Request $request, string $guard, string $destino): RedirectResponse
    {
        Auth::guard($guard)->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect($destino);
    }
}
