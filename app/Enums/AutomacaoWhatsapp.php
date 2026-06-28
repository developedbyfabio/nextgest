<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Catálogo das automações de WhatsApp (Fatia 3, D77). FONTE DA VERDADE da estrutura:
 * cada automação tem categoria, rótulo, descrição, as VARIÁVEIS disponíveis (placeholders
 * `{var}`) e um template padrão. O estado por tenant (ligado/desligado + template editado)
 * mora no JSON `whatsapp_config.automacoes` — aqui só o catálogo (código).
 *
 * Duas categorias, tratadas DIFERENTE na tela:
 *  - TRANSACIONAL: disparo por evento, 1 cliente. Risco controlado.
 *  - BROADCAST: disparo em massa (marketing) — caminho rápido para BAN do número no
 *    WhatsApp não-oficial; exige opt-in/LGPD. Off por padrão; marcado como sensível.
 *    (O disparo em massa real, com throttle/opt-in, é fatia futura — aqui NÃO dispara.)
 *
 * Nada nesta fatia dispara automaticamente — só configura.
 */
enum AutomacaoWhatsapp: string
{
    // Transacionais
    case LembreteServico = 'lembrete_servico';
    case CobrancaClube = 'cobranca_clube';
    case AvaliacaoPosServico = 'avaliacao_pos_servico';

    // Broadcast / informativo (massa — sensível)
    case Noticias = 'noticias';
    case Funcionamento = 'funcionamento';
    case AvisosGerais = 'avisos_gerais';

    public function categoria(): string
    {
        return match ($this) {
            self::LembreteServico, self::CobrancaClube, self::AvaliacaoPosServico => 'transacional',
            self::Noticias, self::Funcionamento, self::AvisosGerais => 'broadcast',
        };
    }

    /** Broadcast = disparo em massa (sensível, off por padrão). */
    public function broadcast(): bool
    {
        return $this->categoria() === 'broadcast';
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::LembreteServico => 'Lembrete de serviço',
            self::CobrancaClube => 'Cobrança do clube',
            self::AvaliacaoPosServico => 'Avaliação pós-serviço',
            self::Noticias => 'Notícias',
            self::Funcionamento => 'Informativo de funcionamento',
            self::AvisosGerais => 'Avisos gerais',
        };
    }

    public function descricao(): string
    {
        return match ($this) {
            self::LembreteServico => 'Lembra o cliente do horário antes do atendimento.',
            self::CobrancaClube => 'Avisa o assinante sobre a cobrança/vencimento do clube.',
            self::AvaliacaoPosServico => 'Convida o cliente a avaliar após o atendimento.',
            self::Noticias => 'Novidades e promoções para a base de clientes.',
            self::Funcionamento => 'Mudanças de horário, feriados e funcionamento.',
            self::AvisosGerais => 'Comunicados gerais para a base de clientes.',
        };
    }

    /**
     * Variáveis (placeholders `{var}`) disponíveis nesta automação.
     *
     * @return list<string>
     */
    public function variaveis(): array
    {
        return match ($this) {
            self::LembreteServico => ['cliente', 'data', 'hora', 'servico', 'profissional', 'salao'],
            self::CobrancaClube => ['cliente', 'plano', 'valor', 'vencimento', 'salao'],
            self::AvaliacaoPosServico => ['cliente', 'servico', 'profissional', 'link', 'salao'],
            self::Noticias, self::Funcionamento, self::AvisosGerais => ['cliente', 'salao'],
        };
    }

    public function templatePadrao(): string
    {
        return match ($this) {
            self::LembreteServico => 'Olá {cliente}! Lembrando do seu horário em {salao}: {servico} com {profissional} no dia {data} às {hora}. Até lá! 🙂',
            self::CobrancaClube => 'Olá {cliente}! A mensalidade do seu plano {plano} ({valor}) vence em {vencimento}. Qualquer dúvida, fale com o {salao}.',
            self::AvaliacaoPosServico => 'Oi {cliente}! Que tal avaliar seu {servico} com {profissional}? Sua opinião ajuda muito o {salao}: {link}',
            self::Noticias => 'Olá {cliente}! Novidades no {salao}. Fique de olho! 😉',
            self::Funcionamento => 'Olá {cliente}! Aviso de funcionamento do {salao}.',
            self::AvisosGerais => 'Olá {cliente}! Um aviso do {salao}.',
        };
    }

    /**
     * Dados de EXEMPLO para o botão "testar" (nunca cliente real). Cobre todas as
     * variáveis do catálogo. O `{salao}` é resolvido no chamador (nome do tenant).
     *
     * @return array<string, string>
     */
    public static function exemplos(): array
    {
        return [
            'cliente' => 'Maria Souza',
            'data' => '28/06/2026',
            'hora' => '15:30',
            'servico' => 'Corte masculino',
            'profissional' => 'Jorge',
            'valor' => 'R$ 49,90',
            'plano' => 'Clube Ouro',
            'vencimento' => '05/07/2026',
            'link' => 'https://exemplo.test/avaliar',
            'salao' => 'Seu Estabelecimento',
        ];
    }

    /** @return list<self> */
    public static function transacionais(): array
    {
        return array_values(array_filter(self::cases(), fn (self $a) => $a->categoria() === 'transacional'));
    }

    /** @return list<self> */
    public static function broadcasts(): array
    {
        return array_values(array_filter(self::cases(), fn (self $a) => $a->categoria() === 'broadcast'));
    }
}
