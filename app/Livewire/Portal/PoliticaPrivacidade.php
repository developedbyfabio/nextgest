<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Política de Privacidade do portal (D93) — página PÚBLICA (sem login), no layout
 * do portal (tema do tenant aplicado). Conteúdo único/compartilhado; servida por
 * tenant na URL (/{tenant}/politica-de-privacidade). Sem estado — só renderiza a
 * view; segue o padrão de página full-page do portal (Layout + Title).
 */
#[Layout('components.layouts.portal')]
#[Title('Política de Privacidade')]
class PoliticaPrivacidade extends Component
{
    public function render(): View
    {
        return view('livewire.portal.politica-privacidade');
    }
}
