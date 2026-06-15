<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Aparencia;

use App\Support\Aparencia;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Edição da identidade visual do estabelecimento (Dono/Gerente). Reaproveita
 * App\Support\Aparencia (presets, persistência, CSS vars) e o componente de
 * prévia x-ng.previa-portal. Permissão: gerir_aparencia.
 */
#[Layout('components.layouts.painel')]
#[Title('Aparência')]
class Editar extends Component
{
    use AuthorizesRequests;

    public string $cor_principal = '';

    public string $cor_secundaria = '';

    public string $cor_fundo = '';

    public string $cor_superficie = '';

    public string $cor_texto = '';

    public string $cor_texto_suave = '';

    public string $fonte = '';

    public string $tamanho_base = '';

    public string $menu_posicao = '';

    public string $icone_estilo = '';

    public function mount(): void
    {
        $this->authorize('gerir_aparencia');
        $this->preencher(Aparencia::doTenant());
    }

    /** @param array<string,mixed> $a */
    protected function preencher(array $a): void
    {
        $this->cor_principal = $a['cor_principal'];
        $this->cor_secundaria = $a['cor_secundaria'];
        $this->cor_fundo = $a['cor_fundo'];
        $this->cor_superficie = $a['cor_superficie'];
        $this->cor_texto = $a['cor_texto'];
        $this->cor_texto_suave = $a['cor_texto_suave'];
        $this->fonte = $a['fonte'];
        $this->tamanho_base = $a['tamanho_base'];
        $this->menu_posicao = $a['menu_posicao'];
        $this->icone_estilo = $a['icone_estilo'];
    }

    public function aplicarTemplate(string $chave): void
    {
        $this->authorize('gerir_aparencia');

        $template = Aparencia::template($chave);
        if ($template === null) {
            return;
        }

        $this->preencher(array_merge(Aparencia::doTenant(), $template));
        Flux::toast('Template aplicado — ajuste e salve.', variant: 'success');
    }

    protected function rules(): array
    {
        $hex = ['required', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'];

        return [
            'cor_principal' => $hex,
            'cor_secundaria' => $hex,
            'cor_fundo' => $hex,
            'cor_superficie' => $hex,
            'cor_texto' => $hex,
            'cor_texto_suave' => $hex,
            'fonte' => ['required', 'string', 'max:255'],
            'tamanho_base' => ['required', 'string', 'regex:/^\d{2}px$/'],
            'menu_posicao' => ['required', Rule::in(['topo', 'lateral'])],
            'icone_estilo' => ['required', Rule::in(['outline', 'solid'])],
        ];
    }

    public function salvar(): void
    {
        $this->authorize('gerir_aparencia');

        Aparencia::salvar($this->validate());

        Flux::toast('Aparência salva.', variant: 'success');
    }

    /** Estado atual do formulário como array de aparência (para a prévia). */
    public function aparenciaAtual(): array
    {
        return array_merge(Aparencia::doTenant(), [
            'cor_principal' => $this->cor_principal,
            'cor_secundaria' => $this->cor_secundaria,
            'cor_fundo' => $this->cor_fundo,
            'cor_superficie' => $this->cor_superficie,
            'cor_texto' => $this->cor_texto,
            'cor_texto_suave' => $this->cor_texto_suave,
            'fonte' => $this->fonte,
            'tamanho_base' => $this->tamanho_base,
            'menu_posicao' => $this->menu_posicao,
            'icone_estilo' => $this->icone_estilo,
        ]);
    }

    public function render(): View
    {
        return view('livewire.painel.aparencia.editar', [
            'templates' => Aparencia::TEMPLATES,
            'fontes' => [
                "'Instrument Sans', ui-sans-serif, system-ui, sans-serif" => 'Instrument Sans',
                'ui-sans-serif, system-ui, sans-serif' => 'Sistema (sans-serif)',
                "Georgia, 'Times New Roman', serif" => 'Georgia (serif)',
                "'Courier New', ui-monospace, monospace" => 'Monoespaçada',
            ],
            'aparencia' => $this->aparenciaAtual(),
        ]);
    }
}
