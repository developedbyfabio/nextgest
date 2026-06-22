<?php

declare(strict_types=1);

use App\Enums\Recurso;
use App\Livewire\Admin\TenantDetalhe;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/*
| Fase 0a — feature flags por tenant. A flag mora no banco CENTRAL, dentro do JSON
| `data` do tenant (chave `recursos`). Default: tudo DESLIGADO. Estes testes cobrem
| EFEITO (não existência), incluindo o fluxo HTTP autenticado por tenant do middleware.
*/

it('admin liga um recurso, persiste no central e fica isolado por estabelecimento', function () {
    criarTenant('lojaum');
    criarTenant('lojadois');

    $this->actingAs(admin(), 'admin');

    Livewire::test(TenantDetalhe::class, ['tenantId' => 'lojaum'])
        ->set('recursos.whatsapp', true)
        ->call('salvarRecursos')
        ->assertHasNoErrors();

    // Persistiu no registro central — e só no tenant certo.
    expect(Tenant::find('lojaum')->recursosAtivos())->toBe(['whatsapp'])
        ->and(Tenant::find('lojadois')->recursosAtivos())->toBe([]);

    // Dentro do contexto de cada tenant, o helper responde certo (isolamento real).
    tenancy()->initialize(Tenant::find('lojaum'));
    expect(tenant_tem_recurso('whatsapp'))->toBeTrue();
    tenancy()->end();

    tenancy()->initialize(Tenant::find('lojadois'));
    expect(tenant_tem_recurso('whatsapp'))->toBeFalse();
    tenancy()->end();
});

it('estabelecimento novo nasce com todos os recursos desligados (default)', function () {
    $t = criarTenant('lojaum');

    expect($t->recursosAtivos())->toBe([])
        ->and(Tenant::find('lojaum')->recursosAtivos())->toBe([]);

    foreach (Recurso::valores() as $slug) {
        expect($t->temRecurso($slug))->toBeFalse();
    }
});

it('ligar um recurso PRESERVA o segmento do estabelecimento (mesmo data)', function () {
    $t = criarTenant('lojaum');
    $t->segmento = 'barbearia'; // metadado que mora no mesmo JSON `data`
    $t->save();

    expect(Tenant::find('lojaum')->segmento)->toBe('barbearia');

    $this->actingAs(admin(), 'admin');

    Livewire::test(TenantDetalhe::class, ['tenantId' => 'lojaum'])
        ->set('recursos.clube', true)
        ->call('salvarRecursos')
        ->assertHasNoErrors();

    $fresh = Tenant::find('lojaum');
    expect($fresh->segmento)->toBe('barbearia')          // segmento intacto
        ->and($fresh->recursosAtivos())->toBe(['clube']); // e o recurso ficou ligado
});

it('tenant_tem_recurso SEM contexto de tenant retorna false (não lança)', function () {
    criarTenant('lojaum'); // existe, mas o tenancy NÃO é inicializado

    expect(tenancy()->initialized)->toBeFalse()
        ->and(tenant_tem_recurso('whatsapp'))->toBeFalse();
});

it('tenant_tem_recurso com chave desconhecida retorna false e loga aviso', function () {
    Log::spy();

    tenancy()->initialize(criarTenant('lojaum'));

    expect(tenant_tem_recurso('chave_que_nao_existe'))->toBeFalse();

    tenancy()->end();

    Log::shouldHaveReceived('warning')->once();
});

it('leitura é normalizada: lixo/desconhecido no data nunca quebra nem liga recurso', function () {
    $t = criarTenant('lojaum');
    // Simula dado estranho gravado no `data` (versões antigas, edição manual etc.).
    $t->recursos = ['whatsapp', 'modulo_fantasma', 123, null, 'clube'];
    $t->save();

    // Só os slugs válidos sobrevivem; o resto é descartado.
    expect(Tenant::find('lojaum')->recursosAtivos())->toBe(['whatsapp', 'clube']);
});

/**
 * Rota de PROVA do middleware — registrada SÓ no teste (não polui as rotas de
 * produção), DENTRO do grupo de tenant (tenancy por caminho). Exercitada por HTTP
 * real autenticado, não unit isolado (lição nº1 do projeto).
 */
function registrarRotaProvaRecurso(): void
{
    Route::middleware(['tenant', 'auth:web', 'recurso:whatsapp'])
        ->prefix('{tenant}')
        ->group(function () {
            Route::get('painel/_prova_recurso', fn () => response('ok', 200));
        });
}

/*
| Dois testes separados (em vez de ligar/desligar no MESMO teste) porque cada teste
| é um ciclo de request isolado — fiel à produção. Ligar e bater de novo no mesmo
| processo esbarraria no early-return de Tenancy::initialize() (mesmo tenant já
| inicializado → mantém a instância stale). Ver doc [[Gotchas e Aprendizados do Projeto]].
*/

it('middleware recurso:whatsapp BLOQUEIA (404) com o recurso desligado — HTTP autenticado por tenant', function () {
    registrarRotaProvaRecurso();

    $tenant = criarTenant('lojaum');
    $tenant->run(fn () => usuarioComPapel('Dono', ['email' => 'dono@lojaum.com']));
    $dono = $tenant->run(fn () => User::where('email', 'dono@lojaum.com')->first());

    // Recurso DESLIGADO (default) → a rota "nem existe" para este tenant.
    $this->actingAs($dono, 'web')
        ->get('/lojaum/painel/_prova_recurso')
        ->assertNotFound();
});

it('middleware recurso:whatsapp LIBERA com o recurso ligado — HTTP autenticado por tenant', function () {
    registrarRotaProvaRecurso();

    $tenant = criarTenant('lojaum');
    $tenant->run(fn () => usuarioComPapel('Dono', ['email' => 'dono@lojaum.com']));
    $dono = $tenant->run(fn () => User::where('email', 'dono@lojaum.com')->first());

    // Liga o recurso no registro central.
    $t = Tenant::find('lojaum');
    $t->recursos = ['whatsapp'];
    $t->save();

    $this->actingAs($dono, 'web')
        ->get('/lojaum/painel/_prova_recurso')
        ->assertOk()
        ->assertSee('ok');
});
