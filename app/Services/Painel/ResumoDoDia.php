<?php

declare(strict_types=1);

namespace App\Services\Painel;

use App\Models\Agendamento;
use App\Models\User;
use Carbon\Carbon;

/**
 * Resumo do dia (in-app) exibido no topo do painel ao logar. É só LEITURA dos
 * agendamentos de HOJE (no contexto do tenant) — sem estado novo, sem push.
 *
 * Conteúdo por papel/pessoa (gate por permissão/atributo, NUNCA por papel — D39):
 * - Casa (gestão): quem tem `ver_agenda` (Dono/Gerente/Recepção) — total de hoje +
 *   quantos a confirmar (pendentes). Query AGREGADA (uma só, sem loop).
 * - Pessoal: quem é profissional (atributo `e_profissional`) — quantos hoje são DELE
 *   + o próximo horário (1 query ordenada, limit 1).
 * - Quem é os dois (Dono que também atende): vê os dois blocos.
 *
 * "Hoje" usa o mesmo critério da agenda: intervalo [início, fim] do dia local
 * (Carbon respeita APP_TIMEZONE) sobre `data_hora_inicio`. "Agendamentos" conta os
 * OCUPANTES (exclui cancelado/nao_compareceu, como o scope da agenda).
 */
final class ResumoDoDia
{
    public function __construct(private User $user) {}

    /**
     * @return array{
     *   mostraCasa: bool, casaTotal: int, casaPendentes: int,
     *   mostraPessoal: bool, meuTotal: int, proximo: ?Agendamento
     * }
     */
    public function dados(): array
    {
        [$de, $ate] = $this->intervaloHoje();

        $resumo = [
            'mostraCasa' => false,
            'casaTotal' => 0,
            'casaPendentes' => 0,
            'mostraPessoal' => false,
            'meuTotal' => 0,
            'proximo' => null,
        ];

        // Casa: só quem enxerga a agenda inteira (Dono/Gerente/Recepção).
        if ($this->user->can('ver_agenda')) {
            $linha = Agendamento::query()
                ->whereBetween('data_hora_inicio', [$de, $ate])
                ->whereNotIn('status', Agendamento::STATUS_LIVRES)
                ->selectRaw('COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pendentes', ['pendente'])
                ->first();

            $resumo['mostraCasa'] = true;
            $resumo['casaTotal'] = (int) ($linha->total ?? 0);
            $resumo['casaPendentes'] = (int) ($linha->pendentes ?? 0);
        }

        // Pessoal: quem atende (atributo, independe do papel).
        if ($this->user->e_profissional) {
            $resumo['mostraPessoal'] = true;
            $resumo['meuTotal'] = $this->baseDoProfissional($de, $ate)->count();
            $resumo['proximo'] = $this->baseDoProfissional($de, $ate)
                ->where('data_hora_inicio', '>=', Carbon::now())
                ->orderBy('data_hora_inicio')
                ->with('cliente:id,nome')
                ->first();
        }

        return $resumo;
    }

    /** Agendamentos ocupantes de HOJE do profissional logado. */
    private function baseDoProfissional(Carbon $de, Carbon $ate)
    {
        return Agendamento::query()
            ->where('profissional_id', $this->user->getKey())
            ->whereNotIn('status', Agendamento::STATUS_LIVRES)
            ->whereBetween('data_hora_inicio', [$de, $ate]);
    }

    /** Intervalo do dia local (mesmo critério da agenda). */
    private function intervaloHoje(): array
    {
        $hoje = Carbon::today();

        return [$hoje->copy()->startOfDay(), $hoje->copy()->endOfDay()];
    }
}
