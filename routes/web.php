<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas CENTRAIS
|--------------------------------------------------------------------------
|
| Rodam no domínio central (nextgest.com.br) e NÃO passam pela resolução de
| tenant. São registradas antes das rotas de tenant, então têm precedência
| sobre o catch-all /{tenant}. Aqui ficam: landing, painel do super-admin
| (/admin), login do super-admin e webhooks dos gateways.
|
*/

// Landing institucional.
Route::get('/', function () {
    return view('welcome');
})->name('landing');

// Painel do super-admin (central). Implementado em fase posterior.
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', function () {
        return 'Painel do super-admin (central) — em construção.';
    })->name('dashboard');
});

// Webhooks dos gateways de pagamento (central, sem tenant na URL; o tenant é
// resolvido pelo conteúdo/assinatura do webhook na fase de Pagamentos).
Route::prefix('webhooks')->name('webhooks.')->group(function () {
    Route::post('/pagamentos/{gateway}', function (string $gateway) {
        // Stub: a fase de Pagamentos trata e despacha o webhook.
        return response()->json(['received' => true, 'gateway' => $gateway]);
    })->name('pagamentos');
});
