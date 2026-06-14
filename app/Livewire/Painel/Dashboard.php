<?php

declare(strict_types=1);

namespace App\Livewire\Painel;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Dashboard da equipe do estabelecimento (tenant, guard `web`). Placeholder da 1A.
 */
#[Layout('components.layouts.painel')]
#[Title('Início')]
class Dashboard extends Component
{
    public function render(): View
    {
        return view('livewire.painel.dashboard');
    }
}
