<?php

/*
| Bootstrap dos testes (pós-incidente).
|
| Causa raiz do incidente: um `bootstrap/cache/config.php` "sujo" (de um
| `config:cache`/`optimize`) faz o Laravel IGNORAR o `DB_CONNECTION=sqlite` do
| phpunit.xml e usar a conexão cacheada (MySQL de dev) — e o `migrate:fresh` do
| RefreshDatabase zerou o banco central. Aqui, ANTES de qualquer app bootar,
| removemos o cache de config/rotas/eventos para os testes sempre lerem
| config/* + env do phpunit (sqlite). O guard em tests/TestCase.php é a 2ª trava.
*/

$cacheDir = __DIR__.'/../bootstrap/cache';

foreach (['config.php', 'routes-v7.php', 'events.php'] as $arquivo) {
    $caminho = $cacheDir.'/'.$arquivo;

    if (is_file($caminho)) {
        @unlink($caminho);
    }
}

require __DIR__.'/../vendor/autoload.php';
