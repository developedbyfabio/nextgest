<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Termos de Uso do portal (D93) — página PÚBLICA (sem login), no layout do portal
 * (tema do tenant aplicado). Conteúdo único/compartilhado; servida por tenant na
 * URL (/{tenant}/termos-de-uso). Sem estado — segue o padrão full-page do portal.
 */
#[Layout('components.layouts.portal')]
#[Title('Termos de Uso')]
class TermosUso extends Component
{
    public function render(): View
    {
        return view('livewire.portal.termos-uso');
    }
}
