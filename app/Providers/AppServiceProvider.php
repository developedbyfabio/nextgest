<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\GarantirAssinaturaAtiva;
use App\Http\Middleware\InicializarTenancyArquivosLivewire;
use App\Services\Clube\GatewayRecorrente;
use App\Services\Clube\GatewayRecorrenteManual;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Features\SupportFileUploads\FilePreviewController;
use Livewire\Livewire;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Costura do gateway recorrente do Clube: hoje sempre a impl. MANUAL (não cobra).
        // O Mercado Pago Preapproval entra como outra impl. desta interface no futuro
        // (Fase 2/3, com VPS), trocando só este binding — sem mexer na aba/serviço.
        $this->app->bind(
            GatewayRecorrente::class,
            GatewayRecorrenteManual::class,
        );
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
            // Correção M1 (D71): a suspensão por pagamento (GarantirAssinaturaAtiva) só rodava
            // no GET de página; as AÇÕES Livewire passavam livres (aba aberta antes da
            // suspensão continuava operando). Como persistent middleware, o Livewire o reaplica
            // no /update — mas SÓ para componentes cuja ROTA ORIGINAL o tinha (= o grupo do
            // PAINEL). O portal/cliente nunca o teve → segue intacto. Listado DEPOIS do
            // InitializeTenancyByPath: a tenancy inicializa antes (lição 4, senão 500). O
            // redirect para a tela de suspensão é respeitado pelo Livewire.
            GarantirAssinaturaAtiva::class,
        ]);

        /*
        | Endpoints GLOBAIS de arquivo do Livewire (upload-file / preview-file) não
        | passam pelo persistent middleware acima (são controllers próprios) nem têm
        | {tenant} no caminho. Sem tenancy, o guard `web` resolve o usuário do tenant
        | contra o banco central → 500. O upload-file recebe o middleware via
        | config/livewire.php (temporary_file_upload.middleware); o preview-file usa
        | esta lista estática — então o injetamos aqui também.
        */
        if (! in_array(InicializarTenancyArquivosLivewire::class, FilePreviewController::$middleware, true)) {
            FilePreviewController::$middleware[] = InicializarTenancyArquivosLivewire::class;
        }

        /*
        | Diretiva @recurso('whatsapp') ... @endrecurso — esconde blocos de UI de um
        | recurso que esteja DESLIGADO para o tenant atual. Reusa o MESMO helper
        | tenant_tem_recurso() (sem contexto/chave inválida → false, sem quebrar).
        */
        Blade::if('recurso', fn (string $recurso): bool => tenant_tem_recurso($recurso));
    }
}
