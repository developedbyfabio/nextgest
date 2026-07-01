<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Exigir CPF do cliente (gate)
    |--------------------------------------------------------------------------
    |
    | Quando true (default), o cliente logado SEM CPF é levado a completar o
    | cadastro antes de usar o portal — inclusive clientes ANTIGOS sem CPF, no
    | próximo login (é o que fecha a brecha de contas duplicadas). false = só a
    | obrigatoriedade do autocadastro vale (existentes sem CPF não são forçados).
    | O middleware App\Http\Middleware\ExigirCpfCliente e o fluxo do Google
    | (fatia seguinte) reutilizam este ponto único.
    |
    */
    'exigir_cpf_cliente' => (bool) env('NEXTGEST_EXIGIR_CPF_CLIENTE', true),

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
        'auth', // rotas centrais de login social (D95): /auth/google/*
        'login',
        'logout',
        'register',
        'webhooks',
        'oauth', // callback OAuth do gateway do tenant (D78)
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
