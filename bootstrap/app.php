<?php

use App\Http\Middleware\EscoparAutenticacaoPorTenant;
use App\Http\Middleware\GarantirTenantAtivo;
use App\Http\Middleware\InicializarTenancyArquivosLivewire;
use App\Http\Middleware\VerificaRecurso;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
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
        | Equivale ao grupo "web" + inicialização do tenancy por caminho. A sessão
        | usa o cookie padrão (compartilhado); o isolamento de login entre tenants
        | é feito por EscoparAutenticacaoPorTenant (após a sessão iniciar).
        |
        | O endpoint de update do Livewire é central; o tenancy é reaplicado lá via
        | persistent middleware (ver App\Providers\AppServiceProvider).
        */
        // O endpoint global de upload do Livewire recebe o
        // InicializarTenancyArquivosLivewire, mas o Laravel ordena por PRIORIDADE
        // e puxava o ThrottleRequests (que resolve $request->user()) para ANTES
        // dele — o usuário do tenant era procurado no banco central → 500. Aqui
        // garantimos que a tenancy inicialize ANTES do throttle.
        $middleware->prependToPriorityList(
            before: ThrottleRequests::class,
            prepend: InicializarTenancyArquivosLivewire::class,
        );

        // VULN-001: o EscoparAutenticacaoPorTenant precisa rodar ANTES do Authenticate.
        // O Authenticate é prioritário e rodava primeiro; quando a sessão era de OUTRO
        // tenant, o Escopar (que vinha depois) deslogava tarde demais e o componente
        // estourava com usuário nulo (500). Com ele antes, a sessão cross-tenant é
        // descartada e o Authenticate redireciona LIMPO para o login do tenant (302).
        $middleware->prependToPriorityList(
            before: Authenticate::class,
            prepend: EscoparAutenticacaoPorTenant::class,
        );

        $middleware->group('tenant', [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            InitializeTenancyByPath::class,
            // VULN-002: barra estabelecimento inativo (404) logo após carregar o tenant.
            GarantirTenantAtivo::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            ValidateCsrfToken::class,
            SubstituteBindings::class,
            EscoparAutenticacaoPorTenant::class,
        ]);

        // Gating de recursos por tenant: ->middleware('recurso:whatsapp')
        // (idem 'recurso:clube' / 'recurso:gateway'). Ver App\Http\Middleware\VerificaRecurso.
        $middleware->alias([
            'recurso' => VerificaRecurso::class,
        ]);

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
