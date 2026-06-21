<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * Regressão do bug "skeleton de loading que não some" (Livewire 4 NÃO auto-esconde
 * `wire:loading`; ele só alterna o display inline durante a requisição). A correção
 * é uma regra CSS que esconde, EM REPOUSO, cada variante "mostrar-no-loading" usada.
 *
 * Estes testes verificam o ESTADO de repouso (não só "o skeleton existe no HTML"):
 * toda variante de `wire:loading` que MOSTRA no loading precisa ter um seletor CSS
 * que a esconde por padrão — senão ela fica presa visível no navegador.
 */

/** Variantes de `wire:loading` que MOSTRAM no loading (exclui remove/class/attr). */
function variantesMostrarNoLoading(string $conteudo): array
{
    preg_match_all('/wire:loading(?:\.[a-z]+)*/', $conteudo, $m);

    return collect($m[0])
        ->reject(fn ($v) => str_contains($v, '.remove')
            || str_contains($v, '.class')
            || str_contains($v, '.attr')
            || str_contains($v, '.offline'))
        ->unique()->values()->all();
}

it('CSS esconde em repouso todas as variantes wire:loading "mostrar" usadas nas views', function () {
    $css = File::get(resource_path('css/app.css'));

    $usadas = collect(File::allFiles(resource_path('views')))
        ->filter(fn ($f) => str_ends_with($f->getFilename(), '.blade.php'))
        ->flatMap(fn ($f) => variantesMostrarNoLoading(File::get($f->getPathname())))
        ->unique()->values();

    // Sanidade: existem skeletons/spinners de loading no projeto.
    expect($usadas)->not->toBeEmpty();

    foreach ($usadas as $variante) {
        // 'wire:loading.delay.flex' -> seletor '[wire\:loading\.delay\.flex]'
        $seletor = '['.str_replace([':', '.'], ['\\:', '\\.'], $variante).']';

        expect(str_contains($css, $seletor))->toBeTrue(
            "Falta no app.css a regra que esconde [{$variante}] em repouso — o skeleton/spinner ficaria preso visível."
        );
    }
});

it('o CSS de esconder NÃO atinge wire:loading.remove (esses devem aparecer em repouso)', function () {
    $css = File::get(resource_path('css/app.css'));

    // O bloco de esconder não pode listar variantes .remove (senão some o conteúdo real).
    expect($css)->not->toContain('[wire\:loading\.remove');
});

it('a regra de esconder está presente para a variante base wire:loading', function () {
    expect(File::get(resource_path('css/app.css')))
        ->toContain('[wire\:loading]')
        ->toContain('display: none;');
});
