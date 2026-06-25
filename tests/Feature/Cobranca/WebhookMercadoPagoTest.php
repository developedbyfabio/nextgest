<?php

declare(strict_types=1);

use App\Models\Assinatura;
use App\Models\Fatura;
use App\Models\WebhookEvento;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

/*
| Fase 5b (D62) — webhook do Mercado Pago. SEGURANÇA primeiro: sem assinatura válida,
| rejeita (401). Idempotente (dedupe). Sempre CONSULTA a API (mockada nos testes) antes
| de espelhar o estado. Aprovado → fatura paga; recusado → vencida na data da falha (4c).
*/

const SEGREDO = 'TEST-webhook-secret';

beforeEach(function () {
    config([
        'mercadopago.webhook_secret' => SEGREDO,
        'mercadopago.access_token' => 'TEST-token',
        'mercadopago.base_url' => 'https://api.mercadopago.com',
    ]);
    Carbon::setTestNow('2026-06-25');
});

afterEach(fn () => Carbon::setTestNow());

function assinaturaRecorrente(string $slug = 'lojaum', string $preapproval = 'PA-1'): Assinatura
{
    criarTenant($slug);

    return Assinatura::create([
        'tenant_id' => $slug, 'plano' => 'profissional', 'valor_mensal' => 99.90,
        'data_inicio' => '2026-04-01', 'trial_dias' => 0, 'status' => Assinatura::ATIVA,
        'mp_preapproval_id' => $preapproval, 'cobranca_automatica' => true,
    ]);
}

/** POST assinado no webhook. $opts: secret, request_id, ts, v1 (forçar inválido), evento_id. */
function postWebhook(string $tipo, string $dataId, array $opts = [])
{
    $secret = $opts['secret'] ?? SEGREDO;
    $reqId = $opts['request_id'] ?? 'req-1';
    $ts = $opts['ts'] ?? 1700000000;
    $manifest = 'id:'.strtolower($dataId).';request-id:'.$reqId.';ts:'.$ts.';';
    $v1 = $opts['v1'] ?? hash_hmac('sha256', $manifest, $secret);

    return test()->postJson('/webhooks/pagamentos/mercadopago',
        ['id' => $opts['evento_id'] ?? 'notif-1', 'type' => $tipo, 'action' => $tipo, 'data' => ['id' => $dataId]],
        ['x-signature' => "ts={$ts},v1={$v1}", 'x-request-id' => $reqId],
    );
}

function fakePagamento(string $status, array $extra = []): void
{
    Http::fake([
        'api.mercadopago.com/authorized_payments/search*' => Http::response(['results' => $extra['results'] ?? []], 200),
        'api.mercadopago.com/authorized_payments/*' => Http::response(array_merge([
            'id' => 'AP-1', 'preapproval_id' => 'PA-1', 'transaction_amount' => 99.90,
            'debit_date' => '2026-06-10', 'payment' => ['id' => 'PAY-1', 'status' => $status, 'status_detail' => 'x'],
        ], $extra['pagamento'] ?? []), 200),
        'api.mercadopago.com/preapproval/*' => Http::response(['id' => 'PA-1', 'status' => $extra['preapproval_status'] ?? 'authorized'], 200),
    ]);
}

// ---- SEGURANÇA: validação da assinatura -------------------------------------

it('rejeita (401) webhook com assinatura inválida, sem processar', function () {
    fakePagamento('approved');
    assinaturaRecorrente();

    postWebhook('subscription_authorized_payment', 'AP-1', ['v1' => 'hashfalso'])
        ->assertStatus(401);

    expect(WebhookEvento::count())->toBe(0)
        ->and(Fatura::count())->toBe(0);
    Http::assertNothingSent(); // nem consultou a API
});

it('rejeita (401) quando não há segredo configurado', function () {
    config(['mercadopago.webhook_secret' => null]);
    assinaturaRecorrente();

    postWebhook('subscription_authorized_payment', 'AP-1')->assertStatus(401);
});

// ---- Cobrança aprovada → fatura paga ----------------------------------------

it('pagamento aprovado: cria fatura paga (espelho) e mantém ativa', function () {
    fakePagamento('approved');
    $a = assinaturaRecorrente();

    postWebhook('subscription_authorized_payment', 'AP-1')->assertOk();

    $f = Fatura::where('assinatura_id', $a->id)->first();
    expect($f)->not->toBeNull()
        ->and($f->status)->toBe(Fatura::PAGA)
        ->and($f->forma_pagamento)->toBe('mercadopago')
        ->and($f->gateway_referencia)->toBe('PAY-1')
        ->and($f->competencia->format('Y-m'))->toBe('2026-06')
        ->and((float) $f->valor)->toBe(99.90)
        ->and($a->fresh()->situacaoAcesso())->toBe(Assinatura::ATIVA);
});

it('é idempotente: o mesmo evento 2x não duplica fatura nem registro', function () {
    fakePagamento('approved');
    assinaturaRecorrente();

    postWebhook('subscription_authorized_payment', 'AP-1')->assertOk();
    postWebhook('subscription_authorized_payment', 'AP-1', ['evento_id' => 'notif-2'])->assertOk(); // reenvio

    expect(Fatura::count())->toBe(1)
        ->and(WebhookEvento::where('evento_id', 'authorized_payment:AP-1')->count())->toBe(1);
});

// ---- Cobrança recusada → vencida na data da falha (4c) ----------------------

it('pagamento recusado: fatura vencida NA DATA DA FALHA dispara a carência', function () {
    fakePagamento('rejected');
    $a = assinaturaRecorrente();

    postWebhook('subscription_authorized_payment', 'AP-1')->assertOk();

    $f = Fatura::where('assinatura_id', $a->id)->first();
    expect($f->status)->toBe(Fatura::ABERTA)
        ->and($f->data_pagamento)->toBeNull()
        ->and($f->data_vencimento->toDateString())->toBe('2026-06-25'); // data da falha (hoje)

    // Conta 20 dias da falha: +10 dias = atrasada; +21 dias = suspensa (encaixe 4c).
    expect($a->fresh()->situacaoAcesso(Carbon::parse('2026-07-05')))->toBe(Assinatura::ATRASADA)
        ->and($a->fresh()->situacaoAcesso(Carbon::parse('2026-07-16')))->toBe(Assinatura::SUSPENSA);
});

// ---- Recorrência: status -----------------------------------------------------

it('subscription_preapproval authorized → mp_status authorized', function () {
    fakePagamento('approved', ['preapproval_status' => 'authorized']);
    $a = assinaturaRecorrente();

    postWebhook('subscription_preapproval', 'PA-1')->assertOk();

    expect($a->fresh()->mp_status)->toBe('authorized');
});

it('subscription_preapproval cancelled → assinatura cancelada (bloqueia 4c)', function () {
    fakePagamento('approved', ['preapproval_status' => 'cancelled']);
    $a = assinaturaRecorrente();

    postWebhook('subscription_preapproval', 'PA-1')->assertOk();

    expect($a->fresh()->status)->toBe(Assinatura::CANCELADA)
        ->and($a->fresh()->situacaoAcesso())->toBe(Assinatura::CANCELADA);
});

// ---- Robustez ----------------------------------------------------------------

it('pagamento de preapproval desconhecido: ack 200 sem criar fatura', function () {
    Http::fake([
        'api.mercadopago.com/authorized_payments/*' => Http::response([
            'id' => 'AP-X', 'preapproval_id' => 'PA-DESCONHECIDO', 'transaction_amount' => 10,
            'payment' => ['id' => 'p', 'status' => 'approved'],
        ], 200),
    ]);
    assinaturaRecorrente(); // PA-1, não casa com PA-DESCONHECIDO

    postWebhook('subscription_authorized_payment', 'AP-X')->assertOk();
    expect(Fatura::count())->toBe(0);
});

it('falha ao consultar a API → 500 (MP reenvia) e nada é registrado', function () {
    Http::fake(['api.mercadopago.com/authorized_payments/*' => Http::response([], 500)]);
    assinaturaRecorrente();

    postWebhook('subscription_authorized_payment', 'AP-1')->assertStatus(500);

    expect(WebhookEvento::count())->toBe(0)
        ->and(Fatura::count())->toBe(0);
});

// ---- Reconciliação (rede de segurança) --------------------------------------

it('reconciliação sincroniza um pagamento que o webhook perdeu', function () {
    fakePagamento('approved', ['results' => [['id' => 'AP-1']]]);
    $a = assinaturaRecorrente();

    $this->artisan('nextgest:reconciliar-assinaturas')->assertSuccessful();

    $f = Fatura::where('assinatura_id', $a->id)->first();
    expect($f)->not->toBeNull()
        ->and($f->status)->toBe(Fatura::PAGA)
        ->and($a->fresh()->mp_status)->toBe('authorized');
});
