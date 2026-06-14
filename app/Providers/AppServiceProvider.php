<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\InitializeTenancyByPathQuandoPresente;
use App\Http\Middleware\ScopeSessionToTenant;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /*
        | Rota de update do Livewire ciente do tenant (identificação por caminho).
        |
        | O parâmetro {tenant} é opcional:
        |  - páginas centrais  → /livewire/update (sem tenant);
        |  - páginas de tenant → /{tenant}/livewire/update (URL::defaults injeta o
        |    tenant na geração — ver ScopeSessionToTenant).
        |
        | Assim o update roda no MESMO contexto (tenant + cookie de sessão) da
        | página, evitando sessão vazia / token CSRF inválido (419). O Livewire
        | adiciona automaticamente o grupo `web` e o RequireLivewireHeaders.
        */
        if ($this->app->runningUnitTests()) {
            return;
        }

        Livewire::setUpdateRoute(function ($handle) {
            return Route::post('/{tenant?}/livewire/update', $handle)
                ->middleware([
                    InitializeTenancyByPathQuandoPresente::class,
                    ScopeSessionToTenant::class,
                ])
                ->where('tenant', '[a-z0-9-]+');
        });
    }
}
