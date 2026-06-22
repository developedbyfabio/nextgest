<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/*
| PERF-005 — imagens do tenant (logo/cabeçalho/fundo) servidas com Cache-Control longo
| e imutável. Seguro porque o nome do arquivo é hashed/único por upload (Storage::store)
| → re-upload gera nova URL; a antiga nunca é reusada.
*/

it('[PERF-005] arquivo do tenant é servido com Cache-Control imutável longo', function () {
    $tenant = criarTenant('lojaimg');

    // Cria um arquivo no disco público DO TENANT (storage_path é sufixado pela tenancy).
    $tenant->run(function () {
        $dir = storage_path('app/public/aparencia');
        File::ensureDirectoryExists($dir);
        file_put_contents($dir.'/logo-fake.png', 'conteudo-fake');
    });

    $resp = $this->get('/lojaimg/arquivo/aparencia/logo-fake.png');

    $resp->assertOk();
    expect($resp->headers->get('Cache-Control'))->toContain('max-age=31536000')
        ->and($resp->headers->get('Cache-Control'))->toContain('immutable');
});
