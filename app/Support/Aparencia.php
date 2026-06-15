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

    /**
     * Templates (presets) de aparência (D30) — definidos em código, não por tenant.
     * Aplicar um template = copiar estes valores para o tenant (segue editável).
     * Reutilizados pela tela de edição e pelo onboarding (Etapa 3).
     *
     * @var array<string, array<string, mixed>>
     */
    public const TEMPLATES = [
        'neutro' => [
            'rotulo' => 'Neutro', 'descricao' => 'Equilibrado e versátil.',
            'cor_principal' => '#4f46e5', 'cor_secundaria' => '#0ea5e9',
            'cor_fundo' => '#f4f4f5', 'cor_superficie' => '#ffffff',
            'cor_texto' => '#18181b', 'cor_texto_suave' => '#71717a',
            'fonte' => "'Instrument Sans', ui-sans-serif, system-ui, sans-serif",
            'tamanho_base' => '16px', 'menu_posicao' => 'topo', 'icone_estilo' => 'outline',
        ],
        'barbearia' => [
            'rotulo' => 'Barbearia', 'descricao' => 'Âmbar e tons terrosos, ar masculino.',
            'cor_principal' => '#b45309', 'cor_secundaria' => '#57534e',
            'cor_fundo' => '#f5f5f4', 'cor_superficie' => '#ffffff',
            'cor_texto' => '#1c1917', 'cor_texto_suave' => '#78716c',
            'fonte' => 'ui-sans-serif, system-ui, sans-serif',
            'tamanho_base' => '16px', 'menu_posicao' => 'topo', 'icone_estilo' => 'solid',
        ],
        'salao_feminino' => [
            'rotulo' => 'Salão feminino', 'descricao' => 'Rosa e roxo, delicado.',
            'cor_principal' => '#db2777', 'cor_secundaria' => '#a855f7',
            'cor_fundo' => '#fdf2f8', 'cor_superficie' => '#ffffff',
            'cor_texto' => '#1f2937', 'cor_texto_suave' => '#9d6b86',
            'fonte' => "'Instrument Sans', ui-sans-serif, system-ui, sans-serif",
            'tamanho_base' => '16px', 'menu_posicao' => 'topo', 'icone_estilo' => 'outline',
        ],
        'salao_masculino' => [
            'rotulo' => 'Salão masculino', 'descricao' => 'Azul-petróleo e grafite.',
            'cor_principal' => '#0f766e', 'cor_secundaria' => '#334155',
            'cor_fundo' => '#f1f5f9', 'cor_superficie' => '#ffffff',
            'cor_texto' => '#0f172a', 'cor_texto_suave' => '#64748b',
            'fonte' => 'ui-sans-serif, system-ui, sans-serif',
            'tamanho_base' => '16px', 'menu_posicao' => 'topo', 'icone_estilo' => 'outline',
        ],
        'premium' => [
            'rotulo' => 'Premium', 'descricao' => 'Preto e dourado, sofisticado.',
            'cor_principal' => '#111827', 'cor_secundaria' => '#b8860b',
            'cor_fundo' => '#faf9f7', 'cor_superficie' => '#ffffff',
            'cor_texto' => '#111827', 'cor_texto_suave' => '#6b7280',
            'fonte' => "Georgia, 'Times New Roman', serif",
            'tamanho_base' => '16px', 'menu_posicao' => 'lateral', 'icone_estilo' => 'solid',
        ],
        'moderno' => [
            'rotulo' => 'Moderno', 'descricao' => 'Ciano e violeta vibrantes.',
            'cor_principal' => '#06b6d4', 'cor_secundaria' => '#8b5cf6',
            'cor_fundo' => '#f8fafc', 'cor_superficie' => '#ffffff',
            'cor_texto' => '#0f172a', 'cor_texto_suave' => '#64748b',
            'fonte' => 'ui-sans-serif, system-ui, sans-serif',
            'tamanho_base' => '16px', 'menu_posicao' => 'topo', 'icone_estilo' => 'outline',
        ],
        'minimalista' => [
            'rotulo' => 'Minimalista', 'descricao' => 'Preto e branco, tipografia limpa.',
            'cor_principal' => '#111827', 'cor_secundaria' => '#6b7280',
            'cor_fundo' => '#ffffff', 'cor_superficie' => '#ffffff',
            'cor_texto' => '#111827', 'cor_texto_suave' => '#9ca3af',
            'fonte' => 'ui-sans-serif, system-ui, sans-serif',
            'tamanho_base' => '15px', 'menu_posicao' => 'topo', 'icone_estilo' => 'outline',
        ],
    ];

    /** Campos visuais de um template (sem rótulo/descrição), prontos para o form. */
    public static function template(string $chave): ?array
    {
        $t = self::TEMPLATES[$chave] ?? null;

        if ($t === null) {
            return null;
        }

        unset($t['rotulo'], $t['descricao']);

        return $t;
    }

    /**
     * URL pública de um arquivo enviado pelo tenant (logo/cabeçalho/fundo),
     * servido por App\Http\Controllers\TenantArquivoController. Null se não há
     * arquivo ou se estamos fora do contexto de um tenant.
     */
    public static function urlArquivo(?string $path): ?string
    {
        if (! $path || ! tenancy()->initialized) {
            return null;
        }

        return route('tenant.arquivo', ['tenant' => tenant('id'), 'path' => $path]);
    }

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
