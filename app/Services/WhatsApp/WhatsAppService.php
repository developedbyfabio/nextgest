<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Models\WhatsappConfig;
use Illuminate\Support\Str;

/**
 * Orquestrador de WhatsApp POR TENANT (WhatsApp Fatia 1, D75). Lê/grava a config do
 * salão (whatsapp_config: nome da instância + token DAQUELA instância, cifrado) e
 * delega ao driver (WhatsAppGateway). A key GLOBAL de infra nunca passa por aqui —
 * é o driver que a usa, do .env. Roda sempre no contexto de um tenant inicializado.
 */
class WhatsAppService
{
    public function __construct(private readonly WhatsAppGateway $gateway) {}

    /** Config (singleton por tenant); cria a linha em memória se ainda não existe. */
    private function config(): WhatsappConfig
    {
        return WhatsappConfig::query()->first() ?? new WhatsappConfig;
    }

    /** Nome da instância deste salão na Evolution (único na Evolution compartilhada). */
    public function nomeInstancia(): string
    {
        $c = $this->config();

        if (filled($c->instancia)) {
            return (string) $c->instancia;
        }

        return config('whatsapp.prefixo_instancia', 'ng_').Str::slug((string) tenant('id'), '_');
    }

    /**
     * Garante a instância do salão e devolve um QR para conectar. Primeira vez: cria
     * na Evolution e PERSISTE nome + token da instância. Depois: pede QR novo. Se a
     * criação falhar porque a instância já existe, cai para o connect.
     *
     * @return array{instancia: string, qr_base64: ?string, qr_code: ?string}
     */
    public function conectarInstancia(): array
    {
        $c = $this->config();
        $nome = $this->nomeInstancia();

        if (blank($c->instancia)) {
            try {
                $res = $this->gateway->criarInstancia($nome);
                $c->instancia = $res['instancia'] ?? $nome;
                if (! empty($res['token'])) {
                    $c->instancia_token = $res['token'];
                }
                $c->status_conexao = 'connecting';
                $c->save();

                return ['instancia' => $c->instancia, 'qr_base64' => $res['qr_base64'] ?? null, 'qr_code' => $res['qr_code'] ?? null];
            } catch (WhatsAppException) {
                // Provavelmente já existe na Evolution → tenta só conectar.
                $c->instancia = $nome;
                $c->save();
            }
        }

        $res = $this->gateway->conectar($c->instancia);
        $c->status_conexao = 'connecting';
        $c->save();

        return ['instancia' => $c->instancia, 'qr_base64' => $res['qr_base64'] ?? null, 'qr_code' => $res['qr_code'] ?? null];
    }

    /** Estado atual da conexão; também grava o último estado conhecido. */
    public function status(): ?string
    {
        $c = $this->config();

        if (blank($c->instancia)) {
            return null;
        }

        $estado = $this->gateway->statusConexao($c->instancia);
        $c->status_conexao = $estado;
        $c->save();

        return $estado;
    }

    /** Desconecta (logout) a instância do salão e marca o estado como `close`. */
    public function desconectar(): void
    {
        $c = $this->config();

        if (blank($c->instancia)) {
            return;
        }

        $this->gateway->desconectar($c->instancia);
        $c->status_conexao = 'close';
        $c->save();
    }

    /**
     * Envia uma mensagem de texto pelo número informado, usando a instância DESTE
     * tenant e o token dela (escopo limitado). Exige a instância já configurada.
     *
     * @return array{id: ?string, status: ?string, raw: array}
     */
    public function enviarTexto(string $numero, string $texto): array
    {
        $c = $this->config();

        if (blank($c->instancia)) {
            throw new WhatsAppException('Este estabelecimento ainda não conectou o WhatsApp.');
        }

        return $this->gateway->enviarTexto($c->instancia, $numero, $texto, $c->instancia_token);
    }
}
