<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LogoutController;
use App\Livewire\Auth\ClienteLogin;
use App\Livewire\Auth\ClienteRegistrar;
use App\Livewire\Auth\PainelLogin;
use App\Livewire\Painel\Agenda\Index as AgendaIndex;
use App\Livewire\Painel\Bloqueios\Index as BloqueiosIndex;
use App\Livewire\Painel\Dashboard as PainelDashboard;
use App\Livewire\Painel\Equipe\Horarios as EquipeHorarios;
use App\Livewire\Painel\Equipe\Index as EquipeIndex;
use App\Livewire\Painel\Papeis\Index as PapeisIndex;
use App\Livewire\Painel\Servicos\Index as ServicosIndex;
use App\Livewire\Painel\Unidades\Index as UnidadesIndex;
use App\Livewire\Portal\Agendar as PortalAgendar;
use App\Livewire\Portal\Home as PortalHome;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas de TENANT (identificação por caminho)
|--------------------------------------------------------------------------
|
| Tudo sob o primeiro segmento da URL: nextgest.com.br/{tenant}/... — onde
| {tenant} é o slug/id do estabelecimento. O grupo "tenant" (bootstrap/app.php)
| inicializa o tenancy antes da sessão e escopa a sessão por tenant.
|
| {tenant} é restringido por regex para nunca casar com um slug reservado
| (config/nextgest.php) — assim /admin, /login (central), /api etc. ficam para o
| app central (routes/web.php).
|
*/

$reserved = implode('|', array_map(
    static fn (string $slug): string => preg_quote($slug, '/'),
    config('nextgest.reserved_slugs', [])
));

$tenantSlugPattern = '(?!('.$reserved.')$)[a-z0-9][a-z0-9-]*';

Route::middleware(['tenant'])
    ->prefix('{tenant}')
    ->where(['tenant' => $tenantSlugPattern])
    ->group(function () {
        /*
        | Portal do cliente (guard `cliente`) — mobile-first.
        */
        Route::get('/', PortalHome::class)->name('tenant.home');

        Route::middleware('guest:cliente')->group(function () {
            Route::get('login', ClienteLogin::class)->name('cliente.login');
            Route::get('registrar', ClienteRegistrar::class)->name('cliente.registrar');
        });

        Route::get('agendar', PortalAgendar::class)
            ->middleware('auth:cliente')
            ->name('cliente.agendar');

        Route::post('sair', [LogoutController::class, 'cliente'])
            ->middleware('auth:cliente')
            ->name('cliente.logout');

        /*
        | Painel da equipe (guard `web`).
        */
        Route::prefix('painel')->name('painel.')->group(function () {
            Route::get('login', PainelLogin::class)
                ->middleware('guest:web')
                ->name('login');

            Route::middleware('auth:web')->group(function () {
                Route::get('/', PainelDashboard::class)->name('dashboard');

                Route::post('sair', [LogoutController::class, 'painel'])->name('logout');

                // Agenda: acesso por ver_agenda OU ver_agenda_propria (checado no componente).
                Route::get('agenda', AgendaIndex::class)->name('agenda');

                // Cadastros (1B). Cada página exige a permissão de gestão correspondente;
                // ações de criar/editar são reconferidas dentro dos componentes.
                Route::get('unidades', UnidadesIndex::class)
                    ->middleware('can:gerir_unidades')
                    ->name('unidades');

                Route::get('servicos', ServicosIndex::class)
                    ->middleware('can:editar_servico')
                    ->name('servicos');

                Route::get('bloqueios', BloqueiosIndex::class)
                    ->middleware('can:gerir_agenda')
                    ->name('bloqueios');

                Route::get('equipe', EquipeIndex::class)
                    ->middleware('can:editar_usuario')
                    ->name('equipe');

                Route::get('equipe/{user}/horarios', EquipeHorarios::class)
                    ->middleware('can:editar_usuario')
                    ->name('equipe.horarios');

                Route::get('papeis', PapeisIndex::class)
                    ->middleware('can:editar_permissoes')
                    ->name('papeis');
            });
        });
    });
