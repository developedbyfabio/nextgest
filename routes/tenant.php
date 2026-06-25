<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\SuporteController;
use App\Http\Controllers\TenantArquivoController;
use App\Http\Middleware\ForcarTrocaSenha;
use App\Livewire\Auth\ClienteLogin;
use App\Livewire\Auth\ClienteRegistrar;
use App\Livewire\Auth\DesafioDoisFatores;
use App\Livewire\Auth\PainelLogin;
use App\Livewire\Auth\TrocarSenha;
use App\Livewire\Painel\Agenda\Index as AgendaIndex;
use App\Livewire\Painel\Avaliacoes\Index as AvaliacoesIndex;
use App\Livewire\Painel\Aparencia\Editar as AparenciaEditar;
use App\Livewire\Painel\Bloqueios\Index as BloqueiosIndex;
use App\Livewire\Painel\Clube\Index as ClubeIndex;
use App\Livewire\Painel\Comissoes\Index as ComissoesIndex;
use App\Livewire\Painel\Dashboard as PainelDashboard;
use App\Livewire\Painel\Equipe\Horarios as EquipeHorarios;
use App\Livewire\Painel\Equipe\Index as EquipeIndex;
use App\Livewire\Painel\Financeiro\Index as FinanceiroIndex;
use App\Livewire\Painel\Funcionamento\Index as FuncionamentoIndex;
use App\Livewire\Painel\Indicadores as IndicadoresIndex;
use App\Livewire\Painel\Integracoes\Index as IntegracoesIndex;
use App\Livewire\Painel\Integracoes\MercadoPago as IntegracaoMercadoPago;
use App\Livewire\Painel\Integracoes\Whatsapp as IntegracaoWhatsapp;
use App\Livewire\Painel\Kanban\Index as KanbanIndex;
use App\Livewire\Painel\Papeis\Index as PapeisIndex;
use App\Livewire\Painel\Produtos\Index as ProdutosIndex;
use App\Livewire\Painel\Seguranca\DoisFatores as SegurancaDoisFatores;
use App\Livewire\Painel\Servicos\Index as ServicosIndex;
use App\Livewire\Painel\Unidades\Index as UnidadesIndex;
use App\Livewire\Painel\Vendas\Detalhe as VendasDetalhe;
use App\Livewire\Painel\Vendas\Index as VendasIndex;
use App\Livewire\Portal\Agendar as PortalAgendar;
use App\Livewire\Portal\Home as PortalHome;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

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

        // Suporte (impersonação do super-admin): entra via token de uso único.
        Route::get('suporte/{token}', [SuporteController::class, 'entrar'])
            ->name('tenant.suporte.entrar');

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

            // Desafio de 2FA (estado "aguardando 2FA", pós-senha). Fica em guest:web:
            // o usuário pendente ainda NÃO está logado no painel. A pendência mora na
            // sessão (`2fa.pendente`); sem ela, o componente volta ao login.
            Route::get('2fa', DesafioDoisFatores::class)
                ->middleware('guest:web')
                ->name('2fa.desafio');

            // ForcarTrocaSenha: 1º login com `deve_trocar_senha` cai na tela de troca
            // (exceto a própria rota, logout e sair do suporte). Só painel (guard web).
            Route::middleware(['auth:web', ForcarTrocaSenha::class])->group(function () {
                Route::get('/', PainelDashboard::class)->name('dashboard');

                Route::post('sair', [LogoutController::class, 'painel'])->name('logout');

                // Troca de senha obrigatória no 1º login (isenta do middleware acima).
                Route::get('senha', TrocarSenha::class)->name('senha');

                // Passo OPCIONAL de 2FA no 1º login do Dono (após a troca de senha).
                // Reusa o MESMO componente do perfil; layout `auth` + botão "Pular".
                // Gate só-Dono dentro do componente (permissão gerenciar_2fa_proprio).
                Route::get('2fa-inicial', SegurancaDoisFatores::class)->name('2fa.onboarding');

                // Encerrar o modo suporte (impersonação) e voltar ao /admin.
                Route::post('suporte/sair', [SuporteController::class, 'sair'])->name('suporte.sair');

                // Agenda: acesso por ver_agenda OU ver_agenda_propria (checado no componente).
                Route::get('agenda', AgendaIndex::class)->name('agenda');

                // "Últimos serviços": atendimentos concluídos + avaliações. Acesso por
                // ver_avaliacoes OU ver_avaliacoes_proprias (checado no componente).
                Route::get('avaliacoes', AvaliacoesIndex::class)->name('avaliacoes');

                // Cadastros (1B). Cada página exige a permissão de gestão correspondente;
                // ações de criar/editar são reconferidas dentro dos componentes.
                Route::get('unidades', UnidadesIndex::class)
                    ->middleware('can:gerir_unidades')
                    ->name('unidades');

                Route::get('servicos', ServicosIndex::class)
                    ->middleware('can:editar_servico')
                    ->name('servicos');

                // Catálogo (editar_produto) + estoque (gerir_estoque, inclui Recepção).
                // O gate combinado é reconferido no mount do componente.
                Route::get('produtos', ProdutosIndex::class)
                    ->name('produtos');

                // Vendas / comandas (2B). Balcão e a partir de agendamento.
                // O ÍNDICE (lista/abrir avulsa) exige criar_venda. O DETALHE NÃO usa
                // middleware de rota: a autorização é por comanda (VendaPolicy::gerir)
                // — assim o Profissional acessa a comanda do PRÓPRIO atendimento sem
                // ter criar_venda. (Avulsas e de outros são negadas pela policy.)
                Route::get('vendas', VendasIndex::class)
                    ->middleware('can:criar_venda')
                    ->name('vendas');

                Route::get('vendas/{venda}', VendasDetalhe::class)
                    ->name('vendas.detalhe');

                // Relatório de comissões + overrides (financeiro: Dono).
                Route::get('comissoes', ComissoesIndex::class)
                    ->middleware('can:ver_financeiro')
                    ->name('comissoes');

                // Financeiro v1 (números do negócio). Gate por permissão (só Dono, D40).
                // Leitura/agregação — sem migração. NÃO é cálculo fiscal.
                Route::get('financeiro', FinanceiroIndex::class)
                    ->middleware('can:ver_financeiro')
                    ->name('financeiro');

                // Indicadores (Fase II): aba que consome o motor IndicadoresClientes (Fase I).
                // Gate por permissão (Dono+Gerente). Não é módulo de flag — sem recurso:.
                Route::get('indicadores', IndicadoresIndex::class)
                    ->middleware('can:ver_indicadores')
                    ->name('indicadores');

                // Clube de Assinatura (Fase A): só existe com a flag `clube` ligada
                // (recurso:clube → 404 se off) + permissão de gestão (Dono+Gerente).
                Route::get('clube', ClubeIndex::class)
                    ->middleware(['recurso:clube', 'can:gerenciar_clube'])
                    ->name('clube');

                Route::get('bloqueios', BloqueiosIndex::class)
                    ->middleware('can:gerir_agenda')
                    ->name('bloqueios');

                // Horário de funcionamento (semanal) + exceções (feriados/fechamentos).
                Route::get('funcionamento', FuncionamentoIndex::class)
                    ->middleware('can:gerir_agenda')
                    ->name('funcionamento');

                Route::get('kanban', KanbanIndex::class)
                    ->middleware('can:ver_kanban_atendimento')
                    ->name('kanban');

                Route::get('equipe', EquipeIndex::class)
                    ->middleware('can:editar_usuario')
                    ->name('equipe');

                Route::get('equipe/{user}/horarios', EquipeHorarios::class)
                    ->middleware('can:editar_usuario')
                    ->name('equipe.horarios');

                Route::get('papeis', PapeisIndex::class)
                    ->middleware('can:editar_permissoes')
                    ->name('papeis');

                Route::get('aparencia', AparenciaEditar::class)
                    ->middleware('can:gerir_aparencia')
                    ->name('aparencia');

                // Integrações (credenciais cifradas por tenant — Fase 0b). O índice
                // lista só os cards disponíveis (flag 0a ligada + permissão). Cada
                // EDITOR é gated pelo recurso (middleware da 0a) + a permissão dele:
                // recurso desligado → 404 (o recurso "nem existe" para o tenant).
                Route::get('integracoes', IntegracoesIndex::class)
                    ->name('integracoes');

                Route::get('integracoes/mercadopago', IntegracaoMercadoPago::class)
                    ->middleware(['can:gerenciar_pagamentos', 'recurso:gateway'])
                    ->name('integracoes.mercadopago');

                Route::get('integracoes/whatsapp', IntegracaoWhatsapp::class)
                    ->middleware(['can:gerenciar_whatsapp', 'recurso:whatsapp'])
                    ->name('integracoes.whatsapp');
            });
        });
    });

/*
| Arquivos enviados por tenant (logo/cabeçalho/fundo). Endpoint público leve:
| só inicializa o tenancy por caminho (sem sessão/CSRF) e serve do disco do
| tenant. {path} aceita barras. Gerar URL via App\Support\Aparencia::urlArquivo().
*/
Route::middleware([InitializeTenancyByPath::class])
    ->prefix('{tenant}')
    ->where(['tenant' => $tenantSlugPattern])
    ->get('arquivo/{path}', TenantArquivoController::class)
    ->where('path', '.*')
    ->name('tenant.arquivo');
