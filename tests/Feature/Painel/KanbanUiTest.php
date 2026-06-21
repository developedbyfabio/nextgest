<?php

declare(strict_types=1);

use App\Livewire\Painel\Kanban\Index;
use App\Models\KanbanCartao;
use App\Models\KanbanQuadro;
use App\Support\Aparencia;
use Livewire\Livewire;

/**
 * Etapa C — elevação do kanban: arquivar (soft delete) em vez de apagar,
 * confirmações por modal (sem confirm nativo) e acabamento temático dark-safe
 * nos dois quadros.
 */
beforeEach(function () {
    $this->tenant = criarTenant('lojakbui');
    tenancy()->initialize($this->tenant);
    $this->dono = usuarioComPapel('Dono', ['email' => 'dono@kbui.test']);
});

function primeiraColunaAtendimento(): \App\Models\KanbanColuna
{
    return KanbanQuadro::where('tipo', 'atendimento')->first()->colunas()->orderBy('ordem')->first();
}

it('arquivar cartão é soft delete: sai do quadro mas não é apagado', function () {
    $this->actingAs($this->dono, 'web');
    $coluna = primeiraColunaAtendimento();
    $cartao = KanbanCartao::create(['coluna_id' => $coluna->id, 'titulo' => 'Arquivável', 'ordem' => 0]);

    Livewire::test(Index::class)
        ->call('pedirArquivarCartao', $cartao->id)
        ->assertSet('confirmarCartao', $cartao->id)   // modal aponta o alvo
        ->call('removerCartao', $cartao->id)
        ->assertSet('confirmarCartao', null);

    expect(KanbanCartao::find($cartao->id))->toBeNull();                          // fora do quadro
    expect(KanbanCartao::withTrashed()->find($cartao->id))->not->toBeNull();      // preservado
    expect(KanbanCartao::withTrashed()->find($cartao->id)->trashed())->toBeTrue();
});

it('cartão arquivado não aparece mais no quadro', function () {
    $this->actingAs($this->dono, 'web');
    $coluna = primeiraColunaAtendimento();
    $cartao = KanbanCartao::create(['coluna_id' => $coluna->id, 'titulo' => 'Some do board', 'ordem' => 0]);

    Livewire::test(Index::class)
        ->assertSee('Some do board')
        ->call('removerCartao', $cartao->id)
        ->assertDontSee('Some do board');
});

it('remover coluna pede confirmação por modal (sem confirm nativo)', function () {
    $this->actingAs($this->dono, 'web');
    $coluna = primeiraColunaAtendimento();

    Livewire::test(Index::class)
        ->call('pedirRemoverColuna', $coluna->id)
        ->assertSet('confirmarColuna', $coluna->id);

    // Só pedir não remove.
    expect(\App\Models\KanbanColuna::find($coluna->id))->not->toBeNull();
});

it('os dois quadros usam superfícies da marca (.ng-surface)', function () {
    Aparencia::salvar(['cor_principal' => '#123456']);
    $this->actingAs($this->dono, 'web');

    Livewire::test(Index::class)
        ->assertSeeHtml('ng-surface')                 // Atendimento temático
        ->call('trocarQuadro', 'crm')
        ->assertSet('tipo', 'crm')
        ->assertSeeHtml('ng-surface');                // CRM temático
});

it('kanban é dark-safe com superfície escura (liga .dark e aplica a superfície)', function () {
    Aparencia::salvar(['cor_superficie' => '#0f172a', 'cor_fundo' => '#020617', 'cor_texto' => '#f8fafc', 'cor_principal' => '#22d3ee']);

    $html = $this->actingAs($this->dono, 'web')->get('/lojakbui/painel/kanban')->assertOk()->content();

    expect($html)->toContain('class="dark"')
        ->and($html)->toContain('--cor-superficie: #0f172a')
        ->and($html)->toContain('ng-surface');
});

it('o handle de arraste está presente nos cartões', function () {
    $this->actingAs($this->dono, 'web');
    $coluna = primeiraColunaAtendimento();
    KanbanCartao::create(['coluna_id' => $coluna->id, 'titulo' => 'Com handle', 'ordem' => 0]);

    Livewire::test(Index::class)->assertSeeHtml('data-kanban-handle');
});
