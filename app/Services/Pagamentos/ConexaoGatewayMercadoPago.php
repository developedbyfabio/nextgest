<?php

declare(strict_types=1);

namespace App\Services\Pagamentos;

use App\Models\GatewayPagamento;
use App\Models\Tenant;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Orquestra a CONEXÃO OAuth da conta Mercado Pago do salão (Modelo A, D78). Liga o
 * fluxo (state/sessão anti-CSRF), conclui o callback (troca o code, grava o token
 * CIFRADO no cofre do tenant) e desconecta. Não cobra nada.
 *
 * A chave de sessão guarda o nonce + tenant; o `state` enviado ao MP carrega
 * `tenant|nonce` (base64). No callback, o nonce do state TEM que bater com o da
 * sessão (mesma sessão que iniciou) — sem isso, rejeita (anti-CSRF de login).
 */
class ConexaoGatewayMercadoPago
{
    private const SESSAO = 'mp_oauth';

    private const PROVEDOR = 'mercadopago';

    public function __construct(private readonly MercadoPagoOAuth $oauth) {}

    /** Inicia a conexão: gera o state, guarda na sessão e devolve a URL de autorização. */
    public function iniciar(string $tenantId): string
    {
        $nonce = Str::random(40);
        session()->put(self::SESSAO, ['tenant' => $tenantId, 'nonce' => $nonce]);

        $state = base64_encode($tenantId.'|'.$nonce);

        return $this->oauth->urlAutorizacao($state);
    }

    /**
     * Conclui o callback: valida o state contra a sessão, troca o code e grava a
     * conexão no cofre do tenant. Devolve o tenantId. Lança em qualquer falha.
     */
    public function concluir(?string $code, ?string $state): string
    {
        if (blank($code) || blank($state)) {
            throw new PagamentoGatewayException('Retorno inválido do Mercado Pago.');
        }

        [$tenantId, $nonce] = array_pad(explode('|', (string) base64_decode((string) $state, true), 2), 2, null);

        $sessao = session()->get(self::SESSAO);
        if (! is_array($sessao) || ($sessao['nonce'] ?? null) !== $nonce || ($sessao['tenant'] ?? null) !== $tenantId || blank($tenantId)) {
            throw new PagamentoGatewayException('Sessão de autorização inválida ou expirada. Tente conectar novamente.');
        }
        session()->forget(self::SESSAO);

        $tenant = Tenant::find($tenantId);
        if (! $tenant instanceof Tenant) {
            throw new PagamentoGatewayException('Estabelecimento não encontrado.');
        }

        $tokens = $this->oauth->trocarCodigo((string) $code);

        $tenant->run(function () use ($tokens) {
            $conta = $this->oauth->contaInfo((string) ($tokens['access_token'] ?? ''));

            $cfg = GatewayPagamento::query()->where('provedor', self::PROVEDOR)->first() ?? new GatewayPagamento(['provedor' => self::PROVEDOR]);

            $userId = (string) ($tokens['user_id'] ?? Arr::get($conta, 'id') ?? '');

            $cfg->provedor = self::PROVEDOR;
            $cfg->credenciais = [
                'access_token' => $tokens['access_token'] ?? null,
                'refresh_token' => $tokens['refresh_token'] ?? null,
                'public_key' => $tokens['public_key'] ?? null,
                'expires_in' => $tokens['expires_in'] ?? null,
                'scope' => $tokens['scope'] ?? null,
            ];
            $cfg->conta_externa_id = $userId;
            $cfg->conta_externa_nome = (string) (Arr::get($conta, 'nickname') ?? Arr::get($conta, 'email') ?? ('Conta '.$userId));
            $cfg->conectado_em = now();
            $cfg->modo = 'producao';
            $cfg->ativo = true;
            $cfg->padrao = true;
            $cfg->save();
        });

        return $tenantId;
    }

    /** Desconecta (limpa a credencial) — roda no contexto do tenant. */
    public function desconectar(): void
    {
        $cfg = GatewayPagamento::query()->where('provedor', self::PROVEDOR)->first();
        if (! $cfg) {
            return;
        }

        $cfg->credenciais = null;
        $cfg->conta_externa_id = null;
        $cfg->conta_externa_nome = null;
        $cfg->conectado_em = null;
        $cfg->ativo = false;
        $cfg->padrao = false;
        $cfg->save();
    }

    /** Conectado? (tenant context) */
    public function conectado(): bool
    {
        $cfg = GatewayPagamento::query()->where('provedor', self::PROVEDOR)->where('ativo', true)->first();

        return $cfg !== null && filled($cfg->credenciais['access_token'] ?? null);
    }

    /**
     * Dados PÚBLICOS da conta conectada (nunca o token). Null se desconectado.
     *
     * @return array{id: ?string, nome: ?string, conectado_em: ?Carbon}|null
     */
    public function conta(): ?array
    {
        $cfg = GatewayPagamento::query()->where('provedor', self::PROVEDOR)->first();

        if (! $cfg || ! filled($cfg->credenciais['access_token'] ?? null)) {
            return null;
        }

        return [
            'id' => $cfg->conta_externa_id,
            'nome' => $cfg->conta_externa_nome,
            'conectado_em' => $cfg->conectado_em,
        ];
    }
}
