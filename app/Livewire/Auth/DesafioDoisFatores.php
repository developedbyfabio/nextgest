<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Models\User;
use App\Support\DoisFatores as Totp;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Desafio de 2FA do login do painel (guard `web`). Estado "aguardando 2FA": a senha
 * já foi validada (PainelLogin), mas o usuário NÃO está autenticado — só o id pendente
 * vive na sessão (`2fa.pendente`). Aqui ele prova o segundo fator (código TOTP OU um
 * código de recuperação de uso único) e só então o login é efetivado.
 *
 * Segurança:
 * - Sem pendência na sessão → volta ao login (não há o que desafiar).
 * - Throttle próprio (~5 tentativas → bloqueio curto) contra força-bruta dos 6 dígitos.
 * - Código de recuperação é CONSUMIDO ao usar (uso único).
 * - Só efetiva o login (Auth::loginUsingId + regenerate) após o fator válido.
 */
#[Layout('components.layouts.auth')]
#[Title('Verificação em duas etapas')]
class DesafioDoisFatores extends Component
{
    /** Código TOTP (6 dígitos) OU um código de recuperação. */
    public string $codigo = '';

    public function mount()
    {
        if (! $this->idPendente()) {
            return redirect()->route('painel.login', ['tenant' => tenant('id')]);
        }
    }

    /** Id do usuário pendente de 2FA (ou null se não há pendência). */
    private function idPendente(): ?int
    {
        $id = session('2fa.pendente.id');

        return $id ? (int) $id : null;
    }

    private function throttleKey(): string
    {
        return Str::transliterate('2fa|'.$this->idPendente().'|'.request()->ip());
    }

    private function garantirNaoBloqueado(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $segundos = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'codigo' => "Muitas tentativas. Tente novamente em {$segundos} segundos.",
        ]);
    }

    public function verificar()
    {
        $this->validate(
            ['codigo' => ['required', 'string']],
            attributes: ['codigo' => 'código'],
        );

        $id = $this->idPendente();

        if (! $id) {
            return redirect()->route('painel.login', ['tenant' => tenant('id')]);
        }

        $this->garantirNaoBloqueado();

        /** @var User|null $user */
        $user = User::find($id);

        if (! $user || ! $user->temDoisFatores()) {
            // Pendência inválida (usuário sumiu ou 2FA foi desativado): recomeça.
            session()->forget('2fa.pendente');

            return redirect()->route('painel.login', ['tenant' => tenant('id')]);
        }

        $totpOk = Totp::verificarCodigo($user->two_factor_secret, $this->codigo);
        $recuperacaoOk = ! $totpOk && $user->consumirCodigoRecuperacao($this->codigo);

        if (! $totpOk && ! $recuperacaoOk) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'codigo' => 'Código inválido.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        $remember = (bool) session('2fa.pendente.remember', false);
        session()->forget('2fa.pendente');

        Auth::guard('web')->loginUsingId($id, $remember);
        session()->regenerate();

        // ForcarTrocaSenha (grupo auth:web) ainda trata deve_trocar_senha, se houver.
        return $this->redirectRoute('painel.dashboard', ['tenant' => tenant('id')], navigate: true);
    }

    public function render(): View
    {
        return view('livewire.auth.desafio-dois-fatores');
    }
}
