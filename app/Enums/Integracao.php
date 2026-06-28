<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Integrações externas que um estabelecimento pode configurar (credenciais no
 * banco do TENANT, cifradas). FONTE ÚNICA dos slugs válidos + do mapeamento para
 * o recurso (flag 0a) que controla a disponibilidade e para a permissão (spatie).
 *
 * Reuso (Fase 0b): NÃO há tabela própria — cada integração mora no seu cofre já
 * existente: `mercadopago` → `gateways_pagamento` (App\Models\GatewayPagamento);
 * `whatsapp` → `whatsapp_config` (App\Models\WhatsappConfig).
 *
 * `clube` NÃO entra aqui: clube não tem credencial própria (consome o gateway).
 */
enum Integracao: string
{
    case MercadoPago = 'mercadopago';

    // WhatsApp saiu daqui (D76): virou item próprio no menu, com tela de conexão
    // (Evolution) gated por recurso `whatsapp` + permissão `gerenciar_whatsapp`.

    /** Recurso (flag 0a) que controla a disponibilidade desta integração. */
    public function recurso(): Recurso
    {
        return match ($this) {
            self::MercadoPago => Recurso::Gateway,
        };
    }

    /** Permissão (spatie) para gerenciar esta integração. */
    public function permissao(): string
    {
        return match ($this) {
            self::MercadoPago => 'gerenciar_pagamentos',
        };
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::MercadoPago => 'Mercado Pago',
        };
    }

    public function descricao(): string
    {
        return match ($this) {
            self::MercadoPago => 'Pagamento online: cole o Access Token da conta Mercado Pago.',
        };
    }

    public function icone(): string
    {
        return match ($this) {
            self::MercadoPago => 'credit-card',
        };
    }

    /** Nome da rota do editor desta integração (gated por recurso + permissão). */
    public function rota(): string
    {
        return 'painel.integracoes.'.$this->value;
    }

    /** Permissões de TODAS as integrações (para checar acesso "a pelo menos uma"). */
    public static function permissoes(): array
    {
        return array_map(static fn (self $i): string => $i->permissao(), self::cases());
    }
}
