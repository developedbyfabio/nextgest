<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Dashboard do super-admin (central). Placeholder da 1A.
 */
#[Layout('components.layouts.admin')]
#[Title('Início')]
class Dashboard extends Component
{
    public function render(): View
    {
        return view('livewire.admin.dashboard', [
            'totalTenants' => Tenant::count(),
        ]);
    }
}
