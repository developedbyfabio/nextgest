<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Livewire\Auth\Concerns\AutenticaPorGuard;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Login do super-admin (central, guard `admin`).
 */
#[Layout('components.layouts.auth')]
#[Title('Acesso administrativo')]
class AdminLogin extends Component
{
    use AutenticaPorGuard;

    protected function credenciaisExtras(): array
    {
        return ['ativo' => true];
    }

    public function login()
    {
        $this->autenticar('admin');

        return $this->redirectRoute('admin.dashboard', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.auth.admin-login');
    }
}
