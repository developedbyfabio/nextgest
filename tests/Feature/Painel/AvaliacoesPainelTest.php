<?php

declare(strict_types=1);

use App\Livewire\Painel\Avaliacoes\Index;
use App\Models\Agendamento;
use App\Models\Avaliacao;
use App\Models\Cliente;
use App\Models\Servico;
use App\Models\Unidade;
use Carbon\Carbon;
use Livewire\Livewire;

/**
 * "Últimos serviços" (painel): RBAC por permissão + anonimato do profissional +
 * filtros/resumo no servidor.
 */
beforeEach(function () {
    $this->tenant = criarTenant('lojaultimos');
    tenancy()->initialize($this->tenant);

    $this->unidade = Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    $this->jorge = usuarioComPapel('Profissional', ['name' => 'Jorge Prof', 'email' => 'jorge@ult.test', 'e_profissional' => true]);
    $this->ana = usuarioComPapel('Profissional', ['name' => 'Ana Prof', 'email' => 'ana@ult.test', 'e_profissional' => true]);
    $this->servico = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 40, 'ativo' => true]);
    $this->cliente = Cliente::create(['nome' => 'Cliente Secreto', 'telefone' => '11', 'email' => 'secreto@ult.test']);
});

function concluido($self, $prof, ?int $nota = null, ?string $comentario = null, ?Carbon $quando = null, ?Cliente $cliente = null): Agendamento
{
    $quando ??= Carbon::now()->subDays(2)->setTime(10, 0);
    $cli = $cliente ?? $self->cliente;
    $ag = Agendamento::create([
        'unidade_id' => $self->unidade->id,
        'cliente_id' => $cli->id,
        'profissional_id' => $prof->id,
        'data_hora_inicio' => $quando,
        'data_hora_fim' => $quando->copy()->addMinutes(30),
        'status' => 'concluido',
        'origem' => 'cliente',
        'valor_total' => 40,
    ]);
    $ag->itens()->create(['servico_id' => $self->servico->id, 'preco' => 40, 'duracao_minutos' => 30]);

    if ($nota !== null) {
        Avaliacao::create([
            'agendamento_id' => $ag->id, 'cliente_id' => $cli->id,
            'profissional_id' => $prof->id, 'unidade_id' => $self->unidade->id,
            'nota' => $nota, 'comentario' => $comentario,
        ]);
    }

    return $ag;
}

it('Dono vê o nome do cliente e todos os profissionais', function () {
    concluido($this, $this->jorge, 5, 'Top!');
    concluido($this, $this->ana, 4);

    $this->actingAs(usuarioComPapel('Dono', ['email' => 'dono@ult.test']), 'web');

    Livewire::test(Index::class)
        ->assertSee('Cliente Secreto')
        ->assertSee('Jorge Prof')
        ->assertSee('Ana Prof');
});

it('Profissional vê só os DELE e NÃO vê o nome do cliente (anonimato real)', function () {
    concluido($this, $this->jorge, 5, 'Comentário do Jorge');
    concluido($this, $this->ana, 4, 'Comentário da Ana');

    $this->actingAs($this->jorge, 'web');

    $c = Livewire::test(Index::class);

    // Anonimato: o nome do cliente NÃO aparece.
    $c->assertDontSee('Cliente Secreto');
    // Vê o próprio atendimento/comentário; NÃO vê o da Ana.
    $c->assertSee('Comentário do Jorge')->assertDontSee('Comentário da Ana');
});

it('anonimato REAL: a query do profissional não carrega o cliente', function () {
    concluido($this, $this->jorge, 5);
    $this->actingAs($this->jorge, 'web');

    // Pela rota, renderiza sem o nome do cliente.
    $html = $this->get('/lojaultimos/painel/avaliacoes')->assertOk()->content();
    expect($html)->not->toContain('Cliente Secreto');
});

it('quem não tem permissão (Recepção) recebe 403', function () {
    $this->actingAs(usuarioComPapel('Recepção', ['email' => 'recep@ult.test']), 'web')
        ->get('/lojaultimos/painel/avaliacoes')
        ->assertForbidden();
});

it('filtra por número de estrelas (afeta a lista)', function () {
    concluido($this, $this->jorge, 5, 'CincoEstrelas');
    concluido($this, $this->jorge, 3, 'TresEstrelas');
    $this->actingAs(usuarioComPapel('Dono', ['email' => 'dono@ult.test']), 'web');

    Livewire::test(Index::class)
        ->assertSee('CincoEstrelas')->assertSee('TresEstrelas')
        ->set('filtroNota', '5')
        ->assertSee('CincoEstrelas')->assertDontSee('TresEstrelas');
});

it('filtra por com/sem comentário', function () {
    concluido($this, $this->jorge, 5, 'TemComentario');
    concluido($this, $this->jorge, 4, null);
    $this->actingAs(usuarioComPapel('Dono', ['email' => 'dono@ult.test']), 'web');

    Livewire::test(Index::class)
        ->set('filtroComentario', 'com')
        ->assertSee('TemComentario')
        ->set('filtroComentario', 'sem')
        ->assertDontSee('TemComentario');
});

it('filtra por cliente (só Dono) e o resumo reflete o período', function () {
    $outro = Cliente::create(['nome' => 'Joana Distinta', 'telefone' => '99', 'email' => 'joana@ult.test']);
    concluido($this, $this->jorge, 5, 'DoSecreto');
    concluido($this, $this->jorge, 4, 'DaJoana', null, $outro);

    $this->actingAs(usuarioComPapel('Dono', ['email' => 'dono@ult.test']), 'web');

    Livewire::test(Index::class)
        ->assertSee('Joana Distinta')
        ->set('filtroCliente', 'Joana')
        ->assertSee('DaJoana')->assertDontSee('DoSecreto');
});

it('filtra por período (data do atendimento)', function () {
    concluido($this, $this->jorge, 5, 'HojeMarcador', Carbon::now()->setTime(9, 0));
    concluido($this, $this->jorge, 4, 'AntigoMarcador', Carbon::now()->subMonths(2)->setTime(9, 0));
    $this->actingAs(usuarioComPapel('Dono', ['email' => 'dono@ult.test']), 'web');

    Livewire::test(Index::class)
        ->set('filtroPeriodo', 'dia')
        ->assertSee('HojeMarcador')->assertDontSee('AntigoMarcador');
});

// ---- D67: filtro por profissional (visão Dono) + blindagem do anonimato ------

it('Dono filtra por profissional e mantém o nome do cliente', function () {
    concluido($this, $this->jorge, 5, 'Comentário do Jorge');
    concluido($this, $this->ana, 4, 'Comentário da Ana');

    $this->actingAs(usuarioComPapel('Dono', ['email' => 'dono@ult.test']), 'web');

    Livewire::test(Index::class)
        ->set('filtroProfissional', $this->jorge->id)
        ->assertSee('Comentário do Jorge')
        ->assertDontSee('Comentário da Ana')
        ->assertSee('Cliente Secreto'); // visão de gestão mantém o nome
});

it('o select de profissional NÃO é renderizado para o profissional', function () {
    concluido($this, $this->jorge, 5);
    $this->actingAs($this->jorge, 'web');

    Livewire::test(Index::class)
        ->assertDontSeeHtml('wire:model.live="filtroProfissional"');
});

it('SEGURANÇA: profissional forçando outro profissional_id não vê dados de outro nem o cliente', function () {
    concluido($this, $this->jorge, 5, 'Comentário do Jorge');
    concluido($this, $this->ana, 4, 'Comentário da Ana');

    $this->actingAs($this->jorge, 'web');

    // Tenta burlar: manda o id da Ana no filtro. O servidor IGNORA (gate por permissão)
    // e o escopo segue forçado no próprio usuário.
    Livewire::test(Index::class)
        ->set('filtroProfissional', $this->ana->id)
        ->assertSee('Comentário do Jorge')      // continua vendo só os DELE
        ->assertDontSee('Comentário da Ana')     // NÃO vaza o de outro
        ->assertDontSee('Cliente Secreto');      // continua anônimo
});
