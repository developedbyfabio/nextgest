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

    // Versão do termo de risco (D80). Bump = re-exige aceite de todos os tenants.
    'termo_versao' => env('WA_TERMO_VERSAO', '1'),

    /*
    | Lembrete de serviço (D79) — automação real, com freios ANTI-BAN conservadores.
    | Tudo configurável por .env (não cravado). O teto por minuto/dia é o freio
    | primário (vale em fila sync OU async); o intervalo só espaça de fato com fila
    | ASSÍNCRONA (em produção, QUEUE_CONNECTION=database/redis + worker). Conservador
    | de propósito: WhatsApp não-oficial bane com rajada/volume.
    */
    'lembretes' => [
        // Antecedência padrão (min) — o dono pode sobrescrever por automação (D77).
        'antecedencia_min_padrao' => (int) env('WA_LEMBRETE_ANTECEDENCIA_MIN', 120),
        // Teto de envios ENFILEIRADOS por execução do comando (roda a cada minuto).
        'limite_por_minuto' => (int) env('WA_LEMBRETE_LIMITE_MINUTO', 4),
        // Espaçamento entre mensagens dentro do minuto (segundos) — fila assíncrona.
        'intervalo_segundos' => (int) env('WA_LEMBRETE_INTERVALO_SEG', 15),
        // Teto diário por tenant.
        'limite_por_dia' => (int) env('WA_LEMBRETE_LIMITE_DIA', 150),
    ],
];
