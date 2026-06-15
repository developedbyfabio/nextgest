<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Configuracao;

/**
 * Identidade visual por estabelecimento (D28).
 *
 * Armazenamento: a aparência fica no banco do TENANT, em `configuracoes`, sob a
 * chave `aparencia` (JSON). Escolha por configuracoes (e não tabela própria):
 * já existe, é chave/valor flexível e acomoda os ganchos futuros (logo, imagens,
 * posição de menu, estilo de ícone) sem nova migration. Sempre mesclado com o
 * tema PADRÃO, então todo tenant nasce com aparência finalizada.
 *
 * Aplicação: em runtime, vira CSS custom properties no escopo do tenant (ver
 * layout do portal). Nada de CSS por tenant compilado — só as variáveis mudam,
 * o que deixa templates e a prévia ao vivo (próximas etapas) triviais.
 */
class Aparencia
{
    public const CHAVE = 'aparencia';

    /** Tema padrão — base de marca neutra (indigo) e clara. */
    public const PADRAO = [
        'cor_principal' => '#4f46e5',   // botões/realces (vira --color-accent do Flux)
        'cor_secundaria' => '#0ea5e9',
        'cor_fundo' => '#f4f4f5',       // fundo da página
        'cor_superficie' => '#ffffff',  // cards/superfícies
        'cor_texto' => '#18181b',       // texto principal
        'cor_texto_suave' => '#71717a', // texto secundário
        'fonte' => "'Instrument Sans', ui-sans-serif, system-ui, sans-serif",
        'tamanho_base' => '16px',

        // Ganchos para as próximas etapas (templates / onboarding). Sem efeito ainda.
        'logo' => null,
        'header_imagem' => null,
        'fundo_imagem' => null,
        'menu_posicao' => 'topo',
        'icone_estilo' => 'outline',
    ];

    /** Aparência do tenant atual mesclada com o padrão. */
    public static function doTenant(): array
    {
        if (! tenancy()->initialized) {
            return self::PADRAO;
        }

        $json = Configuracao::valor(self::CHAVE);
        $config = $json ? json_decode($json, true) : [];

        return array_merge(self::PADRAO, is_array($config) ? $config : []);
    }

    public static function salvar(array $valores): void
    {
        $atual = self::doTenant();
        $merge = array_merge($atual, $valores);

        Configuracao::updateOrCreate(
            ['chave' => self::CHAVE],
            ['valor' => json_encode($merge)],
        );
    }

    /**
     * String de `style` com as CSS custom properties (e o accent do Flux),
     * para injetar no escopo do portal/painel.
     */
    public static function cssVars(?array $a = null): string
    {
        $a = $a ?? self::doTenant();

        $vars = [
            '--cor-principal' => $a['cor_principal'],
            '--cor-secundaria' => $a['cor_secundaria'],
            '--cor-fundo' => $a['cor_fundo'],
            '--cor-superficie' => $a['cor_superficie'],
            '--cor-texto' => $a['cor_texto'],
            '--cor-texto-suave' => $a['cor_texto_suave'],
            // Faz os componentes Flux (botões primary, foco, links) usarem a marca.
            '--color-accent' => $a['cor_principal'],
            '--color-accent-content' => $a['cor_principal'],
            '--color-accent-foreground' => '#ffffff',
            'font-family' => $a['fonte'],
            'font-size' => $a['tamanho_base'],
        ];

        return collect($vars)->map(fn ($v, $k) => "{$k}: {$v}")->implode('; ');
    }
}
