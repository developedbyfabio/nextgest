<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Integracoes;

use App\Enums\Integracao;
use App\Models\GatewayPagamento;
use App\Models\WhatsappConfig;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Hub de Integrações do estabelecimento (Dono/Gerente). Mostra um card por
 * integração DISPONÍVEL = recurso (flag 0a) ligado + usuário com a permissão da
 * integração. Nenhuma disponível → estado vazio ("Nenhuma integração disponível"),
 * que é o comportamento CORRETO (tudo nasce desligado na 0a). É o primeiro uso
 * real do mecanismo de flags. Cada card leva ao editor (rota gated por recurso).
 */
#[Layout('components.layouts.painel')]
#[Title('Integrações')]
class Index extends Component
{
    public function mount(): void
    {
        abort_unless(auth('web')->check(), 403);

        // Acesso à tela exige ao menos UMA permissão de integração (default Dono/Gerente).
        abort_unless(
            auth('web')->user()->hasAnyPermission(Integracao::permissoes()),
            403,
        );
    }

    public function render(): View
    {
        $user = auth('web')->user();

        // Disponível = flag do recurso ligada (0a) E usuário tem a permissão dela.
        $disponiveis = collect(Integracao::cases())
            ->filter(fn (Integracao $i): bool => tenant_tem_recurso($i->recurso()->value) && $user->can($i->permissao()))
            ->values();

        // Status configurado/não — sem expor o segredo.
        $status = $disponiveis->mapWithKeys(fn (Integracao $i): array => [$i->value => $this->configurado($i)]);

        return view('livewire.painel.integracoes.index', [
            'disponiveis' => $disponiveis,
            'status' => $status,
        ]);
    }

    private function configurado(Integracao $i): bool
    {
        return match ($i) {
            Integracao::MercadoPago => filled(
                (GatewayPagamento::where('provedor', 'mercadopago')->first()?->credenciais ?? [])['access_token'] ?? null
            ),
            Integracao::Whatsapp => filled(WhatsappConfig::query()->first()?->token),
        };
    }
}
