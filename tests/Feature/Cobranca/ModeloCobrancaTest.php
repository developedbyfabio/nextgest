<?php

declare(strict_types=1);

use App\Models\Assinatura;
use App\Models\Fatura;
use Carbon\Carbon;

/*
| Fase 4a (D58) — modelo central de cobrança SaaS (salão → Nextgest). Cobre a fonte
| única situacaoAcesso() (fronteiras da carência), o snapshot de valor e o backfill
| idempotente. NÃO toca painel/login/portal/Clube.
*/

// criarTenant() — helper global (tests/Pest.php).

afterEach(fn () => Carbon::setTestNow());

/** Cria uma assinatura ligada a um tenant novo, com defaults sobrescrevíveis. */
function assinatura(array $attrs = []): Assinatura
{
    criarTenant('lojaum');

    return Assinatura::create(array_merge([
        'tenant_id' => 'lojaum',
        'plano' => 'profissional',
        'valor_mensal' => 99.90,
        'data_inicio' => '2026-05-01',
        'trial_dias' => 30,
        'status' => Assinatura::EM_TESTE,
    ], $attrs));
}

/** Adiciona uma fatura não paga vencida há $diasAtraso dias (relativo a hoje). */
function faturaVencidaHa(Assinatura $a, int $diasAtraso, string $status = Fatura::ABERTA): Fatura
{
    return $a->faturas()->create([
        'competencia' => Carbon::today()->startOfMonth(),
        'valor' => 99.90,
        'data_vencimento' => Carbon::today()->subDays($diasAtraso),
        'status' => $status,
    ]);
}

// ---- situacaoAcesso(): fronteiras --------------------------------------------

it('em_teste antes da 1ª cobrança', function () {
    Carbon::setTestNow('2026-06-25');
    // data_inicio 2026-06-20 + 30 dias = 2026-07-20 (futuro).
    $a = assinatura(['data_inicio' => '2026-06-20', 'trial_dias' => 30]);

    expect($a->situacaoAcesso())->toBe(Assinatura::EM_TESTE);
});

it('ativa após o trial sem fatura vencida', function () {
    Carbon::setTestNow('2026-06-25');
    $a = assinatura(['data_inicio' => '2026-05-01', 'trial_dias' => 30]); // 1ª cobrança 2026-05-31

    expect($a->situacaoAcesso())->toBe(Assinatura::ATIVA);

    // Fatura que vence no futuro não conta como atraso.
    $a->faturas()->create([
        'competencia' => '2026-06-01', 'valor' => 99.90,
        'data_vencimento' => '2026-07-10', 'status' => Fatura::ABERTA,
    ]);
    expect($a->situacaoAcesso())->toBe(Assinatura::ATIVA);
});

it('atrasada quando vencida há 10 dias', function () {
    Carbon::setTestNow('2026-06-25');
    $a = assinatura(['data_inicio' => '2026-04-01', 'trial_dias' => 0]);
    faturaVencidaHa($a, 10);

    expect($a->situacaoAcesso())->toBe(Assinatura::ATRASADA);
});

it('atrasada exatamente no dia 20 e suspensa no dia 21 (carência 20)', function () {
    Carbon::setTestNow('2026-06-25');

    $a = assinatura(['data_inicio' => '2026-04-01', 'trial_dias' => 0]);
    faturaVencidaHa($a, 20);
    expect($a->situacaoAcesso())->toBe(Assinatura::ATRASADA);

    $a->faturas()->delete();
    faturaVencidaHa($a, 21);
    expect($a->situacaoAcesso())->toBe(Assinatura::SUSPENSA);
});

it('a carência é lida de config/cobranca.php', function () {
    Carbon::setTestNow('2026-06-25');
    config(['cobranca.carencia_dias' => 5]);

    $a = assinatura(['data_inicio' => '2026-04-01', 'trial_dias' => 0]);

    faturaVencidaHa($a, 5);
    expect($a->situacaoAcesso())->toBe(Assinatura::ATRASADA); // dia 5 = limite

    $a->faturas()->delete();
    faturaVencidaHa($a, 6);
    expect($a->situacaoAcesso())->toBe(Assinatura::SUSPENSA); // dia 6 = passou
});

it('fatura paga não conta como atraso', function () {
    Carbon::setTestNow('2026-06-25');
    $a = assinatura(['data_inicio' => '2026-04-01', 'trial_dias' => 0]);
    faturaVencidaHa($a, 30, Fatura::PAGA); // vencida há 30 dias, mas PAGA

    expect($a->situacaoAcesso())->toBe(Assinatura::ATIVA);
});

it('cancelada é estado manual (sempre cancelada)', function () {
    Carbon::setTestNow('2026-06-25');
    $a = assinatura(['data_inicio' => '2026-04-01', 'trial_dias' => 0, 'status' => Assinatura::CANCELADA]);
    faturaVencidaHa($a, 40);

    expect($a->situacaoAcesso())->toBe(Assinatura::CANCELADA);
});

it('data_primeira_cobranca sobrescreve o cálculo do trial', function () {
    Carbon::setTestNow('2026-06-25');
    // trial já passou (data_inicio antiga), mas a 1ª cobrança combinada é no futuro.
    $a = assinatura([
        'data_inicio' => '2026-01-01', 'trial_dias' => 5,
        'data_primeira_cobranca' => '2026-07-01',
    ]);

    expect($a->primeiraCobranca()->toDateString())->toBe('2026-07-01')
        ->and($a->situacaoAcesso())->toBe(Assinatura::EM_TESTE);
});

// ---- snapshot de valor -------------------------------------------------------

it('valor_mensal é snapshot: mudar o catálogo não reescreve a assinatura', function () {
    $a = assinatura(['valor_mensal' => 99.90]);

    config(['planos.profissional.preco_mes' => 149.90]); // catálogo muda

    expect((float) Assinatura::find($a->id)->valor_mensal)->toBe(99.90);
});

// ---- comando de backfill -----------------------------------------------------

it('provisionar-assinaturas: dry-run não cria; --apply cria; idempotente', function () {
    criarTenant('lojaum')->aplicarPlano('profissional');
    criarTenant('lojadois'); // sem plano

    // Dry-run: nada criado.
    $this->artisan('nextgest:provisionar-assinaturas')->assertSuccessful();
    expect(Assinatura::count())->toBe(0);

    // Apply: uma por tenant.
    $this->artisan('nextgest:provisionar-assinaturas', ['--apply' => true])->assertSuccessful();
    expect(Assinatura::count())->toBe(2);

    $um = Assinatura::where('tenant_id', 'lojaum')->first();
    expect($um->plano)->toBe('profissional')
        ->and((float) $um->valor_mensal)->toBe(99.90)
        ->and($um->status)->toBe(Assinatura::EM_TESTE)
        ->and($um->trial_dias)->toBe((int) config('cobranca.trial_padrao_dias'));

    $dois = Assinatura::where('tenant_id', 'lojadois')->first();
    expect($dois->plano)->toBeNull()
        ->and((float) $dois->valor_mensal)->toBe(0.0);

    // Idempotente: rodar de novo não cria nada.
    $this->artisan('nextgest:provisionar-assinaturas', ['--apply' => true])->assertSuccessful();
    expect(Assinatura::count())->toBe(2);
});
