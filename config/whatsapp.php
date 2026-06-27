<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| WhatsApp via Evolution API — infra (WhatsApp Fatia 1, D75)
|--------------------------------------------------------------------------
|
| Uma Evolution ÚNICA (Fatia 0) atende todos os salões; cada salão é uma
| INSTÂNCIA dentro dela. Estas credenciais são de INFRA (administram a
| Evolution inteira) e ficam SÓ aqui, lidas do .env — NUNCA no banco do tenant.
|
| - base_url: a Evolution local (dev: http://127.0.0.1:8088, fechada no localhost).
| - api_key:  a API key GLOBAL da Evolution (segredo de infra). Só env; nunca
|             logada, exposta ou gravada em banco de tenant.
|
| O que identifica o SALÃO (nome da instância + token daquela instância) mora no
| banco do TENANT (whatsapp_config), não aqui.
|
*/

return [
    'base_url' => env('EVOLUTION_BASE_URL', 'http://127.0.0.1:8088'),

    'api_key' => env('EVOLUTION_API_KEY'),

    // Timeout das chamadas HTTP à Evolution (segundos).
    'timeout' => (int) env('EVOLUTION_TIMEOUT', 15),

    // Prefixo do nome da instância por tenant (garante unicidade na Evolution
    // compartilhada — ex.: ng_barbeariateste).
    'prefixo_instancia' => env('EVOLUTION_PREFIXO_INSTANCIA', 'ng_'),
];
