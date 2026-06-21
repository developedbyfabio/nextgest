<?php

declare(strict_types=1);

use App\Livewire\Portal\Agendar;
use App\Livewire\Portal\Home;
use App\Models\Cliente;
use App\Models\Servico;
use App\Models\Unidade;
use App\Services\Agendamento\Agendador;
use App\Support\Aparencia;
use Carbon\Carbon;
use Livewire\Livewire;

/**
 * Garante a elevação de UI do portal do cliente:
 * - componentes seguem o TEMA do estabelecimento (CSS vars), não zinc/branco fixo;
 * - cancelamento usa modal (não confirm nativo);
 * - estados vazios temáticos.
 */
beforeEach(function () {
    $this->tenant = criarTenant('lojaui');
    tenancy()->initialize($this->tenant);

    Carbon::setTestNow(Carbon::parse('2026-07-06 08:00:00'));
    $this->dia = Carbon::now()->format('Y-m-d');
    $diaSemana = (int) Carbon::now()->dayOfWeek;

    $this->unidade = Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    $this->corte = Servico::create(['nome' => 'Corte', 'duracao_minutos' => 60, 'preco' => 50, 'ativo' => true]);
    $this->corte->unidades()->sync([$this->unidade->id]);
    $this->prof = profissionalAgenda($this->unidade, [$this->corte], [[$diaSemana, '09:00', '18:00']], ['name' => 'Ana']);
    $this->cliente = Cliente::create(['nome' => 'Maria', 'telefone' => '1199', 'email' => 'maria@l.test']);
});

afterEach(fn () => Carbon::setTestNow());

it('wizard usa cartões de opção temáticos (seguem a superfície do tenant)', function () {
    $this->actingAs($this->cliente, 'cliente');

    $html = Livewire::test(Agendar::class)->html();

    expect($html)->toContain('ng-card-portal')        // variante temática do portal
        ->and($html)->not->toContain('ng-card-interactive'); // não a neutra (zinc/branco)
});

it('o cartão temático honra uma superfície escura customizada', function () {
    // O dono pode escolher superfície escura; os cartões do portal devem segui-la,
    // não ficar brancos fixos. A var --cor-superficie é aplicada via .ng-card-portal.
    Aparencia::salvar(['cor_superficie' => '#1f2937', 'cor_texto' => '#f9fafb']);

    expect(Aparencia::doTenant()['cor_superficie'])->toBe('#1f2937');

    $this->actingAs($this->cliente, 'cliente');
    expect(Livewire::test(Agendar::class)->html())->toContain('ng-card-portal');
});

it('home sem agendamentos mostra estado vazio temático', function () {
    $this->actingAs($this->cliente, 'cliente');

    Livewire::test(Home::class)
        ->assertSee('Nenhum agendamento futuro')
        ->assertSeeHtml('var(--cor-texto-suave)'); // texto do empty segue o tema
});

it('cancelar abre confirmação por modal (sem confirm nativo)', function () {
    $ag = app(Agendador::class)->confirmar(
        $this->cliente, $this->unidade->id, [$this->corte->id], $this->prof->id,
        Carbon::now()->copy()->setTime(11, 0),
    );

    $this->actingAs($this->cliente, 'cliente');

    // O botão da home pede confirmação (seta o alvo) em vez de cancelar direto.
    Livewire::test(Home::class)
        ->assertSet('cancelandoId', null)
        ->call('pedirCancelamento', $ag->id)
        ->assertSet('cancelandoId', $ag->id);

    // O agendamento ainda não foi cancelado só por pedir.
    expect($ag->fresh()->status)->toBe('confirmado');
});

it('confirmar no modal cancela e limpa o alvo', function () {
    $ag = app(Agendador::class)->confirmar(
        $this->cliente, $this->unidade->id, [$this->corte->id], $this->prof->id,
        Carbon::now()->copy()->setTime(11, 0),
    );

    $this->actingAs($this->cliente, 'cliente');

    Livewire::test(Home::class)
        ->call('pedirCancelamento', $ag->id)
        ->call('cancelar', $ag->id)
        ->assertSet('cancelandoId', null);

    expect($ag->fresh()->status)->toBe('cancelado');
});
