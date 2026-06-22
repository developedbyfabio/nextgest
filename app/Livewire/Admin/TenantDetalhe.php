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

    public function mount(string $tenantId): void
    {
        abort_unless(auth('admin')->check(), 403);

        $this->tenant = Tenant::findOrFail($tenantId);

        // Pré-carrega os switches a partir do estado atual (recursos ligados no central).
        $ativos = $this->tenant->recursosAtivos();
        foreach (Recurso::cases() as $recurso) {
            $this->recursos[$recurso->value] = in_array($recurso->value, $ativos, true);
        }
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
                'donos' => User::role('Dono')->get(['id', 'name', 'email']),
            ];
        });

        return view('livewire.admin.tenant-detalhe', [
            'resumo' => $resumo,
        ]);
    }
}
