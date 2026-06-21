<?php

declare(strict_types=1);

use App\Livewire\Painel\Dashboard;
use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Servico;
use App\Models\Unidade;
use App\Services\Dashboard\Metricas;
use Carbon\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = criarTenant('lojadash');
    tenancy()->initialize($this->tenant);

    $this->unidade = Unidade::create(['nome' => 'Centro', 'ativo' => true]);
    $this->corte = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 50, 'ativo' => true]);
    $this->barba = Servico::create(['nome' => 'Barba', 'duracao_minutos' => 20, 'preco' => 30, 'ativo' => true]);

    $this->jorge = usuarioComPapel('Profissional', ['name' => 'Jorge', 'email' => 'jorge@dash.test', 'e_profissional' => true]);
    $this->ana = usuarioComPapel('Profissional', ['name' => 'Ana', 'email' => 'ana@dash.test', 'e_profissional' => true]);

    $this->c1 = Cliente::create(['nome' => 'C1', 'telefone' => '1', 'email' => 'c1@dash.test']);
    $this->c2 = Cliente::create(['nome' => 'C2', 'telefone' => '2', 'email' => 'c2@dash.test']);
    $this->c3 = Cliente::create(['nome' => 'C3', 'telefone' => '3', 'email' => 'c3@dash.test']);
});

function criarAg(Cliente $cliente, $prof, Carbon $inicio, string $status, array $servicos): Agendamento
{
    $itens = collect($servicos);
    $ag = Agendamento::create([
        'unidade_id' => test()->unidade->id,
        'cliente_id' => $cliente->id,
        'profissional_id' => $prof->id,
        'data_hora_inicio' => $inicio,
        'data_hora_fim' => $inicio->copy()->addMinutes((int) $itens->sum('duracao_minutos')),
        'status' => $status,
        'origem' => 'equipe',
        'valor_total' => (float) $itens->sum('preco'),
    ]);

    foreach ($itens as $s) {
        $ag->itens()->create(['servico_id' => $s->id, 'preco' => $s->preco, 'duracao_minutos' => $s->duracao_minutos]);
    }

    return $ag;
}

function semearCenario(): void
{
    $hoje = Carbon::today();
    criarAg(test()->c1, test()->jorge, $hoje->copy()->setTime(10, 0), 'concluido', [test()->corte]);
    criarAg(test()->c1, test()->jorge, $hoje->copy()->setTime(11, 0), 'concluido', [test()->corte, test()->barba]);
    criarAg(test()->c2, test()->ana, $hoje->copy()->setTime(14, 0), 'nao_compareceu', [test()->barba]);
    criarAg(test()->c3, test()->jorge, $hoje->copy()->setTime(16, 0), 'confirmado', [test()->corte]);
    criarAg(test()->c2, test()->ana, $hoje->copy()->subDay()->setTime(10, 0), 'cancelado', [test()->corte]);
    // Fora da janela de 30 dias (cai no período anterior).
    criarAg(test()->c1, test()->jorge, $hoje->copy()->subDays(40)->setTime(10, 0), 'concluido', [test()->corte]);
}

function metricas30d(): Metricas
{
    return new Metricas(Carbon::today()->subDays(29)->startOfDay(), Carbon::today()->endOfDay());
}

it('conta total de agendamentos no período e compara com o anterior', function () {
    semearCenario();
    $m = metricas30d();

    expect($m->totalAgendamentos())->toBe(5);

    $comp = $m->comparativoTotal();
    expect($comp['atual'])->toBe(5)
        ->and($comp['anterior'])->toBe(1) // o de 40 dias atrás
        ->and($comp['delta'])->toBe(400.0);
});

it('faturamento NÃO vem de agendamentos — só de vendas pagas (Fatia 2D)', function () {
    semearCenario(); // só agendamentos concluídos, nenhuma comanda/venda
    // Atendimento sem comanda não vira faturamento.
    expect(metricas30d()->faturamento())->toBe(0.0)
        ->and(metricas30d()->vendasPagas())->toBe(0)
        ->and(metricas30d()->ticketMedio())->toBe(0.0);
});

it('conta clientes novos e recorrentes', function () {
    semearCenario();
    $m = metricas30d();

    expect($m->clientesNovos())->toBe(3); // criados agora
    expect($m->clientesRecorrentes())->toBe(2); // c1 e c2 têm 2+ no período
});

it('lista serviços mais agendados (exclui cancelado/não compareceu)', function () {
    semearCenario();
    $top = metricas30d()->servicosTop();

    $corte = $top->firstWhere('nome', 'Corte');
    $barba = $top->firstWhere('nome', 'Barba');
    expect($corte['total'])->toBe(3) // 10h, 11h, 16h (confirmado conta)
        ->and($barba['total'])->toBe(1); // só o 11h (o 14h é não compareceu)
});

it('ranqueia profissionais por concluídos e valor estimado', function () {
    semearCenario();
    $profs = metricas30d()->profissionaisDesempenho();

    expect($profs->first()['nome'])->toBe('Jorge')
        ->and($profs->first()['total'])->toBe(2)
        ->and($profs->first()['valor'])->toBe(130.0);
});

it('calcula a taxa de comparecimento', function () {
    semearCenario();
    $c = metricas30d()->comparecimento();

    expect($c['concluido'])->toBe(2)
        ->and($c['nao_compareceu'])->toBe(1)
        ->and($c['cancelado'])->toBe(1)
        ->and($c['taxa'])->toBe(50.0);
});

it('distribui agendamentos por dia e por hora', function () {
    semearCenario();
    $m = metricas30d();

    $porDia = $m->agendamentosPorDia();
    expect(array_sum($porDia['valores']))->toBe(5);

    // Por hora exclui cancelado/não compareceu: 10h, 11h, 16h => 3 ocupantes.
    $porHora = $m->horariosPorHora();
    expect(array_sum($porHora['valores']))->toBe(3);
});

it('o filtro de período altera os resultados', function () {
    semearCenario();

    $hoje = new Metricas(Carbon::today()->startOfDay(), Carbon::today()->endOfDay());
    expect($hoje->totalAgendamentos())->toBe(4); // os 4 de hoje (sem o de ontem)
    expect(metricas30d()->totalAgendamentos())->toBe(5);
});

it('filtra por unidade', function () {
    semearCenario();
    $outra = Unidade::create(['nome' => 'Filial', 'ativo' => true]);
    criarAg($this->c3, $this->jorge, Carbon::today()->setTime(9, 0), 'concluido', [$this->corte])
        ->update(['unidade_id' => $outra->id]);

    $m = new Metricas(Carbon::today()->subDays(29)->startOfDay(), Carbon::today()->endOfDay(), $outra->id);
    expect($m->totalAgendamentos())->toBe(1);
});

it('renderiza o dashboard para o Dono com os indicadores', function () {
    semearCenario();
    $this->actingAs(usuarioComPapel('Dono', ['email' => 'dono@dash.test']), 'web');

    Livewire::test(Dashboard::class)
        ->assertSee('Faturamento')
        ->assertDontSee('Faturamento estimado') // agora é faturamento REAL (2D)
        ->assertSee('Ticket médio')
        ->assertSee('Comissão a pagar')
        ->assertSee('Agendamentos')
        ->assertSet('periodo', '30d');
});

it('redireciona o Profissional (sem ver_dashboard) para a agenda', function () {
    $this->actingAs(usuarioComPapel('Profissional', ['email' => 'prof@dash.test', 'e_profissional' => true]), 'web');

    Livewire::test(Dashboard::class)
        ->assertRedirect(route('painel.agenda', ['tenant' => 'lojadash']));
});

it('redireciona a Recepção (sem ver_dashboard) para a agenda', function () {
    $this->actingAs(usuarioComPapel('Recepção', ['email' => 'recep@dash.test']), 'web');

    Livewire::test(Dashboard::class)
        ->assertRedirect(route('painel.agenda', ['tenant' => 'lojadash']));
});
