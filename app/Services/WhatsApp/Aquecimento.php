<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Models\LembreteServico;
use App\Models\MensagemWhatsapp;
use App\Models\PedidoAvaliacao;
use App\Models\WhatsappConfig;

/**
 * Modo Aquecimento do WhatsApp (D82): curva de volume crescente p/ número novo, POR CIMA
 * das travas anti-ban (D79). Teto efetivo do dia = min(teto normal, teto da curva do dia),
 * contado a partir de `whatsapp_config.conectado_em` (dia 1 = dia da conexão, fuso
 * APP_TIMEZONE). O consumo do dia é COMBINADO (lembrete + avaliação) — o número tem um
 * orçamento diário único. Broadcast só liberado na fase madura.
 *
 * Defaults conservadores em config('whatsapp.aquecimento'); o tenant sobrescreve em
 * whatsapp_config.aquecimento. NÃO dispara nada — só modula o volume do que já existe.
 */
class Aquecimento
{
    /** Curva efetiva do tenant: override do whatsapp_config ou os defaults do config. */
    public function curva(?WhatsappConfig $cfg): array
    {
        $padrao = (array) config('whatsapp.aquecimento');
        $over = $cfg?->aquecimento;

        return is_array($over) && ! empty($over['fases']) ? array_merge($padrao, $over) : $padrao;
    }

    /** Dia atual da curva (>= 1). conectado_em nulo → 1 (mais conservador). Fuso APP_TIMEZONE. */
    public function diaAtual(?WhatsappConfig $cfg): int
    {
        $conectado = $cfg?->conectado_em;
        if (! $conectado) {
            return 1;
        }

        return (int) $conectado->copy()->startOfDay()->diffInDays(now()->startOfDay()) + 1;
    }

    /** Teto da curva no dia (null = aquecimento desligado ou concluído → sem cap próprio). */
    public function tetoDaCurva(?WhatsappConfig $cfg): ?int
    {
        $aq = $this->curva($cfg);
        if (! ($aq['ativo'] ?? true)) {
            return null;
        }

        $dia = $this->diaAtual($cfg);
        $fases = collect($aq['fases'] ?? [])->sortBy('ate_dia')->values();

        foreach ($fases as $fase) {
            if ($dia <= (int) $fase['ate_dia']) {
                return (int) $fase['limite_dia'];
            }
        }

        return null; // passou a última fase → aquecimento concluído
    }

    /** Teto EFETIVO do dia: min(normal, curva). */
    public function tetoEfetivoDia(?WhatsappConfig $cfg): int
    {
        $normal = (int) config('whatsapp.lembretes.limite_por_dia', 150);
        $curva = $this->tetoDaCurva($cfg);

        return $curva === null ? $normal : min($normal, $curva);
    }

    /**
     * Envios JÁ feitos/enfileirados hoje (orçamento diário ÚNICO, COMBINADO) — contexto do
     * tenant. Soma lembrete + avaliação (enfileirados hoje) + WhatsApp avulso (D88) que
     * realmente saiu/tentou sair hoje (enviado|falhou). Assim o avulso NÃO é porta lateral
     * pra furar o teto: consome o mesmo orçamento e reduz o que as automações podem enviar.
     */
    public function consumoHoje(): int
    {
        return LembreteServico::query()->whereDate('enfileirado_em', today())->count()
            + PedidoAvaliacao::query()->whereDate('enfileirado_em', today())->count()
            + MensagemWhatsapp::query()
                ->where('automacao', MensagemWhatsapp::AVULSO)
                ->whereIn('status', [MensagemWhatsapp::ENVIADO, MensagemWhatsapp::FALHOU])
                ->whereDate('created_at', today())
                ->count();
    }

    /** Quanto ainda pode enviar hoje (>= 0), já com o aquecimento + consumo combinado. */
    public function restanteHoje(?WhatsappConfig $cfg): int
    {
        return max(0, $this->tetoEfetivoDia($cfg) - $this->consumoHoje());
    }

    /** Broadcast (envio em massa) liberado? Bloqueado até a fase madura da curva. */
    public function broadcastLiberado(?WhatsappConfig $cfg): bool
    {
        $aq = $this->curva($cfg);
        if (! ($aq['ativo'] ?? true)) {
            return true;
        }

        // Aquecimento concluído (sem teto de curva) → liberado.
        if ($this->tetoDaCurva($cfg) === null) {
            return true;
        }

        return $this->diaAtual($cfg) >= (int) ($aq['broadcast_a_partir_dia'] ?? 11);
    }
}
