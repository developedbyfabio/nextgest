<?php

declare(strict_types=1);

namespace App\Livewire\Painel;

use App\Models\Agendamento;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Aviso "próximo atendimento chegando" (D69). Componente GLOBAL do painel (embutido no
 * layout) que, por polling leve do Livewire (~60s), checa se o PROFISSIONAL logado tem
 * um atendimento "a atender" começando em ≤ 15 min e dispara UM toast (reusa o sistema
 * existente do Flux), idempotente por sessão.
 *
 * SÓ LÊ a agenda — não toca o MotorDisponibilidade nem o fluxo de atendimento. Só roda
 * para profissionais (o layout só embute o componente quando `e_profissional`). Fuso:
 * `now()` respeita APP_TIMEZONE. Query leve no índice composto (profissional_id,
 * data_hora_inicio).
 */
class AvisoProximoAtendimento extends Component
{
    /** Antecedência do aviso, em minutos. */
    private const JANELA_MIN = 15;

    // A checagem dispara por `wire:init` (no cliente, só em páginas REALMENTE
    // renderizadas) e por `wire:poll` — NÃO no mount(). Motivo: telas que redirecionam
    // no mount (ex.: o Dashboard manda o profissional p/ a agenda) ainda renderizam o
    // layout/este componente antes do redirect; rodar no mount "consumiria" o aviso numa
    // página descartada (marcava a sessão e o toast se perdia). Ver D69.
    public function verificar(): void
    {
        $user = auth('web')->user();

        if (! $user?->e_profissional) {
            return; // só profissional (defesa; o layout já gateia)
        }

        $agora = now();

        $proximo = Agendamento::query()
            ->where('profissional_id', $user->id)
            ->whereNotIn('status', ['concluido', 'cancelado', 'nao_compareceu'])
            ->whereBetween('data_hora_inicio', [$agora, $agora->copy()->addMinutes(self::JANELA_MIN)])
            ->with(['cliente:id,nome', 'itens.servico:id,nome'])
            ->orderBy('data_hora_inicio')
            ->first();

        if (! $proximo) {
            return;
        }

        // Idempotência: avisa UMA vez por agendamento (persiste entre polls e navegações).
        $chave = 'aviso_proximo:'.$user->id;
        $avisados = session()->get($chave, []);

        if (in_array($proximo->id, $avisados, true)) {
            return;
        }

        $cliente = $proximo->cliente?->nome ?? 'Cliente';
        $hora = $proximo->data_hora_inicio->format('H:i');
        $servico = $proximo->itens->first()?->servico?->nome;

        Flux::toast(
            text: $cliente.' · '.$hora.($servico ? ' · '.$servico : ''),
            heading: 'Seu próximo atendimento está chegando',
            duration: 8000,
        );

        session()->put($chave, [...$avisados, $proximo->id]);
    }

    public function render(): View
    {
        return view('livewire.painel.aviso-proximo-atendimento');
    }
}
