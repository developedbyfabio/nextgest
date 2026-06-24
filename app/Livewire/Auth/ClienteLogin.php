<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Livewire\Auth\Concerns\AutenticaPorGuard;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Login do cliente final (tenant, guard `cliente`). Mobile-first.
 */
#[Layout('components.layouts.portal-auth')]
#[Title('Entrar')]
class ClienteLogin extends Component
{
    use AutenticaPorGuard;

    public function login()
    {
        $this->autenticar('cliente');

        return $this->redirectRoute('tenant.home', ['tenant' => tenant('id')], navigate: true);
    }

    public function render(): View
    {
        return view('livewire.auth.cliente-login');
    }
}
