<?php

declare(strict_types=1);

use App\Livewire\Admin\Faturamento;
use App\Models\Assinatura;
use App\Models\Estabelecimento;
use App\Services\MercadoPago\MercadoPagoException;
use App\Services\MercadoPago\PreapprovalClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/*
| Fase 5a (D61) — adesão recorrente via MP Preapproval (sandbox). A API é SEMPRE
| mockada (Http::fake) — a chamada real é validação manual no sandbox. Cobre o payload
| do fluxo "pending" (sem card_token_id), persistência, idempotência e erro tratado.
*/

beforeEach(function () {
    config(['mercadopago.access_token' => 'TEST-fake-token', 'mercadopago.base_url' => 'https://api.mercadopago.com', 'mercadopago.back_url' => 'https://nextgest.com.br']);
    Carbon::setTestNow('2026-06-25');
});

afterEach(fn () => Carbon::setTestNow());

function assinaturaMp(string $slug = 'lojaum', float $valor = 99.90, string $donoEmail = 'dono@lojaum.com'): Assinatura
{
    criarTenant($slug);
    Estabelecimento::create(['tenant_id' => $slug, 'nome_fantasia' => 'Loja', 'dono_email' => $donoEmail]);

    return Assinatura::create([
        'tenant_id' => $slug, 'plano' => 'profissional', 'valor_mensal' => $valor,
        'data_inicio' => '2026-07-01', 'trial_dias' => 30, 'status' => Assinatura::EM_TESTE,
    ]);
}

// ---- Client: payload do fluxo "pending" -------------------------------------

it('monta o payload correto (status pending, sem card_token_id, mensal/BRL) e retorna init_point', function () {
    Http::fake([
        '*/preapproval' => Http::response([
            'id' => 'pa-123', 'init_point' => 'https://www.mercadopago.com/subscriptions/checkout?preapproval_id=pa-123', 'status' => 'pending',
        ], 201),
    ]);

    $a = assinaturaMp();
    $out = app(PreapprovalClient::class)->criarPreapproval($a, 'dono@lojaum.com');

    expect($out['id'])->toBe('pa-123')
        ->and($out['status'])->toBe('pending')
        ->and($out['init_point'])->toContain('checkout');

    Http::assertSent(function ($req) {
        $b = $req->data();

        return str_ends_with($req->url(), '/preapproval')
            && $req->method() === 'POST'
            && $b['status'] === 'pending'
            && ! array_key_exists('card_token_id', $b)             // fluxo sem cartão no front
            && $b['payer_email'] === 'dono@lojaum.com'
            && $b['external_reference'] === 'lojaum'
            && $b['auto_recurring']['frequency'] === 1
            && $b['auto_recurring']['frequency_type'] === 'months'
            && $b['auto_recurring']['currency_id'] === 'BRL'
            && $b['auto_recurring']['transaction_amount'] === 99.90
            && ! empty($b['auto_recurring']['start_date'])
            && str_starts_with($b['back_url'], 'https://');
    });
});

it('recusa criar recorrência com valor zero (sem chamar a API)', function () {
    Http::fake();
    $a = assinaturaMp(valor: 0);

    expect(fn () => app(PreapprovalClient::class)->criarPreapproval($a, 'dono@lojaum.com'))
        ->toThrow(MercadoPagoException::class);

    Http::assertNothingSent();
});

it('erro HTTP do MP vira MercadoPagoException (mensagem segura, sem token)', function () {
    Http::fake(['*/preapproval' => Http::response(['message' => 'invalid_transaction_amount'], 400)]);
    $a = assinaturaMp();

    expect(fn () => app(PreapprovalClient::class)->criarPreapproval($a, 'dono@lojaum.com'))
        ->toThrow(MercadoPagoException::class);
});

// ---- UI: ação na tela Faturamento -------------------------------------------

it('ativa a cobrança automática: salva referência do MP e o link de adesão', function () {
    Http::fake([
        '*/preapproval' => Http::response([
            'id' => 'pa-999', 'init_point' => 'https://mp/checkout?preapproval_id=pa-999', 'status' => 'pending',
        ], 201),
    ]);
    assinaturaMp();

    $this->actingAs(admin(), 'admin');
    Livewire::test(Faturamento::class, ['tenantId' => 'lojaum'])
        ->call('ativarCobrancaAutomatica')
        ->assertHasNoErrors();

    $a = Assinatura::where('tenant_id', 'lojaum')->first();
    expect($a->mp_preapproval_id)->toBe('pa-999')
        ->and($a->mp_status)->toBe('pending')
        ->and($a->link_adesao)->toContain('checkout')
        ->and($a->cobranca_automatica)->toBeTrue();
});

it('é idempotente: clicar de novo não cria recorrência nova', function () {
    Http::fake([
        '*/preapproval' => Http::response(['id' => 'pa-1', 'init_point' => 'https://mp/x', 'status' => 'pending'], 201),
    ]);
    assinaturaMp();

    $this->actingAs(admin(), 'admin');
    $c = Livewire::test(Faturamento::class, ['tenantId' => 'lojaum']);
    $c->call('ativarCobrancaAutomatica');
    $c->call('ativarCobrancaAutomatica'); // 2º clique

    Http::assertSentCount(1); // só uma chamada à API
    expect(Assinatura::where('tenant_id', 'lojaum')->first()->mp_preapproval_id)->toBe('pa-1');
});

it('erro da API é tratado (sem 500, sem salvar referência)', function () {
    Http::fake(['*/preapproval' => Http::response(['message' => 'erro'], 400)]);
    assinaturaMp();

    $this->actingAs(admin(), 'admin');
    Livewire::test(Faturamento::class, ['tenantId' => 'lojaum'])
        ->call('ativarCobrancaAutomatica')
        ->assertHasNoErrors(); // toast de erro, sem exception

    $a = Assinatura::where('tenant_id', 'lojaum')->first();
    expect($a->mp_preapproval_id)->toBeNull()
        ->and($a->cobranca_automatica)->toBeFalse();
});

it('bloqueia ativação sem e-mail do dono cadastrado', function () {
    Http::fake();
    assinaturaMp(donoEmail: ''); // estabelecimento sem dono_email

    $this->actingAs(admin(), 'admin');
    Livewire::test(Faturamento::class, ['tenantId' => 'lojaum'])
        ->call('ativarCobrancaAutomatica');

    Http::assertNothingSent();
    expect(Assinatura::where('tenant_id', 'lojaum')->first()->cobranca_automatica)->toBeFalse();
});
