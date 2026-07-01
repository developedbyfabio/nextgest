<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * Login/cadastro do cliente via Google (D95) — CENTRAL (não tenant-scoped): o Google
 * não aceita wildcard de path, então há UMA redirect URI central. O tenant viaja no
 * round-trip pela SESSÃO (domínio central, sessão compartilhada entre os paths):
 * `/{tenant}/login` chama `redirect?tenant={slug}` → guardamos o slug → callback lê,
 * valida, inicializa o tenancy e faz find-or-create do cliente naquele banco.
 *
 * Reutiliza o gate de CPF (D94): o novo usuário do Google entra sem CPF e o middleware
 * `cpf.cliente` o leva a "Completar cadastro" antes de liberar o portal. Segurança:
 * nunca logamos tokens; guardamos só google_id, nome e e-mail.
 */
class GoogleController extends Controller
{
    private const CHAVE_SESSAO = 'google_oauth_tenant';

    /** Início do fluxo: guarda o slug do tenant na sessão e vai ao Google. */
    public function redirect(Request $request): RedirectResponse
    {
        $slug = (string) $request->query('tenant', '');

        // Sem tenant conhecido não há para onde voltar → à landing.
        if ($slug === '' || Tenant::find($slug) === null) {
            return redirect()->route('landing')->with('erro', 'Estabelecimento inválido.');
        }

        $request->session()->put(self::CHAVE_SESSAO, $slug);

        // NÃO mexemos no `state` do Socialite — a proteção CSRF dele é mantida.
        return Socialite::driver('google')->redirect();
    }

    /** Retorno do Google: valida o tenant, autentica o cliente e volta ao portal. */
    public function callback(Request $request): RedirectResponse
    {
        $slug = (string) $request->session()->pull(self::CHAVE_SESSAO, '');
        $tenant = $slug !== '' ? Tenant::find($slug) : null;

        // Slug ausente/desconhecido → aborta o fluxo (não confiar em sessão vazia).
        if ($tenant === null) {
            return redirect()->route('landing')->with('erro', 'Não foi possível concluir o login com o Google.');
        }

        // O tenancy precisa estar ativo ANTES de qualquer query/gravação do cliente.
        tenancy()->initialize($tenant);

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            // Usuário cancelou / erro de OAuth. Sem logar token; mensagem amigável.
            return redirect()->route('cliente.login', ['tenant' => $slug])
                ->with('erro', 'Login com o Google não concluído. Tente novamente.');
        }

        $email = $googleUser->getEmail();
        $emailVerificado = ($googleUser->user['email_verified'] ?? true) !== false;

        if (! $email || ! $emailVerificado) {
            return redirect()->route('cliente.login', ['tenant' => $slug])
                ->with('erro', 'Sua conta Google não tem um e-mail verificado.');
        }

        $cliente = $this->encontrarOuCriar($googleUser->getId(), $email, $googleUser->getName());

        Auth::guard('cliente')->login($cliente, remember: true);
        $request->session()->regenerate();

        // Home dispara o gate de CPF (D94): sem CPF → "Completar cadastro".
        return redirect()->route('tenant.home', ['tenant' => $slug]);
    }

    /**
     * Find-or-create no banco do tenant: por google_id; senão vincula por e-mail
     * (o Google entrega e-mail verificado); senão cria. Nunca duplica a conta.
     */
    private function encontrarOuCriar(string $googleId, string $email, ?string $nome): Cliente
    {
        $cliente = Cliente::where('google_id', $googleId)->first();

        if ($cliente === null) {
            $cliente = Cliente::where('email', $email)->first();

            if ($cliente !== null) {
                // Conta já existe (cadastro por e-mail) → vincula o Google, sem duplicar.
                $cliente->google_id = $googleId;
                $cliente->save();
            } else {
                $cliente = Cliente::create([
                    'nome' => $nome ?: strtok($email, '@'),
                    'email' => $email,
                    'google_id' => $googleId,
                    // Google não fornece telefone; a coluna é NOT NULL. Fica vazio
                    // (a UI mostra "—") e o cliente pode preencher depois. Sem senha
                    // (login é via Google); CPF é exigido pelo gate (D94).
                    'telefone' => '',
                ]);
            }
        }

        return $cliente;
    }
}
