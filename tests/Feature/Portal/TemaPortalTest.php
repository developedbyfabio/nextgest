<?php

declare(strict_types=1);

use App\Livewire\Portal\Agendar;
use App\Models\Cliente;
use App\Models\Servico;
use App\Models\Unidade;
use Carbon\Carbon;
use Livewire\Livewire;

it('injeta as CSS variables do tema do estabelecimento no portal', function () {
    criarTenant('lojaum');

    $this->get('/lojaum')
        ->assertOk()
        ->assertSee('--cor-principal', false)   // false = não escapar (procura literal)
        ->assertSee('--color-accent', false)    // marca alimenta os componentes Flux
        ->assertSee('--cor-texto', false);
});

it('mostra o nome do serviço e do profissional no wizard de agendar', function () {
    $tenant = criarTenant('lojaum');
    tenancy()->initialize($tenant);

    Carbon::setTestNow(Carbon::parse('2026-07-06 08:00:00'));
    $diaSemana = (int) Carbon::now()->dayOfWeek;

    $unidade = Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    $corte = Servico::create(['nome' => 'Corte Degradê', 'duracao_minutos' => 30, 'preco' => 45, 'ativo' => true]);
    $corte->unidades()->sync([$unidade->id]);
    $prof = profissionalAgenda($unidade, [$corte], [[$diaSemana, '09:00', '18:00']], ['name' => 'Ana Navalha']);
    $cliente = Cliente::create(['nome' => 'Maria', 'telefone' => '11', 'email' => 'maria@l.test']);

    $this->actingAs($cliente, 'cliente');

    $componente = Livewire::test(Agendar::class)
        ->assertSee('Corte Degradê')          // nome do serviço (passo de serviços)
        ->call('toggleServico', $corte->id)
        ->call('irParaProfissional')
        ->assertSee('Ana Navalha');            // nome do profissional (passo de profissional)

    expect($componente)->not->toBeNull();

    Carbon::setTestNow();
});
