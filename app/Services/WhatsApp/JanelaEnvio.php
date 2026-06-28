<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Models\WhatsappConfig;
use Carbon\Carbon;

/**
 * Janela de horário permitido para envios de WhatsApp (Controle de mensagens, D83).
 * Decide, NO SERVIDOR, se um envio pode sair AGORA (`aberta`) e, se não, qual o próximo
 * horário válido (`proximaAbertura`). Fuso APP_TIMEZONE (usa now()/Carbon do app).
 *
 * Resolução (do mais específico ao mais geral): override por automação
 * (whatsapp_config.automacoes[chave].janela) → override global (whatsapp_config.janela)
 * → defaults (config('whatsapp.janela')). `ativa=false` em qualquer nível efetivo = sem
 * restrição (envia a qualquer hora). Janela é do MESMO dia (inicio < fim).
 */
class JanelaEnvio
{
    /** Janela efetiva da automação: merge override-automação > override-global > config. */
    public function paraAutomacao(string $chave, ?WhatsappConfig $cfg): array
    {
        $default = (array) config('whatsapp.janela');
        $global = is_array($cfg?->janela) ? $cfg->janela : [];
        $porAut = $cfg?->automacoes[$chave]['janela'] ?? null;
        $over = is_array($porAut) ? $porAut : [];

        return array_merge($default, $global, $over);
    }

    /** A janela está aberta no momento dado (default now())? `ativa=false` → sempre true. */
    public function aberta(array $janela, ?Carbon $momento = null): bool
    {
        if (! ($janela['ativa'] ?? true)) {
            return true;
        }

        $momento = ($momento ?? now())->copy();
        [$inicio, $fim] = $this->limites($janela, $momento);

        return $momento->between($inicio, $fim);
    }

    /** Próximo instante em que a janela está aberta (>= momento). `ativa=false` → o próprio momento. */
    public function proximaAbertura(array $janela, ?Carbon $momento = null): Carbon
    {
        $momento = ($momento ?? now())->copy();

        if (! ($janela['ativa'] ?? true) || $this->aberta($janela, $momento)) {
            return $momento;
        }

        [$inicio] = $this->limites($janela, $momento);

        // Antes de abrir hoje → abre hoje; depois de fechar → abre amanhã.
        return $momento->lt($inicio) ? $inicio : $inicio->addDay();
    }

    /** Início e fim da janela no DIA do momento (Carbon). */
    private function limites(array $janela, Carbon $momento): array
    {
        [$ih, $im] = $this->horaMinuto((string) ($janela['inicio'] ?? '08:00'));
        [$fh, $fm] = $this->horaMinuto((string) ($janela['fim'] ?? '20:00'));

        return [
            $momento->copy()->setTime($ih, $im, 0),
            $momento->copy()->setTime($fh, $fm, 59),
        ];
    }

    /** "HH:MM" → [hora, minuto] (defensivo: valores fora do range são limitados). */
    private function horaMinuto(string $hhmm): array
    {
        $partes = explode(':', $hhmm);
        $h = max(0, min(23, (int) ($partes[0] ?? 0)));
        $m = max(0, min(59, (int) ($partes[1] ?? 0)));

        return [$h, $m];
    }
}
