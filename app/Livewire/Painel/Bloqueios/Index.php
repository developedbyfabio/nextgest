<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Bloqueios;

use App\Models\Bloqueio;
use App\Models\User;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * CRUD de bloqueios pontuais (folga, feriado, imprevisto) por profissional.
 * Permissão: gerir_agenda (Dono/Gerente/Recepção).
 *
 * Bloqueio é evento operacional da agenda — excluí-lo simplesmente libera o
 * horário (não há inativação; diferente dos cadastros da 1B).
 */
#[Layout('components.layouts.painel')]
#[Title('Bloqueios')]
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public bool $mostrarFormulario = false;

    public ?int $editandoId = null;

    public ?int $user_id = null;

    public string $inicio = '';

    public string $fim = '';

    public string $motivo = '';

    public ?int $confirmarId = null;

    public function mount(): void
    {
        $this->authorize('gerir_agenda');
    }

    protected function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('e_profissional', true)],
            'inicio' => ['required', 'date'],
            'fim' => ['required', 'date', 'after:inicio'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function novo(): void
    {
        $this->authorize('gerir_agenda');
        $this->reset(['editandoId', 'user_id', 'inicio', 'fim', 'motivo']);
        $this->resetValidation();
        $this->mostrarFormulario = true;
    }

    public function editar(int $id): void
    {
        $this->authorize('gerir_agenda');

        $bloqueio = Bloqueio::findOrFail($id);

        $this->editandoId = $bloqueio->id;
        $this->user_id = $bloqueio->user_id;
        $this->inicio = $bloqueio->inicio->format('Y-m-d\TH:i');
        $this->fim = $bloqueio->fim->format('Y-m-d\TH:i');
        $this->motivo = $bloqueio->motivo ?? '';
        $this->resetValidation();
        $this->mostrarFormulario = true;
    }

    public function salvar(): void
    {
        $this->authorize('gerir_agenda');

        $dados = $this->validate();

        Bloqueio::updateOrCreate(['id' => $this->editandoId], [
            'user_id' => $dados['user_id'],
            'inicio' => Carbon::parse($dados['inicio']),
            'fim' => Carbon::parse($dados['fim']),
            'motivo' => $dados['motivo'] ?: null,
        ]);

        $this->mostrarFormulario = false;
        $this->reset(['editandoId', 'user_id', 'inicio', 'fim', 'motivo']);

        Flux::toast('Bloqueio salvo.', variant: 'success');
    }

    public function pedirExcluir(int $id): void
    {
        $this->authorize('gerir_agenda');
        $this->confirmarId = $id;
        Flux::modal('remover-bloqueio')->show();
    }

    public function excluir(int $id): void
    {
        $this->authorize('gerir_agenda');
        Bloqueio::whereKey($id)->delete();
        $this->confirmarId = null;
        Flux::modal('remover-bloqueio')->close();
        Flux::toast('Bloqueio removido.');
    }

    public function render(): View
    {
        return view('livewire.painel.bloqueios.index', [
            'bloqueios' => Bloqueio::with('user')->orderByDesc('inicio')->paginate(15),
            'profissionais' => User::where('e_profissional', true)->where('ativo', true)->orderBy('name')->get(),
        ]);
    }
}
