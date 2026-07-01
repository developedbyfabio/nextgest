<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Pagamentos\MercadoPagoOAuthController;
use App\Http\Controllers\Webhooks\WebhookPagamentoController;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\EstabelecimentoDados;
use App\Livewire\Admin\Faturamento as AdminFaturamento;
use App\Livewire\Admin\OnboardingEstabelecimento;
use App\Livewire\Admin\TenantDetalhe as AdminTenantDetalhe;
use App\Livewire\Admin\Tenants as AdminTenants;
use App\Livewire\Auth\AdminLogin;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas CENTRAIS
|--------------------------------------------------------------------------
|
| Rodam no domínio central (nextgest.com.br) e NÃO passam pela resolução de
| tenant. Registradas antes das rotas de tenant, então têm precedência sobre o
| catch-all /{tenant}. Aqui ficam: landing, painel/login do super-admin (guard
| `admin`) e webhooks dos gateways.
|
*/

// Landing institucional da marca.
Route::get('/', function () {
    return view('landing');
})->name('landing');

// Login social com Google (D95) — CENTRAL (Google não aceita wildcard de path): UMA
// redirect URI. O tenant viaja pela sessão (?tenant={slug} no redirect). Ver
// App\Http\Controllers\Auth\GoogleController.
Route::prefix('auth/google')->name('auth.google.')->group(function () {
    Route::get('redirect', [GoogleController::class, 'redirect'])->name('redirect');
    Route::get('callback', [GoogleController::class, 'callback'])->name('callback');
});

// Super-admin (central, guard `admin`).
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', AdminLogin::class)
        ->middleware('guest:admin')
        ->name('login');

    Route::get('/', AdminDashboard::class)
        ->middleware('auth:admin')
        ->name('dashboard');

    Route::get('estabelecimentos', AdminTenants::class)
        ->middleware('auth:admin')
        ->name('tenants');

    // Onboarding guiado (wizard). Registrado ANTES de {tenantId} para que o
    // segmento "novo" não seja interpretado como id de tenant.
    Route::get('estabelecimentos/novo', OnboardingEstabelecimento::class)
        ->middleware('auth:admin')
        ->name('tenants.novo');

    Route::get('estabelecimentos/{tenantId}', AdminTenantDetalhe::class)
        ->middleware('auth:admin')
        ->name('tenant.detalhe');

    // Dados cadastrais (cadastro central do estabelecimento — D57). Caminho mais
    // específico que {tenantId}, então não conflita com o detalhe.
    Route::get('estabelecimentos/{tenantId}/dados', EstabelecimentoDados::class)
        ->middleware('auth:admin')
        ->name('tenant.dados');

    // Faturamento (cobrança SaaS salão → Nextgest — D59). Caminho mais específico
    // que {tenantId}, sem conflito com o detalhe.
    Route::get('estabelecimentos/{tenantId}/faturamento', AdminFaturamento::class)
        ->middleware('auth:admin')
        ->name('tenant.faturamento');

    Route::post('sair', [LogoutController::class, 'admin'])
        ->middleware('auth:admin')
        ->name('logout');
});

// Webhooks dos gateways de pagamento (central, sem tenant na URL). Público; a
// validação de assinatura é no controller (D62). CSRF já é dispensado em webhooks/*.
Route::prefix('webhooks')->name('webhooks.')->group(function () {
    Route::post('/pagamentos/{gateway}', [WebhookPagamentoController::class, 'handle'])
        ->name('pagamentos');
});

// Callback OAuth do gateway do tenant (Modelo A, D78). Central, fixo (redirect_uri
// único do app MP). Sem tenant na URL — o tenant vem do `state` (validado contra a
// sessão no controller). `web` = sessão disponível p/ o anti-CSRF do state.
Route::get('oauth/mercadopago/callback', [MercadoPagoOAuthController::class, 'callback'])
    ->name('oauth.mercadopago.callback');
