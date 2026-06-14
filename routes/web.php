<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LogoutController;
use App\Livewire\Admin\Dashboard as AdminDashboard;
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

// Landing institucional.
Route::get('/', function () {
    return view('welcome');
})->name('landing');

// Super-admin (central, guard `admin`).
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', AdminLogin::class)
        ->middleware('guest:admin')
        ->name('login');

    Route::get('/', AdminDashboard::class)
        ->middleware('auth:admin')
        ->name('dashboard');

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
