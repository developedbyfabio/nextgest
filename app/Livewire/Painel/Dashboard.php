<?php

declare(strict_types=1);

namespace App\Livewire\Painel;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Dashboard da equipe do estabelecimento (tenant, guard `web`). O profissional
 * "puro" (vê só a própria agenda, sem ver_agenda) cai direto na agenda.
 */
#[Layout('components.layouts.painel')]
#[Title('Início')]
class Dashboard extends Component
{
    public function mount()
    {
        $user = auth('web')->user();

        if (! $user->can('ver_agenda') && $user->can('ver_agenda_propria')) {
            return $this->redirectRoute('painel.agenda', ['tenant' => tenant('id')], navigate: true);
        }
    }

    public function render(): View
    {
        return view('livewire.painel.dashboard');
    }
}
