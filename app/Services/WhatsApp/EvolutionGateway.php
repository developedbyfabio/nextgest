<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Driver Evolution API (WhatsApp Fatia 1, D75). HTTP para a Evolution local
 * (config/whatsapp.php → .env). A key GLOBAL (infra) vem só de config; o token da
 * instância (escopo limitado) é passado pelo orquestrador. Erros/timeout viram
 * WhatsAppException (log sem segredo: nunca a apikey/token).
 */
class EvolutionGateway implements WhatsAppGateway
{
    public function criarInstancia(string $instancia): array
    {
        $resp = $this->req(fn (PendingRequest $h) => $h->post('/instance/create', [
            'instanceName' => $instancia,
            'integration' => 'WHATSAPP-BAILEYS',
            'qrcode' => true,
        ]), $instancia, 'criar instância');

        $json = $resp->json() ?? [];
        $qr = (array) Arr::get($json, 'qrcode', []);

        return [
            'instancia' => (string) (Arr::get($json, 'instance.instanceName') ?? $instancia),
            'token' => $this->extrairToken($json),
            'qr_base64' => Arr::get($qr, 'base64'),
            'qr_code' => Arr::get($qr, 'code'),
            'raw' => $json,
        ];
    }

    public function conectar(string $instancia): array
    {
        $resp = $this->req(fn (PendingRequest $h) => $h->get('/instance/connect/'.$instancia), $instancia, 'conectar');

        $json = $resp->json() ?? [];
        // A Evolution às vezes devolve {base64,code} no topo, às vezes sob {qrcode:{...}}.
        $qr = (array) (Arr::get($json, 'qrcode') ?? $json);

        return [
            'instancia' => $instancia,
            'qr_base64' => Arr::get($qr, 'base64'),
            'qr_code' => Arr::get($qr, 'code'),
            'raw' => $json,
        ];
    }

    public function statusConexao(string $instancia): string
    {
        $resp = $this->req(fn (PendingRequest $h) => $h->get('/instance/connectionState/'.$instancia), $instancia, 'status');

        $json = $resp->json() ?? [];

        return (string) (Arr::get($json, 'instance.state') ?? Arr::get($json, 'state') ?? 'desconhecido');
    }

    public function desconectar(string $instancia): void
    {
        $this->req(fn (PendingRequest $h) => $h->delete('/instance/logout/'.$instancia), $instancia, 'desconectar');
    }

    public function enviarTexto(string $instancia, string $numero, string $texto, ?string $token = null): array
    {
        $resp = $this->req(
            fn (PendingRequest $h) => $h->post('/message/sendText/'.$instancia, [
                'number' => $this->normalizarNumero($numero),
                'text' => $texto,
            ]),
            $instancia,
            'enviar texto',
            $token,
        );

        $json = $resp->json() ?? [];

        return [
            'id' => Arr::get($json, 'key.id') ?? Arr::get($json, 'id'),
            'status' => Arr::get($json, 'status'),
            'raw' => $json,
        ];
    }

    /**
     * Normaliza para o formato que a Evolution espera (dígitos com DDI, sem '+').
     * BR: se não vier com DDI 55 e tiver 10–11 dígitos (DDD + número), prefixa 55.
     */
    public function normalizarNumero(string $numero): string
    {
        $digitos = preg_replace('/\D+/', '', $numero) ?? '';

        if ($digitos === '') {
            throw new WhatsAppException('Número de WhatsApp inválido.');
        }

        if (! str_starts_with($digitos, '55') && strlen($digitos) >= 10 && strlen($digitos) <= 11) {
            $digitos = '55'.$digitos;
        }

        return $digitos;
    }

    /** Token da instância pode vir como string ou objeto {apikey}. */
    private function extrairToken(array $json): ?string
    {
        $hash = Arr::get($json, 'hash');

        if (is_array($hash)) {
            return Arr::get($hash, 'apikey');
        }

        return is_string($hash) && $hash !== '' ? $hash : null;
    }

    /**
     * Executa a chamada com tratamento de falha/timeout → WhatsAppException.
     * `$token` null = usa a key GLOBAL de infra (config). Nunca loga apikey/token.
     */
    private function req(callable $call, string $instancia, string $acao, ?string $token = null)
    {
        try {
            $resp = $call($this->http($token));
        } catch (ConnectionException $e) {
            Log::warning('WhatsApp (Evolution): sem conexão.', ['instancia' => $instancia, 'acao' => $acao]);

            throw new WhatsAppException('Não foi possível falar com o WhatsApp agora. Tente novamente.');
        }

        if ($resp->failed()) {
            Log::warning('WhatsApp (Evolution): chamada falhou.', [
                'instancia' => $instancia,
                'acao' => $acao,
                'http_status' => $resp->status(),
                'mensagem' => Arr::get($resp->json() ?? [], 'message') ?? Arr::get($resp->json() ?? [], 'error'),
            ]);

            throw new WhatsAppException('O WhatsApp recusou a operação ('.$acao.'). Verifique a conexão da instância.');
        }

        return $resp;
    }

    /** Cliente HTTP. `apikey`: token da instância (escopo limitado) ou a GLOBAL (infra). */
    private function http(?string $token = null): PendingRequest
    {
        $apikey = $token ?: (string) config('whatsapp.api_key');

        if ($apikey === '') {
            throw new WhatsAppException('Integração de WhatsApp não configurada (key ausente).');
        }

        return Http::baseUrl((string) config('whatsapp.base_url'))
            ->withHeaders(['apikey' => $apikey])
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('whatsapp.timeout', 15));
    }
}
