<?php

declare(strict_types=1);

use App\Livewire\Painel\Equipe\Horarios;
use App\Models\HorarioTrabalho;
use App\Models\Unidade;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = criarTenant('lojaum');
    tenancy()->initialize($this->tenant);
    $this->unidade = Unidade::create(['nome' => 'Matriz', 'ativo' => true]);
    $this->prof = usuarioComPapel('Profissional', ['email' => 'prof@lojaum.com']);
});

it('salva múltiplas faixas no mesmo dia (manhã e tarde)', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    Livewire::test(Horarios::class, ['user' => $this->prof])
        ->call('adicionarFaixa', 1)
        ->set('faixas.0.unidade_id', $this->unidade->id)
        ->set('faixas.0.hora_inicio', '09:00')
        ->set('faixas.0.hora_fim', '12:00')
        ->call('adicionarFaixa', 1)
        ->set('faixas.1.unidade_id', $this->unidade->id)
        ->set('faixas.1.hora_inicio', '13:00')
        ->set('faixas.1.hora_fim', '18:00')
        ->call('salvar')
        ->assertHasNoErrors();

    expect(HorarioTrabalho::where('user_id', $this->prof->id)->where('dia_semana', 1)->count())->toBe(2);
});

it('rejeita faixa com fim antes do início', function () {
    $this->actingAs(usuarioComPapel('Dono'), 'web');

    Livewire::test(Horarios::class, ['user' => $this->prof])
        ->call('adicionarFaixa', 2)
        ->set('faixas.0.unidade_id', $this->unidade->id)
        ->set('faixas.0.hora_inicio', '18:00')
        ->set('faixas.0.hora_fim', '09:00')
        ->call('salvar')
        ->assertHasErrors('faixas.0.hora_fim');

    expect(HorarioTrabalho::where('user_id', $this->prof->id)->count())->toBe(0);
});

it('bloqueia Profissional (403) na edição de horários', function () {
    $this->actingAs(usuarioComPapel('Profissional', ['email' => 'outro@lojaum.com']), 'web')
        ->get("/lojaum/painel/equipe/{$this->prof->id}/horarios")
        ->assertForbidden();
});
