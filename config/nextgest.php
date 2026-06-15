<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Slugs reservados
    |--------------------------------------------------------------------------
    |
    | Primeiro segmento da URL que NÃO pode virar tenant. Servem ao app central
    | (landing, painel do super-admin, login, webhooks, assets...) e a usos
    | técnicos. A criação de tenant é bloqueada se o slug estiver nesta lista
    | (ver App\Services\Tenancy\TenantCreator) e a rota de tenant tem uma
    | restrição (regex) que impede esses caminhos de serem resolvidos como
    | tenant.
    |
    */
    'reserved_slugs' => [
        'admin',
        'api',
        'login',
        'logout',
        'register',
        'webhooks',
        'assets',
        'storage',
        'vendor',
        'livewire',
        'nextgest',
        'app',
        'painel',
        'super-admin',
        'up', // health check do Laravel
        'novo', // usado pela rota /admin/estabelecimentos/novo (onboarding)
    ],

];
