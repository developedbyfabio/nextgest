<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LogoutController;
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

// Webhooks dos gateways de pagamento (central, sem tenant na URL; o tenant é
// resolvido pelo conteúdo/assinatura do webhook na fase de Pagamentos).
Route::prefix('webhooks')->name('webhooks.')->group(function () {
    Route::post('/pagamentos/{gateway}', function (string $gateway) {
        // Stub: a fase de Pagamentos trata e despacha o webhook.
        return response()->json(['received' => true, 'gateway' => $gateway]);
    })->name('pagamentos');
});
