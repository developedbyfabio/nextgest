<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Perfil;

use App\Support\Aparencia;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Foto de perfil do PRÓPRIO usuário (self-service, todos os papéis). Embutido no
 * layout do painel como um flux:modal, aberto pelo dropdown do perfil (rodapé).
 *
 * Reaproveita o caminho de upload da Aparência (D36) — SEM caminho paralelo:
 *  - WithFileUploads + store('aparencia','public') → disco do tenant (isolado);
 *  - mesma validação ['nullable','image','mimes:png,jpg,jpeg,webp','max:5120'] (sem SVG);
 *  - servido por TenantArquivoController via Aparencia::urlArquivo().
 *
 * O recorte QUADRADO é feito no cliente (Cropper.js empacotado via Vite): o canvas
 * 512x512 vira um blob PNG e sobe pelo upload do Livewire ($wire.upload). Aqui só
 * validamos e persistimos. Após salvar/remover, recarrega a página (reload completo,
 * navigate:false) para o avatar do rodapé — que é renderizado no layout, fora deste
 * componente — refletir a mudança (mesmo padrão da Aparência).
 */
class Foto extends Component
{
    use WithFileUploads;

    /** Upload temporário (blob quadrado vindo do Cropper). */
    public $foto = null;

    /** URL da página atual, capturada no mount (GET) para o reload pós-salvar. */
    public string $urlAtual = '';

    public function mount(): void
    {
        $this->urlAtual = url()->current();

        // Mensagem de sucesso após o reload disparado por salvar()/remover() (flash).
        if (session('foto_perfil_msg')) {
            Flux::toast(session('foto_perfil_msg'), variant: 'success');
        }
    }

    protected function rules(): array
    {
        // Mesmos tipos/limite da Aparência: imagens rasterizadas, SEM SVG (que pode
        // carregar script). 5 MB precisa caber no limite do PHP (ver gotcha de upload).
        return [
            'foto' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
        ];
    }

    protected function messages(): array
    {
        return [
            'foto.max' => 'A foto deve ter no máximo 5 MB.',
            'foto.mimes' => 'Use PNG, JPG ou WebP.',
            'foto.image' => 'O arquivo precisa ser uma imagem.',
        ];
    }

    public function salvar()
    {
        $this->validate();

        if (! $this->foto) {
            return null;
        }

        $user = auth('web')->user();
        // Mesma pasta/disco da Aparência (storage/tenant{id}/app/public/aparencia).
        $user->foto_perfil = $this->foto->store('aparencia', 'public');
        $user->save();

        $this->foto = null;
        session()->flash('foto_perfil_msg', 'Foto de perfil atualizada.');

        return $this->redirect($this->urlAtual, navigate: false);
    }

    public function remover()
    {
        $user = auth('web')->user();
        $user->foto_perfil = null;
        $user->save();

        session()->flash('foto_perfil_msg', 'Foto de perfil removida.');

        return $this->redirect($this->urlAtual, navigate: false);
    }

    public function render(): View
    {
        return view('livewire.painel.perfil.foto', [
            'fotoUrl' => Aparencia::urlArquivo(auth('web')->user()?->foto_perfil),
        ]);
    }
}
