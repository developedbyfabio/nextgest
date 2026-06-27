<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

/**
 * Contrato do gateway de WhatsApp (driver). Fala com UMA instância pelo nome —
 * não conhece tenant nem banco (quem orquestra isso é o WhatsAppService). Hoje o
 * driver é a Evolution API; trocar de provedor é trocar só a implementação.
 *
 * Métodos lançam WhatsAppException em falha/timeout (nunca derrubam a request com 500).
 */
interface WhatsAppGateway
{
    /**
     * Cria a instância na Evolution e devolve o estado inicial (com QR e token da
     * instância, quando o provedor os retorna na criação).
     *
     * @return array{instancia: string, token: ?string, qr_base64: ?string, qr_code: ?string, raw: array}
     */
    public function criarInstancia(string $instancia): array;

    /**
     * Pede um QR novo para conectar a instância já criada.
     *
     * @return array{instancia: string, qr_base64: ?string, qr_code: ?string, raw: array}
     */
    public function conectar(string $instancia): array;

    /** Estado da conexão da instância (ex.: open|connecting|close). */
    public function statusConexao(string $instancia): string;

    /**
     * Envia uma mensagem de texto. `$token` é o token DAQUELA instância (escopo
     * limitado); se null, usa a key global de infra.
     *
     * @return array{id: ?string, status: ?string, raw: array}
     */
    public function enviarTexto(string $instancia, string $numero, string $texto, ?string $token = null): array;
}
