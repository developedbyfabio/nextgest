<?php

declare(strict_types=1);

use App\Livewire\Painel\Clientes\Index;
use App\Models\Agendamento;
use App\Models\AssinaturaClube;
use App\Models\BeneficiarioAssinatura;
use App\Models\Cliente;
use App\Models\PlanoClube;
use App\Models\Unidade;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = criarTenant('clientes');
    tenancy()->initialize($this->tenant);
    $this->unidade = Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    $this->prof = usuarioComPapel('Profissional', ['email' => 'prof@cli.test', 'e_profissional' => true]);
    Carbon::setTestNow(Carbon::create(2026, 6, 28, 12, 0, 0));
});

afterEach(fn () => Carbon::setTestNow());

function clienteCli(string $nome, array $attrs = []): Cliente
{
    return Cliente::create(array_merge([
        'nome' => $nome,
        'telefone' => (string) random_int(11900000000, 11999999999),
    ], $attrs));
}

/** Cria um agendamento do cliente com status/data definidos. */
function agendamentoCli(int $clienteId, int $diasAtras, string $status = 'concluido'): Agendamento
{
    $inicio = Carbon::now()->subDays($diasAtras);

    return Agendamento::create([
        'unidade_id' => test()->unidade->id,
        'cliente_id' => $clienteId,
        'profissional_id' => test()->prof->id,
        'data_hora_inicio' => $inicio,
        'data_hora_fim' => $inicio->copy()->addHour(),
        'status' => $status,
        'origem' => 'equipe',
    ]);
}

function ligarClubeCli(): void
{
    $t = test()->tenant;
    $t->recursos = ['clube'];
    $t->save();
}

// ---- Gate por permissão (ver_clientes) -----------------------------------

it('gate: Dono, Gerente e Recepção abrem 200; Profissional leva 403', function () {
    $dono = usuarioComPapel('Dono', ['email' => 'dono@cli.test']);
    $gerente = usuarioComPapel('Gerente', ['email' => 'ger@cli.test']);
    $recepcao = usuarioComPapel('Recepção', ['email' => 'rec@cli.test']);

    $this->actingAs($dono, 'web')->get('/clientes/painel/clientes')->assertOk()->assertSee('Clientes');
    $this->actingAs($gerente, 'web')->get('/clientes/painel/clientes')->assertOk();
    $this->actingAs($recepcao, 'web')->get('/clientes/painel/clientes')->assertOk();
    $this->actingAs($this->prof, 'web')->get('/clientes/painel/clientes')->assertForbidden();
});

it('menu de Gestão mostra o item Clientes para quem tem a permissão', function () {
    $dono = usuarioComPapel('Dono', ['email' => 'dono@cli.test']);
    $this->actingAs($dono, 'web')->get('/clientes/painel')->assertOk()->assertSee('Clientes');
});

// ---- Última visita = último atendimento CONCLUÍDO ------------------------

it('última visita usa o último concluído; ignora cancelado e mostra "Nunca veio" sem concluído', function () {
    $dono = usuarioComPapel('Dono', ['email' => 'dono@cli.test']);
    $this->actingAs($dono, 'web');

    $assiduo = clienteCli('Assiduo Silva');
    agendamentoCli($assiduo->id, 10, 'concluido');
    agendamentoCli($assiduo->id, 3, 'concluido');   // mais recente → última visita
    agendamentoCli($assiduo->id, 1, 'cancelado');   // NÃO conta

    $soCancelado = clienteCli('So Cancelado');
    agendamentoCli($soCancelado->id, 2, 'cancelado');

    Livewire::test(Index::class)
        ->assertSee('Assiduo Silva')
        ->assertSee('há 3 dias')   // último CONCLUÍDO, não o cancelado de ontem
        ->assertSee('So Cancelado')
        ->assertSee('Nunca veio');
});

// ---- Busca por nome -------------------------------------------------------

it('busca por nome filtra server-side', function () {
    $dono = usuarioComPapel('Dono', ['email' => 'dono@cli.test']);
    $this->actingAs($dono, 'web');

    clienteCli('Maria Aparecida');
    clienteCli('Joao Pereira');

    Livewire::test(Index::class)
        ->assertSee('Maria Aparecida')
        ->assertSee('Joao Pereira')
        ->set('busca', 'maria')
        ->assertSee('Maria Aparecida')
        ->assertDontSee('Joao Pereira');
});

// ---- Filtro de inatividade (faixas cumulativas, D89) ----------------------

it('filtro de inatividade é cumulativo (+X = sem visita há mais de X)', function () {
    $dono = usuarioComPapel('Dono', ['email' => 'dono@cli.test']);
    $this->actingAs($dono, 'web');

    $recente = clienteCli('Recente Costa');
    agendamentoCli($recente->id, 5, 'concluido');      // veio há 5 dias (ativo)
    $sumido = clienteCli('Sumido Lima');
    agendamentoCli($sumido->id, 120, 'concluido');     // há 120 dias
    $antigo = clienteCli('Antigo Reis');
    agendamentoCli($antigo->id, 400, 'concluido');     // há 400 dias
    $nunca = clienteCli('Novato Souza');                // nunca veio

    Livewire::test(Index::class)
        // +90 dias: pega o de 120 e o de 400 (cumulativo); não pega o recente nem "nunca".
        ->set('visitaFiltro', 'mais90')
        ->assertSee('Sumido Lima')
        ->assertSee('Antigo Reis')
        ->assertDontSee('Recente Costa')
        ->assertDontSee('Novato Souza')
        // +1 ano: só o de 400 dias.
        ->set('visitaFiltro', 'mais365')
        ->assertSee('Antigo Reis')
        ->assertDontSee('Sumido Lima')
        ->assertDontSee('Recente Costa')
        // nunca: só quem não tem concluído.
        ->set('visitaFiltro', 'nunca')
        ->assertSee('Novato Souza')
        ->assertDontSee('Antigo Reis');
});

// ---- Cards de resumo (D89) ------------------------------------------------

it('cards: total e faixas de inatividade vêm do agregado', function () {
    $dono = usuarioComPapel('Dono', ['email' => 'dono@cli.test']);
    $this->actingAs($dono, 'web');

    $a = clienteCli('Ativo'); agendamentoCli($a->id, 5, 'concluido');
    $b = clienteCli('Sumido 120'); agendamentoCli($b->id, 120, 'concluido');
    $c = clienteCli('Antigo 400'); agendamentoCli($c->id, 400, 'concluido');
    clienteCli('Nunca Veio');

    Livewire::test(Index::class)->assertViewHas('resumo', function ($resumo) {
        return $resumo['total'] === 4
            && $resumo['bandas']['mais15'] === 2   // 120 e 400 (>15)
            && $resumo['bandas']['mais90'] === 2   // 120 e 400 (>90)
            && $resumo['bandas']['mais365'] === 1; // só 400 (>365)
    });
});

it('coerência card↔tabela: clicar na faixa filtra para os mesmos clientes; reclicar limpa', function () {
    $dono = usuarioComPapel('Dono', ['email' => 'dono@cli.test']);
    $this->actingAs($dono, 'web');

    $a = clienteCli('Ativo Joao'); agendamentoCli($a->id, 5, 'concluido');
    $b = clienteCli('Sumido Maria'); agendamentoCli($b->id, 120, 'concluido');
    $c = clienteCli('Antigo Pedro'); agendamentoCli($c->id, 400, 'concluido');

    Livewire::test(Index::class)
        ->call('selecionarFaixa', 'mais90')
        ->assertSet('visitaFiltro', 'mais90')
        ->assertViewHas('resumo', fn ($r) => $r['bandas']['mais90'] === 2)
        ->assertSee('Sumido Maria')
        ->assertSee('Antigo Pedro')
        ->assertDontSee('Ativo Joao')
        ->call('selecionarFaixa', 'mais90') // reclicar = limpar
        ->assertSet('visitaFiltro', 'todos')
        ->assertSee('Ativo Joao');
});

it('card Clube: assinantes vs avulsos somam o total', function () {
    ligarClubeCli();
    $dono = usuarioComPapel('Dono', ['email' => 'dono@cli.test']);
    $this->actingAs($dono, 'web');

    $assinante = clienteCli('Assina');
    clienteCli('Avulso 1');
    clienteCli('Avulso 2');
    $plano = PlanoClube::create(['nome' => 'VIP', 'preco_mensal' => 50, 'ativo' => true, 'ilimitado' => true, 'capacidade' => 1]);
    $assin = AssinaturaClube::create(['cliente_id' => $assinante->id, 'plano_id' => $plano->id, 'status' => 'ativa', 'preco_contratado' => 50, 'data_inicio' => Carbon::today()]);
    BeneficiarioAssinatura::create(['assinatura_id' => $assin->id, 'cliente_id' => $assinante->id, 'titular' => true]);

    Livewire::test(Index::class)->assertViewHas('resumo', function ($resumo) {
        return $resumo['total'] === 3 && $resumo['assinantes'] === 1 && $resumo['avulsos'] === 2;
    });
});

// ---- Selo e filtro do Clube (titular OU dependente) ----------------------

it('selo Clube marca titular E dependente com conta; filtro separa assinantes de normais', function () {
    ligarClubeCli();
    $dono = usuarioComPapel('Dono', ['email' => 'dono@cli.test']);
    $this->actingAs($dono, 'web');

    $titular = clienteCli('Titular Dias');
    $dependente = clienteCli('Dependente Dias');
    $normal = clienteCli('Avulso Reis');

    $plano = PlanoClube::create(['nome' => 'VIP', 'preco_mensal' => 99.90, 'ativo' => true, 'ilimitado' => true, 'capacidade' => 2]);
    $assinatura = AssinaturaClube::create([
        'cliente_id' => $titular->id, 'plano_id' => $plano->id, 'status' => 'ativa',
        'preco_contratado' => 99.90, 'data_inicio' => Carbon::today(),
    ]);
    BeneficiarioAssinatura::create(['assinatura_id' => $assinatura->id, 'cliente_id' => $titular->id, 'titular' => true]);
    BeneficiarioAssinatura::create(['assinatura_id' => $assinatura->id, 'cliente_id' => $dependente->id, 'titular' => false]);

    Livewire::test(Index::class)
        ->set('clubeFiltro', 'assinantes')
        ->assertSee('Titular Dias')
        ->assertSee('Dependente Dias')
        ->assertDontSee('Avulso Reis')
        ->set('clubeFiltro', 'normais')
        ->assertSee('Avulso Reis')
        ->assertDontSee('Titular Dias')
        ->assertDontSee('Dependente Dias');
});

it('assinatura CANCELADA não conta como assinante', function () {
    ligarClubeCli();
    $dono = usuarioComPapel('Dono', ['email' => 'dono@cli.test']);
    $this->actingAs($dono, 'web');

    $exAssinante = clienteCli('Ex Assinante');
    $plano = PlanoClube::create(['nome' => 'VIP', 'preco_mensal' => 99.90, 'ativo' => true, 'ilimitado' => true, 'capacidade' => 1]);
    $assinatura = AssinaturaClube::create([
        'cliente_id' => $exAssinante->id, 'plano_id' => $plano->id, 'status' => 'cancelada',
        'preco_contratado' => 99.90, 'data_inicio' => Carbon::today()->subMonths(3),
    ]);
    BeneficiarioAssinatura::create(['assinatura_id' => $assinatura->id, 'cliente_id' => $exAssinante->id, 'titular' => true]);

    Livewire::test(Index::class)
        ->set('clubeFiltro', 'assinantes')
        ->assertDontSee('Ex Assinante')
        ->set('clubeFiltro', 'normais')
        ->assertSee('Ex Assinante');
});

it('sem o recurso Clube, a coluna/filtro de Clube não aparece', function () {
    $dono = usuarioComPapel('Dono', ['email' => 'dono@cli.test']);
    $this->actingAs($dono, 'web');
    clienteCli('Fulano Teste');

    Livewire::test(Index::class)
        ->assertSee('Fulano Teste')
        ->assertDontSee('Assinantes');   // opção de filtro do Clube
});

// ---- Detalhe (só leitura) -------------------------------------------------

it('detalhe abre os últimos agendamentos do cliente e fecha no toggle', function () {
    $dono = usuarioComPapel('Dono', ['email' => 'dono@cli.test']);
    $this->actingAs($dono, 'web');

    $cli = clienteCli('Cliente Detalhe');
    agendamentoCli($cli->id, 4, 'concluido');

    Livewire::test(Index::class)
        ->assertDontSee('Últimos agendamentos')
        ->call('alternarDetalhe', $cli->id)
        ->assertSet('clienteAbertoId', $cli->id)
        ->assertSee('Últimos agendamentos')
        ->assertSee('Concluído')
        ->call('alternarDetalhe', $cli->id)
        ->assertSet('clienteAbertoId', null);
});

// ---- Isolamento por tenant ------------------------------------------------

it('isolamento: só lista clientes do tenant atual', function () {
    $outro = criarTenant('clientes2');
    tenancy()->initialize($outro);
    Unidade::create(['nome' => 'Outra', 'ativo' => true]);
    clienteCli('Beta Outro Tenant');

    tenancy()->initialize($this->tenant);
    clienteCli('Alpha Deste Tenant');

    $dono = usuarioComPapel('Dono', ['email' => 'dono@cli.test']);
    $this->actingAs($dono, 'web');

    Livewire::test(Index::class)
        ->assertSee('Alpha Deste Tenant')
        ->assertDontSee('Beta Outro Tenant');
});

// ---- Ações sensíveis ainda NÃO entram (reset de senha = Fatia 3; campanha depois) -----

it('não expõe reset de senha nem campanha (fatias seguintes)', function () {
    $dono = usuarioComPapel('Dono', ['email' => 'dono@cli.test']);
    $this->actingAs($dono, 'web');
    clienteCli('Cliente Qualquer');

    Livewire::test(Index::class)
        ->assertDontSee('Resetar senha')
        ->assertDontSee('Reativar inativos');
});
