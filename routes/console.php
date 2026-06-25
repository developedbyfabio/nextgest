<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Rede de segurança da cobrança recorrente (D62): reconcilia com o MP diariamente,
// caso algum webhook não tenha chegado. Idempotente.
Schedule::command('nextgest:reconciliar-assinaturas')->dailyAt('03:10')->withoutOverlapping();
