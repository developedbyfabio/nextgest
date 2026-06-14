<?php

use App\Http\Middleware\ScopeSessionToTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
        | Grupo de middleware dos TENANTS (identificação por caminho).
        |
        | Equivale ao grupo "web", porém com a inicialização do tenancy ANTES da
        | sessão, e com o escopo de sessão por tenant logo em seguida. As rotas
        | de tenant usam SOMENTE este grupo (não o "web"), para não iniciar a
        | sessão antes de o tenant ser conhecido.
        */
        $middleware->group('tenant', [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            InitializeTenancyByPath::class,
            ScopeSessionToTenant::class,
            StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

        // Garante que o escopo de sessão rode imediatamente antes de StartSession
        // (InitializeTenancyByPath já é colocado como prioridade mais alta pelo
        // TenancyServiceProvider).
        $middleware->prependToPriorityList(
            StartSession::class,
            ScopeSessionToTenant::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
