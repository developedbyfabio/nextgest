<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Equipe;

use App\Models\Unidade;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Editor de horários de trabalho de um profissional. Suporta múltiplas faixas
 * por dia (ex.: 09:00–12:00 e 13:00–18:00, deixando o almoço de fora).
 * Permissão: editar_usuario.
 */
#[Layout('components.layouts.painel')]
#[Title('Horários de trabalho')]
class Horarios extends Component
{
    use AuthorizesRequests;

    public User $profissional;

    /**
     * Faixas planas: cada item = [dia_semana, unidade_id, hora_inicio, hora_fim].
     *
     * @var array<int, array{dia_semana:int, unidade_id:int|string|null, hora_inicio:string, hora_fim:string}>
     */
    public array $faixas = [];

    public const DIAS = [
        1 => 'Segunda',
        2 => 'Terça',
        3 => 'Quarta',
        4 => 'Quinta',
        5 => 'Sexta',
        6 => 'Sábado',
        0 => 'Domingo',
    ];

    public function mount(User $user): void
    {
        $this->authorize('editar_usuario');

        $this->profissional = $user;

        $this->faixas = $user->horariosTrabalho()
            ->orderBy('dia_semana')
            ->orderBy('hora_inicio')
            ->get()
            ->map(fn ($h) => [
                'dia_semana' => $h->dia_semana,
                'unidade_id' => $h->unidade_id,
                'hora_inicio' => substr((string) $h->hora_inicio, 0, 5),
                'hora_fim' => substr((string) $h->hora_fim, 0, 5),
            ])->all();
    }

    public function adicionarFaixa(int $dia): void
    {
        $this->faixas[] = [
            'dia_semana' => $dia,
            'unidade_id' => Unidade::where('ativo', true)->value('id'),
            'hora_inicio' => '09:00',
            'hora_fim' => '18:00',
        ];
    }

    public function removerFaixa(int $index): void
    {
        unset($this->faixas[$index]);
        $this->faixas = array_values($this->faixas);
    }

    protected function rules(): array
    {
        return [
            'faixas' => ['array'],
            'faixas.*.dia_semana' => ['required', 'integer', 'between:0,6'],
            'faixas.*.unidade_id' => ['required', 'integer', 'exists:unidades,id'],
            'faixas.*.hora_inicio' => ['required', 'date_format:H:i'],
            'faixas.*.hora_fim' => ['required', 'date_format:H:i'],
        ];
    }

    public function salvar(): void
    {
        $this->authorize('editar_usuario');

        $this->validate();

        // Validação de fim > início por faixa.
        foreach ($this->faixas as $i => $faixa) {
            if ($faixa['hora_fim'] <= $faixa['hora_inicio']) {
                $this->addError("faixas.$i.hora_fim", 'O fim deve ser depois do início.');
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        DB::transaction(function () {
            // Substitui o conjunto de faixas do profissional.
            $this->profissional->horariosTrabalho()->delete();

            foreach ($this->faixas as $faixa) {
                $this->profissional->horariosTrabalho()->create($faixa);
            }
        });

        Flux::toast('Horários salvos.', variant: 'success');
    }

    public function render(): View
    {
        return view('livewire.painel.equipe.horarios', [
            'unidades' => Unidade::where('ativo', true)->orderBy('nome')->get(),
            'dias' => self::DIAS,
        ]);
    }
}
