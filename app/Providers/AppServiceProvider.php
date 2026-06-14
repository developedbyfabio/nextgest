<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /*
        | Livewire + multi-tenancy por caminho.
        |
        | O endpoint de update do Livewire é único e central (/livewire/update).
        | Para um componente de tenant funcionar nesse endpoint, registramos o
        | InitializeTenancyByPath como "persistent middleware": o Livewire reexecuta
        | esse middleware no update usando o caminho ORIGINAL da página (que contém
        | o {tenant}), reinicializando o tenancy. Assim o componente roda no contexto
        | do tenant sem precisar de uma rota de update por tenant (que quebraria a
        | rota central). A sessão é compartilhada (cookie único) — o isolamento de
        | login entre tenants é feito por App\Http\Middleware\EscoparAutenticacaoPorTenant.
        */
        Livewire::addPersistentMiddleware([
            InitializeTenancyByPath::class,
        ]);
    }
}
