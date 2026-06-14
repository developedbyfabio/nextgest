<?php

declare(strict_types=1);

namespace App\Livewire\Auth\Concerns;

use Illuminate\Auth\Events\Lockout;
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
     * Valida, aplica throttle e tenta autenticar no guard informado.
     * Em caso de sucesso, regenera a sessão.
     */
    protected function autenticar(string $guard): void
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

        if (! Auth::guard($guard)->attempt($credenciais, $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            // Mensagem genérica: não distingue "e-mail não existe" de "senha errada".
            throw ValidationException::withMessages([
                'email' => 'As credenciais informadas estão incorretas.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        session()->regenerate();
    }
}
