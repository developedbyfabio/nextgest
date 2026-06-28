<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pagamentos;

use App\Http\Controllers\Controller;
use App\Services\Pagamentos\ConexaoGatewayMercadoPago;
use App\Services\Pagamentos\PagamentoGatewayException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Callback OAuth do Mercado Pago (Modelo A, D78). Rota CENTRAL fixa (sem tenant na
 * URL — o redirect_uri do MP é único). Valida o `state` contra a sessão (anti-CSRF),
 * troca o code pelo token e grava a conexão no cofre do tenant; depois volta para a
 * tela de Gateway do tenant. Nunca loga token.
 */
class MercadoPagoOAuthController extends Controller
{
    public function callback(Request $request, ConexaoGatewayMercadoPago $conexao): RedirectResponse
    {
        try {
            $tenantId = $conexao->concluir($request->query('code'), $request->query('state'));
        } catch (PagamentoGatewayException $e) {
            Log::warning('Gateway MP: callback OAuth falhou.', ['mensagem' => $e->getMessage()]);

            // Best-effort: descobre o tenant pelo state só para redirecionar de volta.
            $tenantId = $this->tenantDoState((string) $request->query('state', ''));
            session()->flash('gateway_msg', ['tipo' => 'erro', 'texto' => $e->getMessage()]);

            return $tenantId
                ? redirect()->route('painel.pagamentos', ['tenant' => $tenantId])
                : redirect()->to('/');
        }

        session()->flash('gateway_msg', ['tipo' => 'ok', 'texto' => 'Conta do Mercado Pago conectada.']);

        return redirect()->route('painel.pagamentos', ['tenant' => $tenantId]);
    }

    private function tenantDoState(string $state): ?string
    {
        $partes = explode('|', (string) base64_decode($state, true), 2);

        return $partes[0] !== '' ? ($partes[0] ?? null) : null;
    }
}
