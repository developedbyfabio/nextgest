<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\Recurso;
use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Servico;
use App\Models\Tenant;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Detalhe de um estabelecimento (painel central). Mostra um resumo de ALTO NÍVEL
 * — os dados operacionais (clientes, equipe, agenda) são privados de cada tenant;
 * aqui só vemos contagens, donos e status. Para inspecionar de fato, use "Entrar
 * no painel" (impersonação de suporte).
 */
#[Layout('components.layouts.admin')]
#[Title('Estabelecimento')]
class TenantDetalhe extends Component
{
    public Tenant $tenant;

    /** Estado dos toggles de recurso: [slug => bool]. Pré-carregado do central no mount. */
    public array $recursos = [];

    /** Plano selecionado (slug do catálogo). '' = não definido (tenant antigo/personalizado). */
    public string $plano = '';

    public function mount(string $tenantId): void
    {
        abort_unless(auth('admin')->check(), 403);

        $this->tenant = Tenant::findOrFail($tenantId);

        // Pré-carrega os switches a partir do estado atual (recursos ligados no central).
        $ativos = $this->tenant->recursosAtivos();
        foreach (Recurso::cases() as $recurso) {
            $this->recursos[$recurso->value] = in_array($recurso->value, $ativos, true);
        }

        // Plano atual NORMALIZADO ('' se não definido) — NÃO muta nada (D55).
        $this->plano = (string) ($this->tenant->planoAtual() ?? '');
    }

    /**
     * Troca o plano do estabelecimento: reaplica os recursos para o padrão do plano
     * escolhido (D55). Recarrega o Tenant COMPLETO antes de salvar (regra de ouro do
     * `data`: preserva `segmento`/outros). Rebaixar só esconde o acesso — não apaga dados.
     */
    public function trocarPlano(): void
    {
        abort_unless(auth('admin')->check(), 403);

        $this->validate(
            ['plano' => ['required', 'string', Rule::in(array_keys(config('planos', [])))]],
            ['plano.required' => 'Selecione um plano.', 'plano.in' => 'Selecione um plano.'],
        );

        $tenant = Tenant::findOrFail($this->tenant->getKey());
        $tenant->aplicarPlano($this->plano);
        $this->tenant = $tenant;

        // Re-sincroniza os toggles de "ajuste fino" com os recursos do plano aplicado.
        $ativos = $tenant->recursosAtivos();
        foreach (Recurso::cases() as $recurso) {
            $this->recursos[$recurso->value] = in_array($recurso->value, $ativos, true);
        }

        Flux::toast('Plano aplicado. Recursos redefinidos para o padrão do plano.', variant: 'success');
    }

    /**
     * Persiste os recursos ligados no registro CENTRAL do estabelecimento.
     *
     * CRÍTICO: grava só o atributo virtual `recursos` (que mora no JSON `data` junto
     * do `segmento`). Recarrega o Tenant COMPLETO antes de salvar e nunca reatribui o
     * `data` inteiro — assim o `segmento` (e qualquer outro metadado) sobrevive.
     */
    public function salvarRecursos(): void
    {
        abort_unless(auth('admin')->check(), 403);

        $ativos = [];
        foreach (Recurso::cases() as $recurso) {
            if (! empty($this->recursos[$recurso->value])) {
                $ativos[] = $recurso->value;
            }
        }

        $tenant = Tenant::findOrFail($this->tenant->getKey());
        $tenant->recursos = $ativos;
        $tenant->save();

        $this->tenant = $tenant;

        Flux::toast('Recursos atualizados.', variant: 'success');
    }

    /** Id do Dono alvo do reset de 2FA (preenchido ao abrir o modal de confirmação). */
    public ?int $resetAlvo = null;

    /** Abre a confirmação de reset de 2FA para um Dono. */
    public function confirmarReset(int $userId): void
    {
        abort_unless(auth('admin')->check(), 403);

        $this->resetAlvo = $userId;
        Flux::modal('resetar-2fa')->show();
    }

    /**
     * RESET de 2FA pelo super-admin (último recurso: Dono perdeu o celular E os códigos).
     * Desativa o 2FA do Dono (limpa os campos cifrados) no banco do tenant. Ação
     * reversível: o Dono pode reativar depois. Logada para auditoria, SEM dado sensível.
     */
    public function resetar2fa(?int $userId = null)
    {
        abort_unless(auth('admin')->check(), 403);

        // Botão do modal usa o alvo guardado; aceitamos o id explícito como fallback.
        $userId ??= $this->resetAlvo;

        if (! $userId) {
            return null;
        }

        $resetado = $this->tenant->run(function () use ($userId) {
            $user = User::role('Dono')->find($userId);

            if (! $user) {
                return false; // só reseta Donos (defesa)
            }

            $user->two_factor_secret = null;
            $user->two_factor_recovery_codes = null;
            $user->two_factor_confirmed_at = null;
            $user->save();

            return true;
        });

        $this->resetAlvo = null;

        // Vamos redirecionar (recarrega o detalhe). skipRender evita um re-render que,
        // logo após o run() acima ter encerrado a tenancy, tentaria reabrir o banco do
        // tenant à toa — desnecessário já que a página recarrega.
        $this->skipRender();

        if (! $resetado) {
            session()->flash('reset_2fa_erro', 'Dono não encontrado.');

            return redirect()->route('admin.tenant.detalhe', ['tenantId' => $this->tenant->id]);
        }

        Log::info('Suporte: 2FA do Dono resetado pelo super-admin', [
            'tenant' => $this->tenant->id,
            'dono_id' => $userId,
            'ip' => request()->ip(),
        ]);

        // Redirect (recarrega o detalhe mostrando "Sem 2FA") em vez de re-render no
        // mesmo request — mesmo padrão de impersonatar(). Mensagem via flash.
        session()->flash('reset_2fa_ok', '2FA do Dono desativado. Ele volta a entrar só com a senha.');

        return redirect()->route('admin.tenant.detalhe', ['tenantId' => $this->tenant->id]);
    }

    public function impersonatar()
    {
        abort_unless(auth('admin')->check(), 403);

        $donoId = $this->tenant->run(fn () => User::role('Dono')->value('id'));

        if (! $donoId) {
            Flux::toast('Este estabelecimento ainda não tem um Dono. Crie um antes de entrar.', variant: 'danger');

            return;
        }

        // Token de uso único (stancl). O redirect final é o painel do tenant.
        $token = tenancy()->impersonate(
            $this->tenant,
            (string) $donoId,
            route('painel.dashboard', ['tenant' => $this->tenant->id]),
            'web',
        );

        Log::info('Suporte: token de impersonação criado', [
            'tenant' => $this->tenant->id,
            'dono_id' => $donoId,
            'ip' => request()->ip(),
        ]);

        return redirect()->route('tenant.suporte.entrar', [
            'tenant' => $this->tenant->id,
            'token' => $token->token,
        ]);
    }

    public function render(): View
    {
        // Contagens lidas no banco do próprio tenant.
        $resumo = $this->tenant->run(function () {
            return [
                'equipe' => User::count(),
                'profissionais' => User::where('e_profissional', true)->count(),
                'clientes' => Cliente::count(),
                'servicos' => Servico::count(),
                'agendamentos' => Agendamento::count(),
                'donos' => User::role('Dono')->get(['id', 'name', 'email', 'two_factor_confirmed_at']),
            ];
        });

        return view('livewire.admin.tenant-detalhe', [
            'resumo' => $resumo,
            'planos' => config('planos', []),
        ]);
    }
}
