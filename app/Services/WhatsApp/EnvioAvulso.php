<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Models\Cliente;
use App\Models\MensagemWhatsapp;
use App\Models\WhatsappConfig;

/**
 * Envio de WhatsApp AVULSO (manual, 1 a 1, texto livre) pela aba Clientes (D88, Fatia 2).
 * É o "ponto único" que faz o avulso passar pelos MESMOS freios anti-ban dos envios
 * automáticos, para não ser porta lateral pra furar o limite:
 *
 *  - exige o WhatsApp CONECTADO (D75);
 *  - consome o MESMO orçamento diário combinado (D82): bloqueia se `Aquecimento::restanteHoje`
 *    chegou a zero — e cada avulso enviado também reduz o que as automações podem enviar
 *    (porque entra em `Aquecimento::consumoHoje`);
 *  - respeita o teto POR MINUTO (D79, `limite_por_minuto`) só para os avulsos;
 *  - REGISTRA no histórico (D83) como `automacao='avulso'` (enviado|falhou).
 *
 * NÃO decide opt-out: o avulso pode ir para quem está em opt-out, mas a CONFIRMAÇÃO
 * (modal D65) é responsabilidade da UI. Aqui é só o envio com os freios.
 *
 * Bloqueio/falha => WhatsAppException com mensagem amigável (sem 500, sem vazar segredo).
 */
class EnvioAvulso
{
    public function __construct(
        private readonly WhatsAppService $whatsapp,
        private readonly Aquecimento $aquecimento,
    ) {}

    public function enviar(Cliente $cliente, string $texto): MensagemWhatsapp
    {
        $texto = trim($texto);

        if ($texto === '') {
            throw new WhatsAppException('Escreva a mensagem antes de enviar.');
        }

        if (blank($cliente->telefone)) {
            throw new WhatsAppException('Este cliente não tem telefone cadastrado.');
        }

        $cfg = WhatsappConfig::query()->first();

        // Teto do dia (orçamento combinado, D82) — checado ANTES da chamada de rede.
        if ($this->aquecimento->restanteHoje($cfg) <= 0) {
            throw new WhatsAppException('O limite diário de mensagens deste número foi atingido. Tente novamente amanhã.');
        }

        // Teto por minuto (D79) — só os avulsos (as automações são espaçadas pelo cron).
        if ($this->avulsosUltimoMinuto() >= (int) config('whatsapp.lembretes.limite_por_minuto', 4)) {
            throw new WhatsAppException('Muitas mensagens enviadas no último minuto. Aguarde alguns segundos e tente de novo.');
        }

        // Conexão (autoritativa, via Evolution). Caída/erro → não envia, avisa.
        try {
            $estado = $this->whatsapp->status();
        } catch (WhatsAppException) {
            $estado = null;
        }

        if ($estado !== 'open') {
            throw new WhatsAppException('O WhatsApp do estabelecimento não está conectado. Conecte na aba WhatsApp e tente de novo.');
        }

        try {
            $this->whatsapp->enviarTexto((string) $cliente->telefone, $texto);
        } catch (WhatsAppException) {
            RegistroMensagem::registrar([
                'automacao' => MensagemWhatsapp::AVULSO,
                'cliente_id' => $cliente->id,
                'telefone' => $cliente->telefone,
                'status' => MensagemWhatsapp::FALHOU,
                'motivo' => 'falha no envio',
                'conteudo' => $texto,
            ]);

            throw new WhatsAppException('Não foi possível enviar a mensagem agora. Tente novamente em instantes.');
        }

        return RegistroMensagem::registrar([
            'automacao' => MensagemWhatsapp::AVULSO,
            'cliente_id' => $cliente->id,
            'telefone' => $cliente->telefone,
            'status' => MensagemWhatsapp::ENVIADO,
            'conteudo' => $texto,
            'enviado_em' => now(),
        ]);
    }

    /** Avulsos que realmente saíram/tentaram sair nos últimos 60s (freio de rajada). */
    private function avulsosUltimoMinuto(): int
    {
        return MensagemWhatsapp::query()
            ->where('automacao', MensagemWhatsapp::AVULSO)
            ->whereIn('status', [MensagemWhatsapp::ENVIADO, MensagemWhatsapp::FALHOU])
            ->where('created_at', '>=', now()->subMinute())
            ->count();
    }
}
