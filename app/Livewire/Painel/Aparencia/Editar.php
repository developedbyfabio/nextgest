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
use Livewire\WithFileUploads;

/**
 * Edição da identidade visual do estabelecimento (Dono/Gerente). Reaproveita
 * App\Support\Aparencia (presets, persistência, CSS vars) e o componente de
 * prévia x-ng.previa-portal. Permissão: gerir_aparencia.
 *
 * Modelo D36: a MARCA é ACENTO (cor principal/secundária) + TIPOGRAFIA + LOGO/
 * imagens. As SUPERFÍCIES (fundo/superfície/texto) seguem o modo claro/escuro do
 * Flux — por isso NÃO há campos de cor de superfície aqui (seriam controles que
 * mentem). A prévia mostra o portal em MODO CLARO (superfícies neutras) + a marca.
 */
#[Layout('components.layouts.painel')]
#[Title('Aparência')]
class Editar extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public string $cor_principal = '';

    public string $cor_secundaria = '';

    public string $fonte = '';

    public string $tamanho_base = '';

    // Caminhos persistidos das imagens (no disco do tenant) — null se não há.
    public ?string $logo = null;

    public ?string $header_imagem = null;

    public ?string $fundo_imagem = null;

    // Uploads temporários (substituem o caminho persistido ao salvar).
    public $logoUpload = null;

    public $headerUpload = null;

    public $fundoUpload = null;

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
        $this->fonte = $a['fonte'];
        $this->tamanho_base = $a['tamanho_base'];
        $this->logo = $a['logo'];
        $this->header_imagem = $a['header_imagem'];
        $this->fundo_imagem = $a['fundo_imagem'];
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

        // Tipos restritos a imagens rasterizadas (sem SVG, que pode carregar
        // script). 5 MB exige que o PHP (upload_max_filesize/post_max_size) também
        // permita — ver doc/gotcha; em dev, servir com `php -d ...`.
        $imagem = ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'];

        return [
            'cor_principal' => $hex,
            'cor_secundaria' => $hex,
            'fonte' => ['required', 'string', Rule::in(array_keys(Aparencia::FONTES))],
            'tamanho_base' => ['required', 'string', 'regex:/^\d{2}px$/'],
            'logoUpload' => $imagem,
            'headerUpload' => $imagem,
            'fundoUpload' => $imagem,
        ];
    }

    protected function messages(): array
    {
        return [
            'logoUpload.max' => 'A logo deve ter no máximo 5 MB.',
            'headerUpload.max' => 'A imagem de cabeçalho deve ter no máximo 5 MB.',
            'fundoUpload.max' => 'A imagem de fundo deve ter no máximo 5 MB.',
            'logoUpload.mimes' => 'Use PNG, JPG ou WebP.',
            'headerUpload.mimes' => 'Use PNG, JPG ou WebP.',
            'fundoUpload.mimes' => 'Use PNG, JPG ou WebP.',
        ];
    }

    public function removerImagem(string $campo): void
    {
        $this->authorize('gerir_aparencia');

        if (in_array($campo, ['logo', 'header_imagem', 'fundo_imagem'], true)) {
            $this->{$campo} = null;
        }
    }

    public function salvar(): void
    {
        $this->authorize('gerir_aparencia');

        $this->validate();

        // Persiste cada upload no disco do tenant (storage/tenant{id}/app/public).
        foreach ([['logoUpload', 'logo'], ['headerUpload', 'header_imagem'], ['fundoUpload', 'fundo_imagem']] as [$tmp, $campo]) {
            if ($this->{$tmp}) {
                $this->{$campo} = $this->{$tmp}->store('aparencia', 'public');
                $this->{$tmp} = null;
            }
        }

        // Salva só os campos do modelo D36 (marca + tipografia + imagens). As
        // superfícies permanecem nos valores padrão e não são editadas aqui.
        Aparencia::salvar([
            'cor_principal' => $this->cor_principal,
            'cor_secundaria' => $this->cor_secundaria,
            'fonte' => $this->fonte,
            'tamanho_base' => $this->tamanho_base,
            'logo' => $this->logo,
            'header_imagem' => $this->header_imagem,
            'fundo_imagem' => $this->fundo_imagem,
        ]);

        Flux::toast('Aparência salva.', variant: 'success');
    }

    /**
     * Estado atual do formulário como array de aparência (para a prévia). As
     * superfícies são FORÇADAS para o padrão CLARO (a prévia representa o portal
     * em modo claro — fiel ao app real, que usa tokens de claro/escuro, não cores
     * de superfície do tenant). Inclui as URLs resolvidas das imagens (upload
     * temporário se houver, senão o arquivo persistido) em chaves *_url.
     */
    public function aparenciaAtual(): array
    {
        return array_merge(Aparencia::doTenant(), [
            'cor_fundo' => Aparencia::PADRAO['cor_fundo'],
            'cor_superficie' => Aparencia::PADRAO['cor_superficie'],
            'cor_texto' => Aparencia::PADRAO['cor_texto'],
            'cor_texto_suave' => Aparencia::PADRAO['cor_texto_suave'],
            'cor_principal' => $this->cor_principal,
            'cor_secundaria' => $this->cor_secundaria,
            'fonte' => $this->fonte,
            'tamanho_base' => $this->tamanho_base,
            'logo' => $this->logo,
            'header_imagem' => $this->header_imagem,
            'fundo_imagem' => $this->fundo_imagem,
            'logo_url' => $this->urlPrevia($this->logoUpload, $this->logo),
            'header_url' => $this->urlPrevia($this->headerUpload, $this->header_imagem),
            'fundo_url' => $this->urlPrevia($this->fundoUpload, $this->fundo_imagem),
        ]);
    }

    /**
     * URL para a prévia de uma imagem: a do upload temporário (só se for uma
     * imagem previewável — evita quebrar a prévia se o usuário escolher um arquivo
     * não-imagem antes da validação) ou, senão, a do arquivo já persistido.
     */
    private function urlPrevia($upload, ?string $persistido): ?string
    {
        if ($upload && method_exists($upload, 'isPreviewable') && $upload->isPreviewable()) {
            return $upload->temporaryUrl();
        }

        return Aparencia::urlArquivo($persistido);
    }

    public function render(): View
    {
        return view('livewire.painel.aparencia.editar', [
            'templates' => Aparencia::TEMPLATES,
            'fontes' => Aparencia::FONTES,
            'aparencia' => $this->aparenciaAtual(),
        ]);
    }
}
