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

// Lembrete de serviço por WhatsApp (D79): a cada minuto, enfileira o que entrou na
// janela (anti-ban: tetos + espaçamento; idempotente). Em produção exige worker da fila.
Schedule::command('nextgest:enviar-lembretes')->everyMinute()->withoutOverlapping();

// Avaliação pós-serviço por WhatsApp (D81): a cada minuto, enfileira o link de avaliação
// dos atendimentos concluídos na janela. Mesmos freios/idempotência do lembrete.
Schedule::command('nextgest:enviar-avaliacoes')->everyMinute()->withoutOverlapping();
