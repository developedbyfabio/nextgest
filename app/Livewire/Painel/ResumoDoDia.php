<?php

declare(strict_types=1);

namespace App\Livewire\Painel;

use App\Services\Painel\ResumoDoDia as ResumoCalculo;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Seção "resumo do dia" exibida no topo do painel (dashboard E agenda) ao logar.
 * In-app, só leitura. O conteúdo por papel/pessoa vem de App\Services\Painel\ResumoDoDia
 * (casa por `ver_agenda`, pessoal por atributo `e_profissional` — nunca por papel).
 *
 * É embutido com <livewire:painel.resumo-do-dia /> e renderiza inline (sem layout).
 */
class ResumoDoDia extends Component
{
    public function render(): View
    {
        $user = auth('web')->user();

        $resumo = $user
            ? (new ResumoCalculo($user))->dados()
            : ['mostraCasa' => false, 'mostraPessoal' => false, 'casaTotal' => 0, 'casaPendentes' => 0, 'meuTotal' => 0, 'proximo' => null];

        return view('livewire.painel.resumo-do-dia', [
            'resumo' => $resumo,
        ]);
    }
}
