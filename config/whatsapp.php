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
    | Modo Aquecimento (D82) — DEFAULTS conservadores da curva de volume p/ número novo.
    | O teto efetivo do dia = min(teto normal, teto do aquecimento do dia). Conta a partir
    | de `whatsapp_config.conectado_em` (dia 1 = dia da conexão). Cada fase: até o dia
    | `ate_dia`, o teto diário é `limite_dia`. Passou a última fase → aquecimento concluído
    | (vale o teto normal). Broadcast (envio em massa) só liberado a partir de
    | `broadcast_a_partir_dia`. O tenant pode sobrescrever em whatsapp_config.aquecimento.
    */
    'aquecimento' => [
        'ativo' => (bool) env('WA_AQUECIMENTO_ATIVO', true),
        'broadcast_a_partir_dia' => (int) env('WA_AQUECIMENTO_BROADCAST_DIA', 11),
        'fases' => [
            ['ate_dia' => 2, 'limite_dia' => 10],
            ['ate_dia' => 6, 'limite_dia' => 20],
            ['ate_dia' => 13, 'limite_dia' => 40],
            ['ate_dia' => 21, 'limite_dia' => 80],
        ],
    ],

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

    /*
    | Avaliação pós-serviço (D81) — envia link da avaliação X min após a conclusão.
    | Reusa os freios anti-ban dos lembretes (mesmos tetos/intervalo). `janela_buffer_min`
    | evita inundar atendimentos antigos quando a automação é ligada. `link_validade_dias`:
    | validade do link assinado da avaliação.
    */
    'avaliacao' => [
        'apos_min_padrao' => (int) env('WA_AVALIACAO_APOS_MIN', 120),
        'janela_buffer_min' => (int) env('WA_AVALIACAO_BUFFER_MIN', 60),
        'link_validade_dias' => (int) env('WA_AVALIACAO_LINK_DIAS', 7),
    ],
];
