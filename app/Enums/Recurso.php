<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Recursos (módulos "à la carte") que o super-admin liga/desliga POR estabelecimento.
 *
 * FONTE ÚNICA da lista válida de recursos. A flag mora no banco CENTRAL, dentro do
 * JSON `data` do tenant (chave `recursos`), como um array de slugs ligados — ver
 * App\Models\Tenant::recursosAtivos()/temRecurso() e o helper tenant_tem_recurso().
 *
 * Default: TUDO DESLIGADO (slug ausente do array = recurso off).
 *
 * Convenção (Fase 0a): todo recurso futuro nasce embrulhado na sua flag — rota com o
 * middleware `recurso:{slug}` + bloco Blade `@recurso('{slug}') ... @endrecurso`.
 */
enum Recurso: string
{
    case Clube = 'clube';
    case Whatsapp = 'whatsapp';
    case Gateway = 'gateway';

    /** Rótulo amigável (pt-BR) para a UI do admin. */
    public function rotulo(): string
    {
        return match ($this) {
            self::Clube => 'Clube de assinatura',
            self::Whatsapp => 'WhatsApp / Lembretes',
            self::Gateway => 'Pagamento online',
        };
    }

    /** Subtítulo curto do toggle. */
    public function descricao(): string
    {
        return match ($this) {
            self::Clube => 'Planos de assinatura recorrente para os clientes.',
            self::Whatsapp => 'Lembretes e mensagens automáticas via WhatsApp.',
            self::Gateway => 'Cobrança online por gateway de pagamento.',
        };
    }

    /**
     * Slugs válidos.
     *
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_map(static fn (self $r): string => $r->value, self::cases());
    }

    /** O slug informado é um recurso conhecido? (não lança) */
    public static function valido(string $slug): bool
    {
        return self::tryFrom($slug) !== null;
    }
}
