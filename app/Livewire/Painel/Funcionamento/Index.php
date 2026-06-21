<?php

declare(strict_types=1);

namespace App\Livewire\Painel\Funcionamento;

use App\Livewire\Painel\Equipe\Horarios;
use App\Models\Configuracao;
use App\Models\ExcecaoFuncionamento;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Funcionamento do estabelecimento (painel): horário SEMANAL (reusa o mesmo editor
 * do onboarding, x-funcionamento-editor) + CALENDÁRIO de exceções (feriados/
 * fechamentos/horário especial). Ambos alimentam o MotorDisponibilidade via
 * App\Services\Agendamento\Funcionamento. Permissão: gerir_agenda (Dono/Gerente/
 * Recepção — mesma que gere a agenda).
 */
#[Layout('components.layouts.painel')]
#[Title('Funcionamento')]
class Index extends Component
{
    use AuthorizesRequests;

    /** @var array<int, array{dia:int, rotulo:string, aberto:bool, inicio:string, fim:string}> */
    public array $funcionamento = [];

    public string $mesAtual; // 'Y-m-01'

    // Modal de exceção.
    public bool $mostrarExcecao = false;

    public ?string $excecaoData = null;

    public string $excecaoTipo = 'fechado';

    public ?string $excecaoInicio = '09:00';

    public ?string $excecaoFim = '18:00';

    public ?string $excecaoDescricao = null;

    public ?int $excecaoEditId = null;

    public function mount(): void
    {
        $this->authorize('gerir_agenda');
        $this->funcionamento = $this->carregarFuncionamento();
        $this->mesAtual = Carbon::now()->startOfMonth()->format('Y-m-d');
    }

    /** @return array<int, array<string, mixed>> */
    private function carregarFuncionamento(): array
    {
        $salvo = collect(json_decode((string) Configuracao::valor('horario_funcionamento'), true) ?: [])
            ->keyBy('dia');

        $lista = [];
        foreach (Horarios::DIAS as $dia => $rotulo) {
            $f = $salvo->get($dia);
            $lista[] = [
                'dia' => $dia,
                'rotulo' => $rotulo,
                'aberto' => (bool) ($f['aberto'] ?? ($dia !== 0)),
                'inicio' => $f['inicio'] ?? '09:00',
                'fim' => $f['fim'] ?? ($dia === 6 ? '13:00' : '18:00'),
            ];
        }

        return $lista;
    }

    public function salvarHorario(): void
    {
        $this->authorize('gerir_agenda');
        $this->resetValidation();

        $abertos = 0;
        $erro = false;

        foreach ($this->funcionamento as $i => $f) {
            if (! ($f['aberto'] ?? false)) {
                continue;
            }
            $abertos++;
            $okFmt = preg_match('/^\d{2}:\d{2}$/', (string) $f['inicio']) && preg_match('/^\d{2}:\d{2}$/', (string) $f['fim']);
            if (! $okFmt || $f['inicio'] >= $f['fim']) {
                $this->addError("funcionamento.$i.fim", 'O fim deve ser após o início.');
                $erro = true;
            }
        }

        if ($abertos === 0) {
            $this->addError('funcionamento', 'Defina ao menos um dia de funcionamento.');
            $erro = true;
        }

        if ($erro) {
            return;
        }

        Configuracao::updateOrCreate(
            ['chave' => 'horario_funcionamento'],
            ['valor' => json_encode(collect($this->funcionamento)->map(fn ($f) => [
                'dia' => (int) $f['dia'],
                'aberto' => (bool) $f['aberto'],
                'inicio' => $f['inicio'],
                'fim' => $f['fim'],
            ])->all())],
        );

        Flux::toast('Horário de funcionamento salvo.', variant: 'success');
    }

    public function navegarMes(int $dir): void
    {
        $this->mesAtual = Carbon::parse($this->mesAtual)->addMonths($dir)->startOfMonth()->format('Y-m-d');
    }

    public function abrirExcecao(string $data): void
    {
        $this->authorize('gerir_agenda');
        $d = Carbon::parse($data)->startOfDay();

        if ($d->isPast() && ! $d->isToday()) {
            Flux::toast('Escolha um dia de hoje em diante.', variant: 'warning');

            return;
        }

        $this->resetValidation();
        $this->excecaoData = $d->toDateString();
        $existente = ExcecaoFuncionamento::whereDate('data', $d->toDateString())->first();

        if ($existente) {
            $this->excecaoEditId = $existente->id;
            $this->excecaoTipo = $existente->tipo;
            $this->excecaoInicio = $existente->hora_inicio ? substr((string) $existente->hora_inicio, 0, 5) : '09:00';
            $this->excecaoFim = $existente->hora_fim ? substr((string) $existente->hora_fim, 0, 5) : '18:00';
            $this->excecaoDescricao = $existente->descricao;
        } else {
            $this->excecaoEditId = null;
            $this->excecaoTipo = 'fechado';
            $this->excecaoInicio = '09:00';
            $this->excecaoFim = '18:00';
            $this->excecaoDescricao = null;
        }

        $this->mostrarExcecao = true;
    }

    public function salvarExcecao(): void
    {
        $this->authorize('gerir_agenda');

        $regras = [
            'excecaoData' => ['required', 'date'],
            'excecaoTipo' => ['required', 'in:fechado,horario_especial'],
            'excecaoDescricao' => ['nullable', 'string', 'max:120'],
        ];
        if ($this->excecaoTipo === 'horario_especial') {
            $regras['excecaoInicio'] = ['required', 'date_format:H:i'];
            $regras['excecaoFim'] = ['required', 'date_format:H:i', 'after:excecaoInicio'];
        }
        $this->validate($regras, attributes: [
            'excecaoInicio' => 'início', 'excecaoFim' => 'fim', 'excecaoDescricao' => 'descrição',
        ]);

        ExcecaoFuncionamento::updateOrCreate(
            ['data' => $this->excecaoData],
            [
                'tipo' => $this->excecaoTipo,
                'hora_inicio' => $this->excecaoTipo === 'horario_especial' ? $this->excecaoInicio : null,
                'hora_fim' => $this->excecaoTipo === 'horario_especial' ? $this->excecaoFim : null,
                'descricao' => $this->excecaoDescricao,
            ],
        );

        $this->mostrarExcecao = false;
        Flux::toast('Exceção salva.', variant: 'success');
    }

    public function removerExcecao(int $id): void
    {
        $this->authorize('gerir_agenda');
        ExcecaoFuncionamento::whereKey($id)->delete();
        $this->mostrarExcecao = false;
        Flux::toast('Exceção removida.');
    }

    public function render(): View
    {
        $inicio = Carbon::parse($this->mesAtual)->startOfMonth();
        $fim = $inicio->copy()->endOfMonth();

        $excecoesMes = ExcecaoFuncionamento::whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])
            ->get()
            ->keyBy(fn ($e) => $e->data->toDateString());

        // Grade do mês: começa no domingo da 1ª semana, termina no sábado da última.
        $gridInicio = $inicio->copy()->startOfWeek(Carbon::SUNDAY);
        $gridFim = $fim->copy()->endOfWeek(Carbon::SATURDAY);
        $dias = [];
        for ($d = $gridInicio->copy(); $d->lte($gridFim); $d->addDay()) {
            $dias[] = $d->copy();
        }

        return view('livewire.painel.funcionamento.index', [
            'excecoesMes' => $excecoesMes,
            'excecoesProximas' => ExcecaoFuncionamento::whereDate('data', '>=', Carbon::today()->toDateString())
                ->orderBy('data')->limit(60)->get(),
            'dias' => $dias,
            'mesLabel' => $inicio->translatedFormat('F Y'),
            'mesInicio' => $inicio,
        ]);
    }
}
