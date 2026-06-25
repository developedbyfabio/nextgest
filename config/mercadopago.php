<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Mercado Pago — assinatura recorrente do SaaS (Preapproval) — D61
|--------------------------------------------------------------------------
|
| Token da conta do Nextgest (plataforma), usado para a cobrança recorrente
| salão → Nextgest. NÃO confundir com as credenciais por tenant do Clube
| (gateways_pagamento, cliente → salão).
|
| SEGREDO: o access token vem SEMPRE do .env (env aqui é a única leitura
| permitida; o resto do app lê via config('mercadopago.access_token')). Nunca
| cravar no código, logar ou expor. Nesta fase, só o token de TESTE (sandbox).
|
| back_url: para onde o MP redireciona o pagador após a adesão. Precisa ser uma
| URL https válida (em dev usamos um destino https estável; o retorno público de
| verdade é tema de produção — a confirmação das cobranças é por webhook, 5b).
|
*/

return [
    'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),

    'base_url' => env('MERCADOPAGO_BASE_URL', 'https://api.mercadopago.com'),

    'back_url' => env('MERCADOPAGO_BACK_URL', 'https://nextgest.com.br'),

    // Timeout das chamadas HTTP à API (segundos).
    'timeout' => (int) env('MERCADOPAGO_TIMEOUT', 15),
];
