<?php

declare(strict_types=1);

use App\Http\Middleware\GarantirAssinaturaAtiva;
use App\Models\Assinatura;
use App\Models\Fatura;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Livewire;

/*
| Fase 4c (D60) — suspensão por pagamento. Enforcement AO VIVO (situacaoAcesso) só no
| painel (guard web): suspensa/cancelada → tela de suspensão; atrasada → banner (Dono).
| Portal do cliente intacto; inativo segue 404; reversível ao marcar pago.
*/

afterEach(fn () => Carbon::setTestNow());

/** Tenant + Dono (no tenant) + assinatura (central) com situação controlada. */
function cenario(string $slug, string $status, ?int $venceuHaDias = null): array
{
    Carbon::setTestNow('2026-06-25');
    $tenant = criarTenant($slug);
    $tenant->run(fn () => usuarioComPapel('Dono', ['email' => "dono@{$slug}.com"]));
    $dono = $tenant->run(fn () => User::where('email', "dono@{$slug}.com")->first());

    $a = Assinatura::create([
        'tenant_id' => $slug, 'plano' => 'basico', 'valor_mensal' => 49.90,
        'data_inicio' => '2026-04-01', 'trial_dias' => 0, 'status' => $status,
    ]);

    if ($venceuHaDias !== null) {
        $a->faturas()->create([
            'competencia' => '2026-06-01', 'valor' => 49.90,
            'data_vencimento' => Carbon::today()->subDays($venceuHaDias), 'status' => Fatura::ABERTA,
        ]);
    }

    return [$tenant, $dono, $a];
}

// ---- em_teste / ativa: nada muda --------------------------------------------

it('ativa: login do painel normal (200) e sem banner', function () {
    [$tenant, $dono] = cenario('lojaum', Assinatura::ATIVA); // sem fatura vencida → ativa

    $this->get('/lojaum/painel/login')->assertOk();
    $this->actingAs($dono, 'web')->get('/lojaum/painel')->assertOk()->assertDontSee('Sua fatura venceu em');
});

// ---- atrasada: acesso segue + banner (só Dono) ------------------------------

it('atrasada: painel funciona (200) e o Dono vê o banner de carência', function () {
    [$tenant, $dono] = cenario('lojaum', Assinatura::ATIVA, venceuHaDias: 10); // dentro dos 20

    $this->get('/lojaum/painel/login')->assertOk(); // não bloqueia

    $this->actingAs($dono, 'web')->get('/lojaum/painel')
        ->assertOk()
        ->assertSee('Sua fatura venceu em')
        ->assertSee('05/07/2026'); // limite = 25/06 + 20 dias
});

it('atrasada: a equipe (sem ver_financeiro) NÃO vê o banner', function () {
    cenario('lojaum', Assinatura::ATIVA, venceuHaDias: 10);
    $rec = Tenant::find('lojaum')->run(fn () => usuarioComPapel('Recepção', ['email' => 'rec@lojaum.com']));

    // Recepção não acessa o dashboard (exige ver_dashboard); usa a agenda (tem ver_agenda).
    // O banner mora no layout (toda página do painel), então a ausência vale igual.
    $this->actingAs($rec, 'web')->get('/lojaum/painel/agenda')
        ->assertOk()
        ->assertDontSee('Sua fatura venceu em');
});

// ---- suspensa / cancelada: bloqueia o painel --------------------------------

it('suspensa: tentar o painel redireciona para a tela de suspensão', function () {
    [$tenant, $dono] = cenario('lojaum', Assinatura::ATIVA, venceuHaDias: 25); // > 20 → suspensa

    $destino = route('painel.assinatura.suspensa', ['tenant' => 'lojaum']);

    // Vindo do login (guest) e do painel autenticado: ambos caem na suspensão.
    $this->get('/lojaum/painel/login')->assertRedirect($destino);
    $this->actingAs($dono, 'web')->get('/lojaum/painel')->assertRedirect($destino);

    // A tela de suspensão abre (200) e mostra a fatura.
    $this->get($destino)->assertOk()->assertSee('Assinatura pausada')->assertSee('49,90');
});

it('cancelada: mesmo bloqueio que suspensa', function () {
    [$tenant, $dono] = cenario('lojaum', Assinatura::CANCELADA);

    $this->actingAs($dono, 'web')->get('/lojaum/painel')
        ->assertRedirect(route('painel.assinatura.suspensa', ['tenant' => 'lojaum']));
});

it('a tela de suspensão é isenta (sem loop) e some quando não está bloqueada', function () {
    // Suspensa: a própria tela abre normalmente (não redireciona p/ si mesma).
    cenario('lojaum', Assinatura::ATIVA, venceuHaDias: 25);
    $this->get('/lojaum/painel/assinatura-suspensa')->assertOk();

    // Ativa: abrir a tela à toa volta ao login (não fica presa nela).
    cenario('lojadois', Assinatura::ATIVA);
    $this->get('/lojadois/painel/assinatura-suspensa')
        ->assertRedirect(route('painel.login', ['tenant' => 'lojadois']));
});

it('suspensa: o logout continua funcionando (isento do bloqueio)', function () {
    [$tenant, $dono] = cenario('lojaum', Assinatura::ATIVA, venceuHaDias: 25);

    $this->actingAs($dono, 'web')->post('/lojaum/painel/sair')
        ->assertRedirect(route('painel.login', ['tenant' => 'lojaum']));
    $this->assertGuest('web');
});

// ---- portal do cliente e inativo: intactos ----------------------------------

it('portal do cliente do tenant suspenso continua no ar', function () {
    cenario('lojaum', Assinatura::ATIVA, venceuHaDias: 25); // suspensa

    $this->get('/lojaum')->assertOk();          // portal (home)
    $this->get('/lojaum/login')->assertOk();     // login do cliente
});

it('inativo segue 404 (caminho separado da suspensão), mesmo com assinatura suspensa', function () {
    cenario('lojaum', Assinatura::ATIVA, venceuHaDias: 25);
    Tenant::whereKey('lojaum')->update(['ativo' => false]);

    $this->get('/lojaum/painel/login')->assertNotFound();              // 404 do GarantirTenantAtivo
    $this->get('/lojaum/painel/assinatura-suspensa')->assertNotFound(); // nem a tela de suspensão
});

// ---- reversibilidade --------------------------------------------------------

it('marcar a fatura paga libera o painel no próximo request (ao vivo)', function () {
    [$tenant, $dono, $a] = cenario('lojaum', Assinatura::ATIVA, venceuHaDias: 25);

    // Bloqueada agora.
    $this->actingAs($dono, 'web')->get('/lojaum/painel')
        ->assertRedirect(route('painel.assinatura.suspensa', ['tenant' => 'lojaum']));

    // Marca paga → situação volta a ativa.
    $a->faturaPendente()->update(['status' => Fatura::PAGA, 'data_pagamento' => Carbon::today()]);

    $this->actingAs($dono, 'web')->get('/lojaum/painel')->assertOk();
});

// ---- M1 (D71): suspensão também nas AÇÕES Livewire (não só no GET) ----------
//
// O GarantirAssinaturaAtiva precisa valer nas requisições de update do Livewire, senão
// uma aba aberta antes da suspensão continua agindo. Como o endpoint /update é central e
// só reaplica os PERSISTENT middleware que estavam na ROTA ORIGINAL do componente, a
// blindagem é a dupla: (1) ele é persistent; (2) ele está na rota do painel. Juntos →
// reaplicado nas ações do painel; ausente no portal (rota do cliente não o tem) → portal
// intacto. (Verificado fim-a-fim por HTTP/Playwright: suspenso bloqueia a ação e
// redireciona; dono ativo age normal; portal segue 200.)

it('M1: GarantirAssinaturaAtiva é persistent middleware do Livewire (vale nas ações /update)', function () {
    expect(Livewire::getPersistentMiddleware())->toContain(GarantirAssinaturaAtiva::class);
});

it('M1: a rota do painel tem o GarantirAssinaturaAtiva (logo é reaplicado nas ações; portal não o tem)', function () {
    $router = app('router');

    $rotaPainel = $router->getRoutes()->getByName('painel.agenda');
    expect($router->gatherRouteMiddleware($rotaPainel))->toContain(GarantirAssinaturaAtiva::class);

    // Portal do cliente NÃO carrega o middleware → não será reaplicado nas ações dele.
    $rotaPortal = $router->getRoutes()->getByName('tenant.home');
    expect($router->gatherRouteMiddleware($rotaPortal))->not->toContain(GarantirAssinaturaAtiva::class);
});
