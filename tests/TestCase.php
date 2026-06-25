<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * GUARD DE SEGURANÇA (pós-incidente): roda ANTES do `migrate:fresh` do
     * RefreshDatabase. Se a conexão padrão não for sqlite, ABORTA a suíte — assim os
     * testes NUNCA tocam (e jamais zeram) um banco real, mesmo que um
     * `bootstrap/cache/config.php` "sujo" faça o app ignorar o DB_CONNECTION=sqlite do
     * phpunit. Ver [[Gotchas e Aprendizados do Projeto]] (config cacheado em dev).
     */
    protected function beforeRefreshingDatabase()
    {
        $conexao = config('database.default');

        if ($conexao !== 'sqlite') {
            throw new RuntimeException(
                "GUARD DE TESTE ABORTADO: a conexão padrão é '{$conexao}', não 'sqlite'. "
                ."Os testes NÃO podem rodar contra um banco real (risco de migrate:fresh zerar o central). "
                ."Causa provável: bootstrap/cache/config.php cacheado. Rode `php artisan config:clear` e tente de novo."
            );
        }
    }
}
