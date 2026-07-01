<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Exigir perfil completo do cliente (gate)
    |--------------------------------------------------------------------------
    |
    | Quando true (default), o cliente logado com PERFIL INCOMPLETO (sem CPF OU sem
    | telefone) é levado a completar o cadastro antes de usar o portal — inclusive
    | clientes do Google (telefone '') e clientes ANTIGOS, no próximo acesso (fecha a
    | brecha de contas duplicadas e o telefone vazio que quebra o WhatsApp). false =
    | só a obrigatoriedade do autocadastro vale. O middleware
    | App\Http\Middleware\ExigirPerfilCompletoCliente é o ponto único (reusado pelo
    | Google). Chave mantida (`exigir_cpf_cliente`/`NEXTGEST_EXIGIR_CPF_CLIENTE`) por
    | compatibilidade — hoje liga/desliga o gate de PERFIL inteiro.
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
