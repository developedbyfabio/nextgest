<?php

declare(strict_types=1);

use App\Livewire\Painel\Clube\Index;
use App\Models\AssinaturaClube;
use App\Models\Cliente;
use App\Models\PlanoBeneficio;
use App\Models\PlanoClube;
use App\Models\Produto;
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

function ligarClube(): void
{
    $t = test()->tenant;
    $t->recursos = ['clube'];
    $t->save();
}

/** Plano de cobertura. $servicos = ids cobertos; $attrs sobrescreve limite/dias/capacidade. */
function planoClube(array $attrs = [], array $servicos = []): PlanoClube
{
    $plano = PlanoClube::create(array_merge([
        'nome' => 'VIP', 'preco_mensal' => 99.90, 'ativo' => true,
        'ilimitado' => true, 'capacidade' => 1,
    ], $attrs));

    foreach ($servicos as $sid) {
        PlanoBeneficio::create(['plano_id' => $plano->id, 'servico_id' => $sid, 'tipo' => 'ilimitado']);
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

// ---- Indicadores (eventos) ------------------------------------------------

it('indicadores: 3 novas e 1 cancelada → ativos=2, novos=3, cancelados=1', function () {
    $plano = planoClube();
    $svc = app(Assinaturas::class);
    $clientes = collect(range(1, 3))->map(fn ($i) => Cliente::create(['nome' => "C{$i}", 'telefone' => (string) $i]));

    $assinaturas = $clientes->map(fn ($c) => $svc->criar($c->id, $plano, AssinaturaClube::STATUS_ATIVA));
    $svc->alterarStatus($assinaturas->first(), AssinaturaClube::STATUS_CANCELADA);

    $ind = new IndicadoresClube;
    expect($ind->assinantesAtivos())->toBe(2)
        ->and($ind->novosNoMes())->toBe(3)
        ->and($ind->canceladosNoMes())->toBe(1);

    $junho = $ind->evolucao(6)->firstWhere('mes', '2026-06');
    expect($junho['entradas'])->toBe(3)->and($junho['saidas'])->toBe(1);
});

it('toda mudança de status gera um evento', function () {
    $plano = planoClube();
    $cli = Cliente::create(['nome' => 'C', 'telefone' => '1']);
    $svc = app(Assinaturas::class);

    $a = $svc->criar($cli->id, $plano, AssinaturaClube::STATUS_ATIVA);
    expect($a->eventos()->count())->toBe(1);

    $svc->alterarStatus($a, AssinaturaClube::STATUS_INADIMPLENTE);
    $svc->alterarStatus($a, AssinaturaClube::STATUS_CANCELADA);
    expect($a->fresh()->eventos()->count())->toBe(3)
        ->and($a->fresh()->status)->toBe('cancelada');

    $svc->alterarStatus($a->fresh(), AssinaturaClube::STATUS_CANCELADA); // no-op
    expect($a->fresh()->eventos()->count())->toBe(3);
});

// ---- Cobertura na comanda -------------------------------------------------

it('cobertura: serviço coberto no dia permitido sai 100%; produto e serviço fora são cobrados', function () {
    $uni = Unidade::create(['nome' => 'M', 'ativo' => true]);
    $corte = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 50, 'ativo' => true]);
    $barba = Servico::create(['nome' => 'Barba', 'duracao_minutos' => 20, 'preco' => 30, 'ativo' => true]);
    $produto = Produto::create(['nome' => 'Pomada', 'preco_venda' => 40, 'preco_custo' => 10, 'controla_estoque' => false, 'ativo' => true]);

    $hoje = Carbon::today()->dayOfWeek;
    $plano = planoClube(['dias_semana' => [$hoje]], [$corte->id]); // cobre corte, hoje permitido

    $cli = Cliente::create(['nome' => 'C', 'telefone' => '1']);
    $svc = app(Assinaturas::class);
    $svc->criar($cli->id, $plano, AssinaturaClube::STATUS_ATIVA);

    $comanda = app(Comanda::class);
    $venda = $comanda->abrir($uni->id, $cli->id);
    $comanda->adicionarServico($venda, $corte); // 50 (coberto)
    $comanda->adicionarServico($venda, $barba);  // 30 (fora do plano)
    $comanda->adicionarProduto($venda, $produto, 1); // 40 (produto)

    expect((float) $venda->fresh()->valor_total)->toBe(120.0);

    $cobertos = app(BeneficioClube::class)->aplicarCobertura($venda->fresh());

    expect($cobertos)->toBe(1)
        ->and((float) $venda->fresh()->valor_total)->toBe(70.0); // barba 30 + pomada 40
});

it('cobertura NÃO aplica em dia não permitido', function () {
    $uni = Unidade::create(['nome' => 'M', 'ativo' => true]);
    $corte = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 50, 'ativo' => true]);
    $outroDia = (Carbon::today()->dayOfWeek + 1) % 7;
    $plano = planoClube(['dias_semana' => [$outroDia]], [$corte->id]); // hoje NÃO permitido

    $cli = Cliente::create(['nome' => 'C', 'telefone' => '1']);
    app(Assinaturas::class)->criar($cli->id, $plano, AssinaturaClube::STATUS_ATIVA);

    $comanda = app(Comanda::class);
    $venda = $comanda->abrir($uni->id, $cli->id);
    $comanda->adicionarServico($venda, $corte);

    expect(app(BeneficioClube::class)->aplicarCobertura($venda->fresh()))->toBe(0)
        ->and((float) $venda->fresh()->valor_total)->toBe(50.0); // cobrado
});

it('teto: dentro do limite cobre; além do teto não cobre; inadimplente não recebe', function () {
    $uni = Unidade::create(['nome' => 'M', 'ativo' => true]);
    $corte = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 50, 'ativo' => true]);
    $hoje = Carbon::today()->dayOfWeek;
    $plano = planoClube(['ilimitado' => false, 'limite_usos' => 1, 'dias_semana' => [$hoje]], [$corte->id]);

    $cli = Cliente::create(['nome' => 'C', 'telefone' => '1']);
    $svc = app(Assinaturas::class);
    $a = $svc->criar($cli->id, $plano, AssinaturaClube::STATUS_ATIVA);
    $ben = app(BeneficioClube::class);
    $comanda = app(Comanda::class);

    // 1ª comanda: dentro do teto → cobre.
    $v1 = $comanda->abrir($uni->id, $cli->id);
    $comanda->adicionarServico($v1, $corte);
    expect($ben->aplicarCobertura($v1->fresh()))->toBe(1)
        ->and((float) $v1->fresh()->valor_total)->toBe(0.0);

    // 2ª comanda no mesmo mês: teto (1) esgotado → não cobre.
    $v2 = $comanda->abrir($uni->id, $cli->id);
    $comanda->adicionarServico($v2, $corte);
    expect($ben->aplicarCobertura($v2->fresh()))->toBe(0)
        ->and((float) $v2->fresh()->valor_total)->toBe(50.0);

    // Inadimplente não recebe.
    $svc->alterarStatus($a, AssinaturaClube::STATUS_INADIMPLENTE);
    $v3 = $comanda->abrir($uni->id, $cli->id);
    $comanda->adicionarServico($v3, $corte);
    expect($ben->aplicarCobertura($v3->fresh()))->toBe(0);
});

it('capacidade: não permite mais beneficiários que a capacidade', function () {
    $plano = planoClube(['capacidade' => 2]);
    $titular = Cliente::create(['nome' => 'Titular', 'telefone' => '1']);
    $filho = Cliente::create(['nome' => 'Filho', 'telefone' => '2']);
    $svc = app(Assinaturas::class);

    $a = $svc->criar($titular->id, $plano, AssinaturaClube::STATUS_ATIVA);
    expect($a->beneficiarios()->count())->toBe(1); // titular auto

    $svc->adicionarBeneficiario($a, $filho->id); // 2/2
    expect($a->beneficiarios()->count())->toBe(2);

    expect(fn () => $svc->adicionarBeneficiario($a, null, 'Extra'))
        ->toThrow(RuntimeException::class); // capacidade atingida
});

it('beneficiário com conta usa a assinatura compartilhada (consumo conta contra a assinatura)', function () {
    $uni = Unidade::create(['nome' => 'M', 'ativo' => true]);
    $corte = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 50, 'ativo' => true]);
    $hoje = Carbon::today()->dayOfWeek;
    $plano = planoClube(['ilimitado' => false, 'limite_usos' => 8, 'dias_semana' => [$hoje], 'capacidade' => 2], [$corte->id]);

    $titular = Cliente::create(['nome' => 'Titular', 'telefone' => '1']);
    $filho = Cliente::create(['nome' => 'Filho', 'telefone' => '2']);
    $svc = app(Assinaturas::class);
    $a = $svc->criar($titular->id, $plano, AssinaturaClube::STATUS_ATIVA);
    $svc->adicionarBeneficiario($a, $filho->id);
    $ben = app(BeneficioClube::class);
    $comanda = app(Comanda::class);

    // Comanda do FILHO (beneficiário com conta) → cobre via a assinatura do titular.
    $venda = $comanda->abrir($uni->id, $filho->id);
    $comanda->adicionarServico($venda, $corte);
    expect($ben->aplicarCobertura($venda->fresh()))->toBe(1)
        ->and((float) $venda->fresh()->valor_total)->toBe(0.0);

    // Consumo contou contra a assinatura (compartilhado): 1 uso, saldo 7.
    expect($ben->usosNoPeriodo($a, '2026-06'))->toBe(1)
        ->and($ben->saldoRestante($a, '2026-06'))->toBe(7);
});

// ---- Isolamento + aba -----------------------------------------------------

it('assinaturas de um tenant não vazam para outro', function () {
    $plano = planoClube();
    $cli = Cliente::create(['nome' => 'C', 'telefone' => '1']);
    app(Assinaturas::class)->criar($cli->id, $plano, AssinaturaClube::STATUS_ATIVA);
    expect((new IndicadoresClube)->assinantesAtivos())->toBe(1);
    tenancy()->end();

    tenancy()->initialize(criarTenant('clube2'));
    expect((new IndicadoresClube)->assinantesAtivos())->toBe(0);
    tenancy()->end();
});

it('a aba renderiza para o Dono (cards) e exporta CSV', function () {
    ligarClube();
    $plano = planoClube();
    $cli = Cliente::create(['nome' => 'Maria Cliente', 'telefone' => '1']);
    app(Assinaturas::class)->criar($cli->id, $plano, AssinaturaClube::STATUS_ATIVA);

    $dono = usuarioComPapel('Dono', ['email' => 'dono@clube.test']);
    $this->actingAs($dono, 'web');

    Livewire::test(Index::class)
        ->assertOk()
        ->assertSee('Assinantes ativos')
        ->set('aba', 'relatorios')
        ->call('exportarCsv')
        ->assertFileDownloaded();
});

it('a aba exige flag (404 no mount)', function () {
    $dono = usuarioComPapel('Dono', ['email' => 'dono@clube.test']);
    $this->actingAs($dono, 'web');
    Livewire::test(Index::class)->assertStatus(404);
});
