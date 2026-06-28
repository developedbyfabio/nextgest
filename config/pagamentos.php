<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Gateway de pagamento do TENANT — Modelo A (direto pro dono) — D78
|--------------------------------------------------------------------------
|
| OAuth do Mercado Pago: o DONO autoriza no site do MP e o Nextgest recebe um
| token PARA AGIR NA CONTA DO SALÃO (o dinheiro cai no salão; o Nextgest orquestra,
| não intermedia). NÃO confundir com config/mercadopago.php (cobrança SaaS do
| ADMIN, dinheiro salão → Nextgest) — são contextos distintos.
|
| SEGREDO: client_id/client_secret do APP Nextgest só vêm do .env (placeholders por
| ora; o Fabio registra o app no MP depois). Nunca em banco/log/tela. O token do
| salão fica CIFRADO no cofre do tenant (gateways_pagamento.credenciais).
|
*/

return [
    'mercadopago' => [
        'client_id' => env('MP_OAUTH_CLIENT_ID'),
        'client_secret' => env('MP_OAUTH_CLIENT_SECRET'),
        // URL FIXA registrada no app MP (a rota central de callback desta fatia).
        'redirect_uri' => env('MP_OAUTH_REDIRECT_URI'),

        'auth_url' => env('MP_OAUTH_AUTH_URL', 'https://auth.mercadopago.com/authorization'),
        'token_url' => env('MP_OAUTH_TOKEN_URL', 'https://api.mercadopago.com/oauth/token'),
        'api_url' => env('MP_OAUTH_API_URL', 'https://api.mercadopago.com'),

        'timeout' => (int) env('MP_OAUTH_TIMEOUT', 15),
    ],
];
