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

function concluido($self, $prof, ?int $nota = null, ?string $comentario = null, ?Carbon $quando = null): Agendamento
{
    $quando ??= Carbon::now()->subDays(2)->setTime(10, 0);
    $ag = Agendamento::create([
        'unidade_id' => $self->unidade->id,
        'cliente_id' => $self->cliente->id,
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
            'agendamento_id' => $ag->id, 'cliente_id' => $self->cliente->id,
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
