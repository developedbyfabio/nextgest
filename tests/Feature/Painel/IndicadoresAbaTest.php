<?php

declare(strict_types=1);

use App\Livewire\Painel\Indicadores;
use App\Models\Cliente;
use App\Models\Unidade;
use App\Models\Venda;
use App\Services\Painel\IndicadoresClientes;
use Carbon\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    tenancy()->initialize(criarTenant('indicaba'));
    $this->unidade = Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    Carbon::setTestNow(Carbon::create(2026, 6, 22, 12, 0, 0));
});

afterEach(fn () => Carbon::setTestNow());

function clienteAba(string $nome): Cliente
{
    return Cliente::create(['nome' => $nome, 'telefone' => (string) random_int(1, 999999999)]);
}

function vendaPagaAba(int $unidadeId, int $clienteId, int $diasAtras, float $valor = 50.0): void
{
    $data = Carbon::today()->subDays($diasAtras);
    Venda::create(['unidade_id' => $unidadeId, 'cliente_id' => $clienteId, 'status' => 'paga',
        'valor_bruto' => $valor, 'desconto' => 0, 'valor_total' => $valor, 'data' => $data]);
}

it('permissão: Dono e Gerente abrem 200; Recepção e Profissional levam 403 (cru)', function () {
    $dono = usuarioComPapel('Dono', ['email' => 'dono@indicaba.test']);
    $gerente = usuarioComPapel('Gerente', ['email' => 'ger@indicaba.test']);
    $recepcao = usuarioComPapel('Recepção', ['email' => 'rec@indicaba.test']);
    $prof = usuarioComPapel('Profissional', ['email' => 'prof@indicaba.test', 'e_profissional' => true]);

    $this->actingAs($dono, 'web')->get('/indicaba/painel/indicadores')->assertOk()->assertSee('Indicadores');
    $this->actingAs($gerente, 'web')->get('/indicaba/painel/indicadores')->assertOk();
    $this->actingAs($recepcao, 'web')->get('/indicaba/painel/indicadores')->assertForbidden();
    $this->actingAs($prof, 'web')->get('/indicaba/painel/indicadores')->assertForbidden();
});

it('os 4 cards refletem o serviço da Fase I (mesmos números)', function () {
    // Sumido: 3 visitas, intervalo 10d, última há 70d → 1 em risco; bucket "sempre".
    $a = clienteAba('Sumido');
    foreach ([90, 80, 70] as $d) {
        vendaPagaAba($this->unidade->id, $a->id, $d);
    }
    // Cliente com 1 visita recente → "novos".
    $b = clienteAba('Novato');
    vendaPagaAba($this->unidade->id, $b->id, 2, 100);

    $dono = usuarioComPapel('Dono', ['email' => 'dono@indicaba.test']);
    $this->actingAs($dono, 'web');

    // Números esperados direto do serviço (a aba não recalcula — só exibe os mesmos).
    $svc = new IndicadoresClientes;
    $ticketEsperado = number_format($svc->ticketMedio(Carbon::today()->subDays(29)->startOfDay(), Carbon::today()->endOfDay(), null), 2, ',', '.');

    Livewire::test(Indicadores::class)
        ->assertSee('Clientes sumindo')
        ->assertSee('Ticket médio')
        ->assertSee('R$ '.$ticketEsperado)
        ->assertSee('Vai sempre')
        ->assertSee('Novos / poucos dados')
        // bucket "sempre" = 1 (o sumido), "novos" = 1 (o novato): aparecem na lista de frequência
        ->assertSeeInOrder(['Vai sempre', '1']);
});

it('drill-in de risco: abre a lista ordenada pelo mais atrasado, com o nome do cliente', function () {
    $a = clienteAba('Cliente Sumido');
    foreach ([90, 80, 70] as $d) {
        vendaPagaAba($this->unidade->id, $a->id, $d);
    }

    $dono = usuarioComPapel('Dono', ['email' => 'dono@indicaba.test']);
    $this->actingAs($dono, 'web');

    Livewire::test(Indicadores::class)
        ->assertDontSee('Cliente Sumido')   // lista fechada
        ->call('abrirRisco')
        ->assertSet('mostrarRisco', true)
        ->assertSee('Cliente Sumido')        // nome resolvido (whereIn da página)
        ->assertSee('Dias sem vir')
        ->call('fecharDrill')
        ->assertSet('mostrarRisco', false)
        ->assertDontSee('Cliente Sumido');
});

it('drill-in por bucket de frequência lista quem está nele', function () {
    $a = clienteAba('Regular Fulano');
    foreach ([90, 60, 30] as $d) { // intervalo 30 → "regular"
        vendaPagaAba($this->unidade->id, $a->id, $d);
    }

    $dono = usuarioComPapel('Dono', ['email' => 'dono@indicaba.test']);
    $this->actingAs($dono, 'web');

    Livewire::test(Indicadores::class)
        ->call('abrirBucket', 'regular')
        ->assertSet('bucketAberto', 'regular')
        ->assertSee('Regular Fulano')
        ->call('abrirBucket', 'invalido')   // ignorado
        ->assertSet('bucketAberto', 'regular');
});

it('filtros de período alteram o ticket médio (curto vs longo)', function () {
    $cli = clienteAba('Cli');
    vendaPagaAba($this->unidade->id, $cli->id, 3, 50);   // dentro de 7d
    vendaPagaAba($this->unidade->id, $cli->id, 60, 100); // só dentro de 90d

    $dono = usuarioComPapel('Dono', ['email' => 'dono@indicaba.test']);
    $this->actingAs($dono, 'web');

    Livewire::test(Indicadores::class)
        ->set('periodo', '7d')
        ->assertSee('R$ 50,00')              // só a venda recente
        ->set('periodo', '90d')
        ->assertSee('R$ 75,00');             // média (50+100)/2
});

it('estado vazio: tenant sem dados não quebra', function () {
    $dono = usuarioComPapel('Dono', ['email' => 'dono@indicaba.test']);
    $this->actingAs($dono, 'web');

    Livewire::test(Indicadores::class)
        ->assertOk()
        ->assertSee('Indicadores')
        ->assertSee('R$ 0,00')               // ticket zero
        ->call('abrirRisco')
        ->assertSee('Nenhum cliente aqui');  // drill-in vazio amigável
});
