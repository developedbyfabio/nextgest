<?php

declare(strict_types=1);

use App\Livewire\Painel\Agenda\Index as Agenda;
use App\Livewire\Painel\Vendas\Detalhe;
use App\Livewire\Painel\Vendas\Index as Vendas;
use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Produto;
use App\Models\Servico;
use App\Models\Unidade;
use App\Models\Venda;
use App\Services\Estoque\MovimentadorEstoque;
use App\Services\Venda\Comanda;
use Carbon\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = criarTenant('lojafin');
    tenancy()->initialize($this->tenant);

    $this->unidade = Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    $this->cliente = Cliente::create(['nome' => 'Maria', 'telefone' => '11', 'email' => 'maria@f.test']);
    $this->corte = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 30, 'preco' => 45, 'ativo' => true, 'percentual_comissao' => 20]);
    $this->pomada = Produto::create(['nome' => 'Pomada', 'preco_venda' => 40, 'controla_estoque' => true, 'percentual_comissao' => 10, 'ativo' => true]);
    app(MovimentadorEstoque::class)->entrada($this->pomada->id, $this->unidade->id, 10);

    $this->prof = profissionalAgenda($this->unidade, [$this->corte], [], ['name' => 'Jorge']);
    $this->comanda = app(Comanda::class);
});

/** Cria um agendamento (com 1 serviço) para um profissional. */
function agendamentoDe($ctx, $profissional, string $status = 'confirmado'): Agendamento
{
    $ag = Agendamento::create([
        'unidade_id' => $ctx->unidade->id,
        'cliente_id' => $ctx->cliente->id,
        'profissional_id' => $profissional->id,
        'data_hora_inicio' => Carbon::now(),
        'data_hora_fim' => Carbon::now()->copy()->addMinutes(30),
        'status' => $status,
        'origem' => 'equipe',
    ]);
    $ag->itens()->create(['servico_id' => $ctx->corte->id, 'preco' => 45, 'duracao_minutos' => 30]);

    return $ag;
}

it('Profissional finaliza o PRÓPRIO atendimento → comanda travada (cliente + profissional) e itens de serviço', function () {
    $ag = agendamentoDe($this, $this->prof);
    $this->actingAs($this->prof, 'web');

    Livewire::test(Agenda::class)
        ->call('abrirDetalhe', $ag->id)
        ->call('finalizarAtendimento')
        ->assertRedirect();

    $ag->refresh();
    expect($ag->status)->toBe('concluido'); // conclui o atendimento

    $venda = Venda::where('agendamento_id', $ag->id)->first();
    expect($venda)->not->toBeNull()
        ->and($venda->cliente_id)->toBe($this->cliente->id)        // cliente travado (do agendamento)
        ->and($venda->profissional_id)->toBe($this->prof->id);     // quem atendeu = vendedor

    $item = $venda->itens()->first();
    expect($item->tipo)->toBe('servico')
        ->and($item->profissional_id)->toBe($this->prof->id);      // serviço com o profissional
});

it('Finalizar atendimento é idempotente (não duplica a comanda)', function () {
    $ag = agendamentoDe($this, $this->prof, 'concluido');
    $this->actingAs($this->prof, 'web');

    Livewire::test(Agenda::class)->call('abrirDetalhe', $ag->id)->call('finalizarAtendimento')->assertRedirect();
    Livewire::test(Agenda::class)->call('abrirDetalhe', $ag->id)->call('finalizarAtendimento')->assertRedirect();

    expect(Venda::where('agendamento_id', $ag->id)->count())->toBe(1);
});

it('Profissional NÃO finaliza atendimento de OUTRO profissional', function () {
    $outro = profissionalAgenda($this->unidade, [$this->corte], [], ['name' => 'Outro']);
    $agOutro = agendamentoDe($this, $outro);

    $this->actingAs($this->prof, 'web');

    // O escopo da agenda do Profissional só enxerga os próprios → bloqueia.
    expect(fn () => Livewire::test(Agenda::class)->set('detalheId', $agOutro->id)->call('finalizarAtendimento'))
        ->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);

    expect(Venda::where('agendamento_id', $agOutro->id)->count())->toBe(0);
});

it('Profissional acessa a comanda do PRÓPRIO atendimento, mas NÃO uma avulsa', function () {
    // Comanda de finalização do próprio atendimento → acessível.
    $ag = agendamentoDe($this, $this->prof, 'concluido');
    $vFinal = $this->comanda->apartirDeAgendamento($ag, $this->prof->id);

    $this->actingAs($this->prof, 'web')
        ->get('/lojafin/painel/vendas/'.$vFinal->id)
        ->assertOk();

    // Comanda avulsa (sem agendamento) → 403 para o Profissional.
    $vAvulsa = $this->comanda->abrir($this->unidade->id, $this->cliente->id);
    $this->actingAs($this->prof, 'web')
        ->get('/lojafin/painel/vendas/'.$vAvulsa->id)
        ->assertForbidden();

    // E o índice de comandas avulsas também é negado.
    $this->actingAs($this->prof, 'web')->get('/lojafin/painel/vendas')->assertForbidden();
});

it('Comanda de finalização: vendedor/cliente TRAVADOS (não troca o profissional)', function () {
    $ag = agendamentoDe($this, $this->prof, 'concluido');
    $v = $this->comanda->apartirDeAgendamento($ag, $this->prof->id);
    $outro = profissionalAgenda($this->unidade, [], [], ['name' => 'Tentativa']);

    $this->actingAs($this->prof, 'web');

    Livewire::test(Detalhe::class, ['venda' => $v->id])
        ->set('vendedorId', (string) $outro->id) // tenta trocar
        ->assertSet('vendedorId', (string) $this->prof->id); // volta travado

    expect(Venda::find($v->id)->profissional_id)->toBe($this->prof->id);
});

it('Avulsa: "quem vendeu" pré-preenche o item e a comissão grava por item (produto)', function () {
    $vendedor = profissionalAgenda($this->unidade, [], [], ['name' => 'Vendedor']);
    $gerente = usuarioComPapel('Dono', ['email' => 'dono@f.test']);
    $this->actingAs($gerente, 'web');

    // Cria a avulsa já com "quem vendeu".
    Livewire::test(Vendas::class)
        ->set('novaUnidadeId', (string) $this->unidade->id)
        ->set('novaClienteId', (string) $this->cliente->id)
        ->set('novaProfissionalId', (string) $vendedor->id)
        ->call('criar')
        ->assertRedirect();

    $venda = Venda::where('cliente_id', $this->cliente->id)->whereNull('agendamento_id')->latest('id')->first();
    expect($venda->profissional_id)->toBe($vendedor->id);

    // Abrir o modal de item pré-preenche o profissional com o vendedor.
    Livewire::test(Detalhe::class, ['venda' => $venda->id])
        ->call('abrirItem')
        ->assertSet('itemProfissionalId', (string) $vendedor->id)
        ->set('tipoItem', 'produto')
        ->set('itemRefId', (string) $this->pomada->id)
        ->set('itemQtd', 1)
        ->call('adicionarItem')
        ->call('pedirPagar')
        ->call('pagar');

    $venda->refresh();
    expect($venda->status)->toBe('paga');

    $item = $venda->itens()->where('tipo', 'produto')->first();
    expect($item->profissional_id)->toBe($vendedor->id)
        ->and((float) $item->percentual_comissao)->toBe(10.0)     // % do produto
        ->and((float) $item->valor_comissao)->toBe(4.0);          // 40 × 10%
});

it('Comanda avulsa permite escolher o cliente (selecionável)', function () {
    $gerente = usuarioComPapel('Dono', ['email' => 'dono2@f.test']);

    Livewire::actingAs($gerente, 'web');
    $v = $this->comanda->abrir($this->unidade->id, $this->cliente->id);

    // Sem agendamento → editável (não travado).
    expect($v->agendamento_id)->toBeNull()
        ->and($v->cliente_id)->toBe($this->cliente->id);
});
