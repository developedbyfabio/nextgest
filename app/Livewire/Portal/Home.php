<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Home do portal do cliente (tenant). Mobile-first. Placeholder da 1A:
 * o fluxo de agendamento entra na 1C.
 */
#[Layout('components.layouts.portal')]
class Home extends Component
{
    public function render(): View
    {
        return view('livewire.portal.home');
    }
}
