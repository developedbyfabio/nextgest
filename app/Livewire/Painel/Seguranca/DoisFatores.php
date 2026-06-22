<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Seguranca;

use App\Models\User;
use App\Support\DoisFatores as Totp;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Setup do 2FA (TOTP) — COMPONENTE ÚNICO reusado pelo PERFIL (modal embutido no
 * layout do painel) e pelo passo de ONBOARDING do Dono (rota em layout `auth`).
 * Fonte de verdade única do fluxo; a cripto vem de App\Support\DoisFatores.
 *
 * Segurança:
 * - Só o Dono (permissão `gerenciar_2fa_proprio`, D39) — checado no mount E em cada ação.
 * - O segredo NUNCA é propriedade pública (não vai ao snapshot do Livewire). É lido do
 *   banco (decifrado) só no render(), e só enquanto em configuração — quando o QR
 *   precisa, por definição, ser exibido para o Dono escanear. Idem códigos de recuperação.
 * - NUNCA ativa sem um código de confirmação válido (prova que o app sincronizou): só
 *   então grava `two_factor_confirmed_at` e gera os códigos de recuperação.
 * - Desativar / regenerar / reexibir exigem reconfirmar a SENHA (revelação inline).
 *
 * Estados (derivados do model + flags):
 * - inativo:        sem confirmação e fora de configuração → botão "Ativar".
 * - emConfiguracao: segredo gerado, aguardando o código (QR + chave manual + input).
 * - ativo:          `two_factor_confirmed_at` preenchido → gestão (recuperação/desativar).
 */
#[Layout('components.layouts.auth')]
class DoisFatores extends Component
{
    /** Modo onboarding (1º login do Dono): permite PULAR o passo opcional. */
    #[Locked]
    public bool $permitePular = false;

    /** Em meio ao setup (segredo gerado, aguardando o código de confirmação). */
    public bool $emConfiguracao = false;

    /** Exibindo os códigos de recuperação (uma vez, após ativar/regenerar/reexibir). */
    public bool $mostrarRecuperacao = false;

    /** Qual ação sensível está pedindo senha inline: '', 'ver', 'regenerar', 'desativar'. */
    public string $acaoSenha = '';

    /** Código TOTP digitado na confirmação. */
    public string $codigo = '';

    /** Senha para reconfirmar operações sensíveis. */
    public string $senha = '';

    public function mount(bool $permitePular = false): void
    {
        $this->garantirDono();

        // Modo onboarding: pela flag explícita (teste) OU pela rota do passo de 1º login.
        $this->permitePular = $permitePular || request()->routeIs('painel.2fa.onboarding');
    }

    /** Gate só-Dono (defesa em profundidade: mount + toda ação). */
    private function garantirDono(): void
    {
        abort_unless(auth('web')->user()?->can('gerenciar_2fa_proprio') ?? false, 403);
    }

    private function user(): User
    {
        /** @var User $u */
        $u = auth('web')->user();

        return $u;
    }

    /** Inicia o setup: gera um segredo novo e o grava (cifrado), ainda NÃO confirmado. */
    public function ativar(): void
    {
        $this->garantirDono();

        $user = $this->user();

        if ($user->temDoisFatores()) {
            return; // já ativo: nada a fazer
        }

        $user->two_factor_secret = Totp::gerarSegredo();
        $user->two_factor_confirmed_at = null;
        $user->two_factor_recovery_codes = null;
        $user->save();

        $this->reset('codigo', 'acaoSenha', 'senha');
        $this->resetValidation();
        $this->emConfiguracao = true;
        $this->mostrarRecuperacao = false;
    }

    /** Cancela um setup em andamento (limpa o segredo não confirmado). */
    public function cancelar(): void
    {
        $this->garantirDono();

        $user = $this->user();

        if (! $user->temDoisFatores()) {
            $user->two_factor_secret = null;
            $user->two_factor_recovery_codes = null;
            $user->save();
        }

        $this->reset('codigo', 'emConfiguracao');
        $this->resetValidation();
    }

    /**
     * Confirma o código do app. NUNCA ativa sem um código válido. Em sucesso: grava
     * `two_factor_confirmed_at`, gera os códigos de recuperação e os exibe uma vez.
     */
    public function confirmar(): void
    {
        $this->garantirDono();

        $this->validate(
            ['codigo' => ['required', 'string']],
            attributes: ['codigo' => 'código'],
        );

        $user = $this->user();

        if (is_null($user->two_factor_secret) || $user->temDoisFatores()) {
            // Sem setup em andamento (ou já ativo): nada a confirmar.
            $this->emConfiguracao = false;

            return;
        }

        if (! Totp::verificarCodigo($user->two_factor_secret, $this->codigo)) {
            throw ValidationException::withMessages([
                'codigo' => 'Código inválido. Confira o relógio do app e tente o código atual.',
            ]);
        }

        $user->two_factor_recovery_codes = Totp::gerarCodigosRecuperacao();
        $user->two_factor_confirmed_at = now();
        $user->save();

        $this->reset('codigo');
        $this->emConfiguracao = false;
        $this->mostrarRecuperacao = true;

        Flux::toast('Autenticação em duas etapas ativada.', variant: 'success');
    }

    /** Abre a confirmação de senha inline para uma ação sensível. */
    public function pedirSenha(string $acao): void
    {
        $this->garantirDono();

        if (! in_array($acao, ['ver', 'regenerar', 'desativar'], true)) {
            return;
        }

        $this->reset('senha');
        $this->resetValidation('senha');
        $this->acaoSenha = $acao;
    }

    /** Fecha a confirmação de senha inline. */
    public function cancelarSenha(): void
    {
        $this->reset('senha', 'acaoSenha');
        $this->resetValidation('senha');
    }

    /** Reexibe os códigos atuais (exige senha). */
    public function reexibir(): void
    {
        $this->garantirDono();
        $this->exigirSenha();

        if ($this->user()->temDoisFatores()) {
            $this->mostrarRecuperacao = true;
        }

        $this->reset('senha', 'acaoSenha');
    }

    /** Gera um novo conjunto de códigos (invalida os antigos). Exige senha. */
    public function regenerar(): void
    {
        $this->garantirDono();
        $this->exigirSenha();

        $user = $this->user();

        if ($user->temDoisFatores()) {
            $user->two_factor_recovery_codes = Totp::gerarCodigosRecuperacao();
            $user->save();
            $this->mostrarRecuperacao = true;
            Flux::toast('Novos códigos de recuperação gerados.', variant: 'success');
        }

        $this->reset('senha', 'acaoSenha');
    }

    /** Desativa o 2FA (exige senha): limpa segredo + códigos + confirmação. */
    public function desativar(): void
    {
        $this->garantirDono();
        $this->exigirSenha();

        $user = $this->user();
        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        $this->reset('senha', 'codigo', 'emConfiguracao', 'mostrarRecuperacao', 'acaoSenha');
        Flux::toast('Autenticação em duas etapas desativada.', variant: 'success');
    }

    /** Valida a senha atual; lança erro de validação em 'senha' se incorreta. */
    private function exigirSenha(): void
    {
        $this->validate(
            ['senha' => ['required', 'string']],
            attributes: ['senha' => 'senha'],
        );

        if (! Hash::check($this->senha, $this->user()->password)) {
            throw ValidationException::withMessages(['senha' => 'Senha incorreta.']);
        }
    }

    /** Oculta o painel de códigos de recuperação (botão "já guardei"). */
    public function ocultarRecuperacao(): void
    {
        $this->mostrarRecuperacao = false;
    }

    /** Onboarding: concluir o passo e ir ao painel. */
    public function concluir()
    {
        return redirect()->route('painel.dashboard', ['tenant' => tenant('id')]);
    }

    /** Onboarding: pular o passo opcional e ir ao painel. */
    public function pular()
    {
        abort_unless($this->permitePular, 404);

        return redirect()->route('painel.dashboard', ['tenant' => tenant('id')]);
    }

    public function render(): View
    {
        $user = $this->user();
        $ativo = $user->temDoisFatores();

        // Dados sensíveis (segredo/QR/códigos) são LOCAIS da view — nunca propriedade
        // pública (logo, fora do snapshot do Livewire). Só montados quando precisam ser
        // exibidos: QR durante a configuração; códigos quando mostrarRecuperacao.
        $qrSvg = null;
        $chaveManual = null;

        if ($this->emConfiguracao && ! $ativo && ! is_null($user->two_factor_secret)) {
            $segredo = $user->two_factor_secret;
            $emissor = 'Nextgest ('.(tenant('slug') ?? tenant('id')).')';
            $url = Totp::otpauthUrl($user->email, $segredo, $emissor);
            $qrSvg = Totp::qrSvg($url);
            $chaveManual = $segredo;
        }

        $codigosRecuperacao = ($this->mostrarRecuperacao && $ativo)
            ? ($user->two_factor_recovery_codes ?? [])
            : [];

        return view('livewire.painel.seguranca.dois-fatores', [
            'ativo' => $ativo,
            'qrSvg' => $qrSvg,
            'chaveManual' => $chaveManual,
            'codigosRecuperacao' => $codigosRecuperacao,
        ]);
    }
}
