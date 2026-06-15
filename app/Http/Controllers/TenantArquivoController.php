<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serve arquivos enviados por tenant (logo, imagem de cabeçalho, fundo) sob o
 * prefixo /{tenant}/arquivo/{path}. Diferente do TenantAssetsController do
 * stancl (que identifica o tenant por DOMÍNIO), aqui a rota carrega o tenancy
 * por CAMINHO (InitializeTenancyByPath na rota), então storage_path() já vem
 * sufixado para o disco do tenant (storage/tenant{id}/app/public).
 *
 * O parâmetro {tenant} é "esquecido" pelo InitializeTenancyByPath, então o
 * controller recebe apenas {path}.
 */
class TenantArquivoController extends Controller
{
    public function __invoke(string $path): BinaryFileResponse
    {
        $raiz = realpath(storage_path('app/public'));
        abort_if($raiz === false, 404);

        $alvo = realpath($raiz.DIRECTORY_SEPARATOR.$path);

        // Existe e está contido na raiz pública do tenant (anti path traversal).
        abort_if($alvo === false || ! str_starts_with($alvo, $raiz.DIRECTORY_SEPARATOR), 404);

        return response()->file($alvo);
    }
}
