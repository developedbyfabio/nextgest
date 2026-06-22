<?php

declare(strict_types=1);

namespace App\Livewire\Auth\Concerns;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Lógica comum de login por guard para os componentes de autenticação.
 *
 * Segurança:
 * - throttle de 5 tentativas/min por (email + IP);
 * - mensagens genéricas (não revelam se o e-mail existe);
 * - session()->regenerate() após autenticar (evita fixation).
 */
trait AutenticaPorGuard
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    /**
     * Restrições extras aplicadas ao attempt (além de e-mail/senha).
     * Ex.: ['ativo' => true] para barrar usuários inativos.
     */
    protected function credenciaisExtras(): array
    {
        return [];
    }

    /**
     * Chave de throttle por e-mail + IP.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }

    protected function garantirNaoBloqueado(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $segundos = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "Muitas tentativas. Tente novamente em {$segundos} segundos.",
        ]);
    }

    /**
     * Indica se o usuário recém-validado por senha ainda precisa de um SEGUNDO fator
     * (2FA). Padrão: não. Sobrescrito só pelo login do painel (web). Para admin/cliente
     * continua false → o caminho abaixo é idêntico ao de sempre.
     */
    protected function precisaSegundoFator(Authenticatable $user): bool
    {
        return false;
    }

    /**
     * Valida, aplica throttle e tenta autenticar no guard informado.
     *
     * Retorno:
     * - null  → login efetivado (sessão regenerada). Caminho SÓ-SENHA, byte a byte como
     *   sempre: `attempt()` + `regenerate()`. É o que admin/cliente e usuários sem 2FA usam.
     * - User  → senha OK, mas o usuário exige 2FA: o login é DESFEITO (estado pendente) e
     *   o usuário é devolvido para o chamador montar o desafio. Só ocorre no painel quando
     *   `precisaSegundoFator()` é verdadeiro — não afeta o caminho sem 2FA.
     */
    protected function autenticar(string $guard): ?Authenticatable
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ], attributes: [
            'email' => 'e-mail',
            'password' => 'senha',
        ]);

        $this->garantirNaoBloqueado();

        $credenciais = array_merge(
            ['email' => $this->email, 'password' => $this->password],
            $this->credenciaisExtras(),
        );

        $guardInstance = Auth::guard($guard);

        if (! $guardInstance->attempt($credenciais, $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            // Mensagem genérica: não distingue "e-mail não existe" de "senha errada".
            throw ValidationException::withMessages([
                'email' => 'As credenciais informadas estão incorretas.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        $user = $guardInstance->user();

        if ($user !== null && $this->precisaSegundoFator($user)) {
            // Desfaz o login: até passar o 2FA, NÃO há acesso a nada.
            $guardInstance->logout();

            return $user;
        }

        session()->regenerate();

        return null;
    }
}
