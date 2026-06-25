<?php

declare(strict_types=1);

use App\Livewire\Admin\Faturamento;
use App\Livewire\Admin\Tenants;
use App\Models\Assinatura;
use App\Models\Fatura;
use Carbon\Carbon;
use Livewire\Livewire;

/*
| Fase 4b (D59) — tela Faturamento (admin): configura a assinatura, gera/marca faturas
| e mostra a situação (situacaoAcesso). SÓ operação manual + visualização: nenhum
| bloqueio de login (informativo). Cobrança salão → Nextgest (≠ Clube).
*/

// admin() / criarTenant() — helpers globais (tests/Pest.php).

afterEach(fn () => Carbon::setTestNow());

it('exige super-admin para abrir o Faturamento', function () {
    criarTenant('lojaum');

    $this->get('/admin/estabelecimentos/lojaum/faturamento')
        ->assertRedirect(route('admin.login'));
});

it('cria a assinatura no 1º uso (firstOrNew) com defaults do plano', function () {
    criarTenant('lojaum')->aplicarPlano('profissional');

    $this->actingAs(admin(), 'admin');

    Livewire::test(Faturamento::class, ['tenantId' => 'lojaum'])
        ->assertSet('valorMensal', '99.90')        // snapshot do preço do plano
        ->assertSet('statusManual', Assinatura::EM_TESTE)
        ->assertSee('Em teste');

    $a = Assinatura::where('tenant_id', 'lojaum')->first();
    expect($a)->not->toBeNull()
        ->and($a->plano)->toBe('profissional')
        ->and($a->status)->toBe(Assinatura::EM_TESTE);
});

it('salva a configuração da assinatura', function () {
    criarTenant('lojaum');
    $this->actingAs(admin(), 'admin');

    Livewire::test(Faturamento::class, ['tenantId' => 'lojaum'])
        ->set('valorMensal', '79.90')
        ->set('dataInicio', '2026-04-01')
        ->set('trialDias', '15')
        ->set('diaVencimento', '10')
        ->set('statusManual', Assinatura::ATIVA)
        ->call('salvarConfig')
        ->assertHasNoErrors();

    $a = Assinatura::where('tenant_id', 'lojaum')->first();
    expect((float) $a->valor_mensal)->toBe(79.90)
        ->and($a->trial_dias)->toBe(15)
        ->and($a->dia_vencimento)->toBe(10)
        ->and($a->status)->toBe(Assinatura::ATIVA);
});

it('não deixa definir status derivado (suspensa/atrasada) à mão', function () {
    criarTenant('lojaum');
    $this->actingAs(admin(), 'admin');

    Livewire::test(Faturamento::class, ['tenantId' => 'lojaum'])
        ->set('statusManual', 'suspensa')
        ->call('salvarConfig')
        ->assertHasErrors('statusManual');
});

it('gera fatura e barra competência duplicada com mensagem amigável (sem 500)', function () {
    criarTenant('lojaum');
    $this->actingAs(admin(), 'admin');

    $c = Livewire::test(Faturamento::class, ['tenantId' => 'lojaum'])
        ->set('novaCompetencia', '2026-06')
        ->set('novoValor', '99.90')
        ->set('novoVencimento', '2026-07-10')
        ->call('gerarFatura')
        ->assertHasNoErrors();

    expect(Fatura::count())->toBe(1);
    $f = Fatura::first();
    expect((string) $f->valor)->toBe('99.90')      // dinheiro em decimal
        ->and($f->status)->toBe(Fatura::ABERTA)
        ->and($f->link_pagamento)->toBeNull();      // manual: sem link (gateway é fase 5)

    // Mesma competência → barrado (unique) com erro amigável, sem duplicar.
    $c->set('novaCompetencia', '2026-06')
        ->set('novoValor', '99.90')
        ->set('novoVencimento', '2026-07-10')
        ->call('gerarFatura')
        ->assertHasErrors('novaCompetencia');

    expect(Fatura::count())->toBe(1);
});

it('marca paga, recalcula situação para ativa, e reverte/cancela', function () {
    Carbon::setTestNow('2026-06-25');
    criarTenant('lojaum');
    $this->actingAs(admin(), 'admin');

    $c = Livewire::test(Faturamento::class, ['tenantId' => 'lojaum'])
        ->set('valorMensal', '99.90')
        ->set('dataInicio', '2026-04-01')
        ->set('trialDias', '0')
        ->set('statusManual', Assinatura::ATIVA)
        ->call('salvarConfig')
        // fatura vencida há 5 dias → atrasada
        ->set('novaCompetencia', '2026-06')
        ->set('novoValor', '99.90')
        ->set('novoVencimento', '2026-06-20')
        ->call('gerarFatura')
        ->assertHasNoErrors();

    $f = Fatura::first();
    $c->assertSee('Atrasada');

    // Marca paga → situação volta a ativa.
    $c->call('abrirPagar', $f->id)
        ->call('confirmarPagamento')
        ->assertHasNoErrors()
        ->assertSee('Ativa');
    expect(Fatura::find($f->id)->status)->toBe(Fatura::PAGA);

    // Reverte → aberta.
    $c->call('reverter', $f->id);
    $fr = Fatura::find($f->id);
    expect($fr->status)->toBe(Fatura::ABERTA)
        ->and($fr->data_pagamento)->toBeNull();

    // Cancela → cancelada.
    $c->call('cancelar', $f->id);
    expect(Fatura::find($f->id)->status)->toBe(Fatura::CANCELADA);
});

it('mostra suspensa no admin (informativo); o bloqueio do painel é a 4c', function () {
    Carbon::setTestNow('2026-06-25');
    criarTenant('lojablock');

    $a = Assinatura::create([
        'tenant_id' => 'lojablock', 'plano' => 'basico', 'valor_mensal' => 49.90,
        'data_inicio' => '2026-04-01', 'trial_dias' => 0, 'status' => Assinatura::ATIVA,
    ]);
    $a->faturas()->create([
        'competencia' => '2026-06-01', 'valor' => 49.90,
        'data_vencimento' => '2026-05-31', 'status' => Fatura::ABERTA, // vencida há 25 dias
    ]);

    expect($a->situacaoAcesso())->toBe(Assinatura::SUSPENSA);

    // No admin, o badge é só informativo (a tela de Faturamento não bloqueia nada).
    $this->actingAs(admin(), 'admin');
    Livewire::test(Faturamento::class, ['tenantId' => 'lojablock'])->assertSee('Suspensa');

    // O bloqueio EFETIVO é a 4c (D60): o login do tenant redireciona p/ a tela de suspensão.
    $this->get('/lojablock/painel/login')
        ->assertRedirect(route('painel.assinatura.suspensa', ['tenant' => 'lojablock']));
});

it('mostra o botão "Faturamento" na lista de estabelecimentos', function () {
    criarTenant('lojaum');
    $this->actingAs(admin(), 'admin');

    Livewire::test(Tenants::class)->assertSee('Faturamento');
});
