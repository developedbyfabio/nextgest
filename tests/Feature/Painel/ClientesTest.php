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

// ---- Filtros de faixa de última visita -----------------------------------

it('filtro de faixa de última visita combina com a recência', function () {
    $dono = usuarioComPapel('Dono', ['email' => 'dono@cli.test']);
    $this->actingAs($dono, 'web');

    $recente = clienteCli('Recente Costa');
    agendamentoCli($recente->id, 5, 'concluido');     // até 30 dias
    $sumido = clienteCli('Sumido Lima');
    agendamentoCli($sumido->id, 120, 'concluido');    // mais de 90 dias
    $nunca = clienteCli('Novato Souza');               // nenhum concluído

    Livewire::test(Index::class)
        ->set('visitaFiltro', 'ate30')
        ->assertSee('Recente Costa')
        ->assertDontSee('Sumido Lima')
        ->assertDontSee('Novato Souza')
        ->set('visitaFiltro', 'mais90')
        ->assertSee('Sumido Lima')
        ->assertDontSee('Recente Costa')
        ->set('visitaFiltro', 'nunca')
        ->assertSee('Novato Souza')
        ->assertDontSee('Recente Costa')
        ->assertDontSee('Sumido Lima');
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

// ---- Sem ações nesta fatia ------------------------------------------------

it('não expõe ações (editar / resetar senha / WhatsApp) nesta fatia', function () {
    $dono = usuarioComPapel('Dono', ['email' => 'dono@cli.test']);
    $this->actingAs($dono, 'web');
    clienteCli('Cliente Qualquer');

    Livewire::test(Index::class)
        ->assertDontSee('Editar')
        ->assertDontSee('Resetar')
        ->assertDontSee('Enviar WhatsApp');
});
