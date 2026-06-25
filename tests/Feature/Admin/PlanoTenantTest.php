<?php

declare(strict_types=1);

use App\Livewire\Admin\OnboardingEstabelecimento as Onboarding;
use App\Livewire\Admin\TenantDetalhe;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;

/*
| Fase 2 (D55) — plano nomeado dirige os recursos. Catálogo em config/planos.php.
| Mapa: basico=[], profissional=[clube,gateway], nextgest=[clube,gateway,whatsapp].
| Cobre o EFEITO (recursos aplicados + gating real por HTTP), não só a existência.
*/

// admin() / criarTenant() / usuarioComPapel() são helpers globais (tests/Pest.php).

// ---- Model: aplicarPlano / planoAtual --------------------------------------

it('aplicarPlano seta plano + recursos do catálogo e PRESERVA o segmento', function () {
    $t = criarTenant('lojaum');
    $t->segmento = 'barbearia'; // metadado no mesmo JSON `data`
    $t->save();

    $t->aplicarPlano('profissional');

    $fresh = Tenant::find('lojaum');
    expect($fresh->planoAtual())->toBe('profissional')
        ->and($fresh->recursosAtivos())->toBe(['clube', 'gateway'])
        ->and($fresh->segmento)->toBe('barbearia'); // regra de ouro: segmento intacto
});

it('aplicarPlano com chave fora do catálogo lança e não muta', function () {
    $t = criarTenant('lojaum');

    expect(fn () => $t->aplicarPlano('inexistente'))
        ->toThrow(InvalidArgumentException::class);

    expect(Tenant::find('lojaum')->planoAtual())->toBeNull()
        ->and(Tenant::find('lojaum')->recursosAtivos())->toBe([]);
});

it('planoAtual normaliza: null para tenant sem plano ou com lixo no data', function () {
    $t = criarTenant('lojaum');
    expect($t->planoAtual())->toBeNull(); // nunca definido

    $t->plano = 'plano_fantasma'; // valor fora do catálogo
    $t->save();
    expect(Tenant::find('lojaum')->planoAtual())->toBeNull();
});

it('rebaixar o plano (nextgest -> basico) esconde os recursos (dados permanecem)', function () {
    $t = criarTenant('lojaum');
    $t->aplicarPlano('nextgest');
    expect(Tenant::find('lojaum')->recursosAtivos())->toBe(['clube', 'gateway', 'whatsapp']);

    Tenant::find('lojaum')->aplicarPlano('basico');
    expect(Tenant::find('lojaum')->planoAtual())->toBe('basico')
        ->and(Tenant::find('lojaum')->recursosAtivos())->toBe([]);
});

// ---- Onboarding: etapa Plano ----------------------------------------------

it('não avança da etapa Plano sem selecionar um plano', function () {
    $this->actingAs(admin(), 'admin');

    Livewire::test(Onboarding::class)
        ->set('etapa', 6) // Plano virou a etapa 6 (D56)
        ->call('proximo')
        ->assertHasErrors('plano')
        ->assertSet('etapa', 6);
});

it('a revisão mostra o plano escolhido e os recursos inclusos', function () {
    $this->actingAs(admin(), 'admin');

    Livewire::test(Onboarding::class)
        ->set('plano', 'nextgest')
        ->set('etapa', 7) // Revisão virou a etapa 7 (D56)
        ->assertSee('Nextgest')
        ->assertSee('WhatsApp / Lembretes'); // rótulo do recurso whatsapp
});

// ---- TenantDetalhe: troca de plano ----------------------------------------

it('troca de plano reaplica os recursos e re-sincroniza os toggles (preserva segmento)', function () {
    $t = criarTenant('lojaum');
    $t->segmento = 'barbearia';
    $t->save();

    $this->actingAs(admin(), 'admin');

    Livewire::test(TenantDetalhe::class, ['tenantId' => 'lojaum'])
        ->set('plano', 'profissional')
        ->call('trocarPlano')
        ->assertHasNoErrors()
        ->assertSet('recursos.clube', true)
        ->assertSet('recursos.gateway', true)
        ->assertSet('recursos.whatsapp', false);

    $fresh = Tenant::find('lojaum');
    expect($fresh->planoAtual())->toBe('profissional')
        ->and($fresh->recursosAtivos())->toBe(['clube', 'gateway'])
        ->and($fresh->segmento)->toBe('barbearia');
});

it('detalhe de tenant SEM plano mostra "não definido" e não muta nada', function () {
    $tenant = criarTenant('lojaum');
    $tenant->run(fn () => usuarioComPapel('Dono', ['email' => 'dono@lojaum.com']));

    $this->actingAs(admin(), 'admin');

    Livewire::test(TenantDetalhe::class, ['tenantId' => 'lojaum'])
        ->assertSet('plano', '')
        ->assertSee('não definido');

    expect(Tenant::find('lojaum')->planoAtual())->toBeNull()
        ->and(Tenant::find('lojaum')->recursosAtivos())->toBe([]); // nada foi mutado
});

// ---- Gating real por HTTP (o que o plano libera/bloqueia no painel) --------

it('plano nextgest LIBERA a rota do Clube (recurso:clube) — HTTP autenticado por tenant', function () {
    $tenant = criarTenant('lojaum');
    $tenant->run(fn () => usuarioComPapel('Dono', ['email' => 'dono@lojaum.com']));
    $dono = $tenant->run(fn () => User::where('email', 'dono@lojaum.com')->first());

    Tenant::find('lojaum')->aplicarPlano('nextgest');

    $this->actingAs($dono, 'web')
        ->get('/lojaum/painel/clube')
        ->assertOk()
        ->assertSee('Clube de Assinatura');
});

it('plano básico BLOQUEIA a rota do Clube (404) — HTTP autenticado por tenant', function () {
    $tenant = criarTenant('lojaum');
    $tenant->run(fn () => usuarioComPapel('Dono', ['email' => 'dono@lojaum.com']));
    $dono = $tenant->run(fn () => User::where('email', 'dono@lojaum.com')->first());

    Tenant::find('lojaum')->aplicarPlano('basico');

    $this->actingAs($dono, 'web')
        ->get('/lojaum/painel/clube')
        ->assertNotFound();
});
