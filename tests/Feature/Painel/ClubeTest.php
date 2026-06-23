<?php

declare(strict_types=1);

use App\Livewire\Painel\Clube\Index;
use App\Models\AssinaturaClube;
use App\Models\Cliente;
use App\Models\PlanoClube;
use App\Models\PlanoDesconto;
use App\Models\Servico;
use App\Models\Unidade;
use App\Services\Clube\Assinaturas;
use App\Services\Clube\BeneficioClube;
use App\Services\Clube\IndicadoresClube;
use App\Services\Venda\Comanda;
use Carbon\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = criarTenant('clube');
    tenancy()->initialize($this->tenant);
    Carbon::setTestNow(Carbon::create(2026, 6, 15, 12, 0, 0));
});

afterEach(fn () => Carbon::setTestNow());

/** Liga a flag `clube` para o tenant (no registro central). */
function ligarClube(): void
{
    $t = test()->tenant;
    $t->recursos = ['clube'];
    $t->save();
}

function planoComDesconto(float $pct = 10): PlanoClube
{
    $plano = PlanoClube::create(['nome' => 'VIP', 'preco_mensal' => 99.90, 'ativo' => true]);
    if ($pct > 0) {
        PlanoDesconto::create(['plano_id' => $plano->id, 'aplica_em' => 'todos', 'tipo_desconto' => 'percentual', 'valor' => $pct]);
    }

    return $plano;
}

// ---- Gate: flag + permissão ----------------------------------------------

it('flag OFF: rota do clube dá 404 e o menu não mostra o item', function () {
    $dono = usuarioComPapel('Dono', ['email' => 'dono@clube.test']);

    $this->actingAs($dono, 'web')->get('/clube/painel/clube')->assertNotFound();
    $this->actingAs($dono, 'web')->get('/clube/painel')->assertOk()->assertDontSee('Clube de Assinatura');
});

it('flag ON + Dono: abre 200 e o menu mostra o item', function () {
    ligarClube();
    $dono = usuarioComPapel('Dono', ['email' => 'dono@clube.test']);

    $this->actingAs($dono, 'web')->get('/clube/painel/clube')->assertOk()->assertSee('Clube de Assinatura');
    $this->actingAs($dono, 'web')->get('/clube/painel')->assertOk()->assertSee('Clube de Assinatura');
});

it('flag ON + sem permissão (Recepção): rota dá 403 cru', function () {
    ligarClube();
    $recepcao = usuarioComPapel('Recepção', ['email' => 'rec@clube.test']);

    $this->actingAs($recepcao, 'web')->get('/clube/painel/clube')->assertForbidden();
});

// ---- Indicadores leem os eventos corretamente -----------------------------

it('indicadores: 3 novas e 1 cancelada → ativos=2, novos=3, cancelados=1', function () {
    $plano = planoComDesconto();
    $svc = app(Assinaturas::class);
    $clientes = collect(range(1, 3))->map(fn ($i) => Cliente::create(['nome' => "C{$i}", 'telefone' => (string) $i]));

    $assinaturas = $clientes->map(fn ($c) => $svc->criar($c->id, $plano, AssinaturaClube::STATUS_ATIVA));
    $svc->alterarStatus($assinaturas->first(), AssinaturaClube::STATUS_CANCELADA);

    $ind = new IndicadoresClube;
    expect($ind->assinantesAtivos())->toBe(2)
        ->and($ind->novosNoMes())->toBe(3)
        ->and($ind->canceladosNoMes())->toBe(1)
        ->and($ind->inadimplentes()->total())->toBe(0);

    // Evolução do mês corrente: 3 entradas, 1 saída.
    $junho = $ind->evolucao(6)->firstWhere('mes', '2026-06');
    expect($junho['entradas'])->toBe(3)->and($junho['saidas'])->toBe(1);
});

it('toda mudança de status gera um evento', function () {
    $plano = planoComDesconto(0);
    $cli = Cliente::create(['nome' => 'C', 'telefone' => '1']);
    $svc = app(Assinaturas::class);

    $a = $svc->criar($cli->id, $plano, AssinaturaClube::STATUS_ATIVA);
    expect($a->eventos()->count())->toBe(1); // criada

    $svc->alterarStatus($a, AssinaturaClube::STATUS_INADIMPLENTE);
    $svc->alterarStatus($a, AssinaturaClube::STATUS_CANCELADA);
    expect($a->fresh()->eventos()->count())->toBe(3) // + pagamento_falhou + cancelada
        ->and($a->fresh()->status)->toBe('cancelada')
        ->and($a->fresh()->data_fim)->not->toBeNull();

    // Status igual ao atual → não duplica evento.
    $svc->alterarStatus($a->fresh(), AssinaturaClube::STATUS_CANCELADA);
    expect($a->fresh()->eventos()->count())->toBe(3);
});

// ---- Benefício só para assinante ATIVO ------------------------------------

it('benefício (desconto %) só vale para assinante ativo', function () {
    $plano = planoComDesconto(10);
    $cli = Cliente::create(['nome' => 'C', 'telefone' => '1']);
    $svc = app(Assinaturas::class);
    $beneficio = app(BeneficioClube::class);

    $a = $svc->criar($cli->id, $plano, AssinaturaClube::STATUS_ATIVA);
    expect($beneficio->percentualDoCliente($cli->id))->toBe(10.0);

    // Aplica numa comanda: 10% de 60 = 6 → total 54.
    $unidade = Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    $servico = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 60, 'ativo' => true]);
    $comanda = app(Comanda::class);
    $venda = $comanda->abrir($unidade->id, $cli->id);
    $comanda->adicionarServico($venda, $servico);
    expect($beneficio->aplicarNaComanda($venda->fresh()))->toBe(6.0)
        ->and((float) $venda->fresh()->valor_total)->toBe(54.0);

    // Inadimplente não recebe.
    $svc->alterarStatus($a, AssinaturaClube::STATUS_INADIMPLENTE);
    expect($beneficio->percentualDoCliente($cli->id))->toBeNull();
});

// ---- Isolamento por tenant ------------------------------------------------

it('assinaturas de um tenant não vazam para outro', function () {
    $plano = planoComDesconto(0);
    $cli = Cliente::create(['nome' => 'C', 'telefone' => '1']);
    app(Assinaturas::class)->criar($cli->id, $plano, AssinaturaClube::STATUS_ATIVA);
    expect((new IndicadoresClube)->assinantesAtivos())->toBe(1);
    tenancy()->end();

    tenancy()->initialize(criarTenant('clube2'));
    expect((new IndicadoresClube)->assinantesAtivos())->toBe(0);
    tenancy()->end();
});

// ---- Aba + CSV ------------------------------------------------------------

it('a aba renderiza para o Dono (cards) e exporta CSV', function () {
    ligarClube();
    $plano = planoComDesconto();
    $cli = Cliente::create(['nome' => 'Maria Cliente', 'telefone' => '1']);
    app(Assinaturas::class)->criar($cli->id, $plano, AssinaturaClube::STATUS_ATIVA);

    $dono = usuarioComPapel('Dono', ['email' => 'dono@clube.test']);
    $this->actingAs($dono, 'web');

    Livewire::test(Index::class)
        ->assertOk()
        ->assertSee('Assinantes ativos')
        ->assertSee('Cancelamentos no mês')
        ->set('aba', 'relatorios')
        ->call('exportarCsv')
        ->assertFileDownloaded();
});

it('a aba bloqueia quem não pode (mount 403) e exige flag (404)', function () {
    // Sem flag → 404 já no mount.
    $dono = usuarioComPapel('Dono', ['email' => 'dono@clube.test']);
    $this->actingAs($dono, 'web');
    Livewire::test(Index::class)->assertStatus(404);
});
