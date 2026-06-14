<?php

use App\Http\Middleware\ScopeSessionToTenant;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException;
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
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            InitializeTenancyByPath::class,
            ScopeSessionToTenant::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            ValidateCsrfToken::class,
            SubstituteBindings::class,
        ]);

        // Garante que o escopo de sessão rode imediatamente antes de StartSession
        // (InitializeTenancyByPath já é colocado como prioridade mais alta pelo
        // TenancyServiceProvider).
        $middleware->prependToPriorityList(
            StartSession::class,
            ScopeSessionToTenant::class,
        );

        // Webhooks de gateways externos não enviam token CSRF.
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);

        // Não autenticado → login da ÁREA correta (admin / painel / portal).
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.login');
            }

            if (tenancy()->initialized) {
                $tenantId = tenant('id');

                if ($request->is('*/painel') || $request->is('*/painel/*')) {
                    return route('painel.login', ['tenant' => $tenantId]);
                }

                return route('cliente.login', ['tenant' => $tenantId]);
            }

            return route('admin.login');
        });

        // Já autenticado tentando acessar tela de login → manda para a ÁREA.
        $middleware->redirectUsersTo(function (Request $request) {
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.dashboard');
            }

            if (tenancy()->initialized) {
                $tenantId = tenant('id');

                if ($request->is('*/painel') || $request->is('*/painel/*')) {
                    return route('painel.dashboard', ['tenant' => $tenantId]);
                }

                return route('tenant.home', ['tenant' => $tenantId]);
            }

            return route('admin.dashboard');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Tenant inexistente / não identificado → 404 amigável (em vez de 500).
        $exceptions->render(function (TenantCouldNotBeIdentifiedException $e, Request $request) {
            abort(404, 'Estabelecimento não encontrado.');
        });
    })->create();
