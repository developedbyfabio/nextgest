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
        // Favicon do tenant (D90): PNG 32x32 processado no upload (ver App\Support\
        // Favicon). null → cai no favicon padrão do Nextgest (ver linkFavicon).
        'favicon' => null,
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

    /**
     * Catálogo de fontes oferecidas (D36 — tipografia faz parte da marca).
     * Chave = stack de `font-family` (valor persistido em `fonte`); o `google` é
     * a especificação de família p/ a API do Google Fonts (`?family=...`), ou
     * `null` quando é fonte do sistema/empacotada (Instrument Sans já vem no build).
     * As fontes Google são carregadas SOB DEMANDA por um `<link>` no `<head>`
     * (ver linkFonteGoogle) — nada de embutir dezenas de arquivos.
     *
     * @var array<string, array{label: string, google: ?string}>
     */
    public const FONTES = [
        "'Instrument Sans', ui-sans-serif, system-ui, sans-serif" => ['label' => 'Instrument Sans (padrão)', 'google' => null],
        'ui-sans-serif, system-ui, sans-serif' => ['label' => 'Sistema (sans-serif)', 'google' => null],
        "'Inter', ui-sans-serif, sans-serif" => ['label' => 'Inter', 'google' => 'Inter:wght@400;500;600;700'],
        "'Poppins', ui-sans-serif, sans-serif" => ['label' => 'Poppins', 'google' => 'Poppins:wght@400;500;600;700'],
        "'Montserrat', ui-sans-serif, sans-serif" => ['label' => 'Montserrat', 'google' => 'Montserrat:wght@400;500;600;700'],
        "'Roboto', ui-sans-serif, sans-serif" => ['label' => 'Roboto', 'google' => 'Roboto:wght@400;500;700'],
        "'Open Sans', ui-sans-serif, sans-serif" => ['label' => 'Open Sans', 'google' => 'Open+Sans:wght@400;500;600;700'],
        "'Lato', ui-sans-serif, sans-serif" => ['label' => 'Lato', 'google' => 'Lato:wght@400;700'],
        "'Nunito', ui-sans-serif, sans-serif" => ['label' => 'Nunito', 'google' => 'Nunito:wght@400;600;700'],
        "'Raleway', ui-sans-serif, sans-serif" => ['label' => 'Raleway', 'google' => 'Raleway:wght@400;500;600;700'],
        "'Work Sans', ui-sans-serif, sans-serif" => ['label' => 'Work Sans', 'google' => 'Work+Sans:wght@400;500;600;700'],
        "'Space Grotesk', ui-sans-serif, sans-serif" => ['label' => 'Space Grotesk', 'google' => 'Space+Grotesk:wght@400;500;700'],
        "'Playfair Display', Georgia, serif" => ['label' => 'Playfair Display (serif)', 'google' => 'Playfair+Display:wght@400;600;700'],
        "'Merriweather', Georgia, serif" => ['label' => 'Merriweather (serif)', 'google' => 'Merriweather:wght@400;700'],
        "Georgia, 'Times New Roman', serif" => ['label' => 'Georgia (serif do sistema)', 'google' => null],
        "'JetBrains Mono', ui-monospace, monospace" => ['label' => 'JetBrains Mono (mono)', 'google' => 'JetBrains+Mono:wght@400;500;700'],
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

    /**
     * `<link rel="icon">` para o `<head>` (D90) — ícone da aba do navegador. Usa o
     * favicon do tenant (PNG 32x32 processado no upload) quando há; senão, o favicon
     * PADRÃO do Nextgest (mesmo de landing/admin). Chamado nos layouts do tenant
     * (portal/painel/auth), no MESMO ponto onde a tipografia/acento já entram.
     *
     * O favicon do tenant tem nome ÚNICO por upload → URL nova a cada troca →
     * cache-busting embutido (o navegador cacheia favicon agressivamente).
     */
    public static function linkFavicon(?array $a = null): string
    {
        $path = null;

        if (tenancy()->initialized) {
            $a = $a ?? self::doTenant();
            $path = $a['favicon'] ?? null;
        }

        // Favicon do tenant (PNG) ou fallback pro padrão do Nextgest (também PNG).
        $url = self::urlArquivo($path) ?? asset('nextgest-logo.png');

        return '<link rel="icon" type="image/png" href="'.e($url).'">';
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
     * Cor de frente (preto/branco) legível sobre uma cor de fundo (D27/6B).
     *
     * A cor principal escolhida pelo dono pode ser clara OU escura; texto sobre
     * ela (ex.: dentro de botões primários) precisa contrastar. Usa a luminância
     * relativa do WCAG: abaixo do limiar (~0.179, onde o contraste de preto e
     * branco se cruza) o fundo é escuro → texto branco; acima, texto quase-preto.
     *
     * `$claro`/`$escuro` permitem casar com a paleta do design (zinc) em vez de
     * preto/branco puros. Hex de 3 ou 6 dígitos; entrada inválida → assume escuro.
     */
    public static function corDeContraste(string $hex, string $claro = '#ffffff', string $escuro = '#18181b'): string
    {
        return self::luminancia($hex) < 0.179 ? $claro : $escuro;
    }

    /** Luminância relativa (WCAG) de uma cor hex, no intervalo [0,1]. */
    public static function luminancia(string $hex): float
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return 0.0; // entrada inválida → trata como escuro (texto branco)
        }

        $canal = static function (int $v): float {
            $s = $v / 255;

            return $s <= 0.03928 ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $canal((int) hexdec(substr($hex, 0, 2)))
            + 0.7152 * $canal((int) hexdec(substr($hex, 2, 2)))
            + 0.0722 * $canal((int) hexdec(substr($hex, 4, 2)));
    }

    /**
     * Mapa completo das CSS custom properties da aparência (chave => valor),
     * incluindo o accent do Flux e a cor de frente legível sobre a marca.
     *
     * @return array<string, string>
     */
    protected static function mapaVars(array $a): array
    {
        $sobre = self::corDeContraste($a['cor_principal']);

        return [
            '--cor-principal' => $a['cor_principal'],
            '--cor-secundaria' => $a['cor_secundaria'],
            '--cor-fundo' => $a['cor_fundo'],
            '--cor-superficie' => $a['cor_superficie'],
            '--cor-texto' => $a['cor_texto'],
            '--cor-texto-suave' => $a['cor_texto_suave'],
            // Cor de frente legível sobre a cor principal (6B) — texto em botões etc.
            '--cor-sobre-principal' => $sobre,
            // Faz os componentes Flux (botões primary, foco, links) usarem a marca.
            '--color-accent' => $a['cor_principal'],
            '--color-accent-content' => $a['cor_principal'],
            '--color-accent-foreground' => $sobre,
            'font-family' => $a['fonte'],
            'font-size' => $a['tamanho_base'],
        ];
    }

    /**
     * String de `style` com TODAS as CSS custom properties (identidade completa:
     * fundo/superfície/texto/fonte + accent). Para o PORTAL do cliente e telas de
     * auth do tenant, onde a identidade do estabelecimento domina a tela.
     */
    public static function cssVars(?array $a = null): string
    {
        $a = $a ?? self::doTenant();

        return collect(self::mapaVars($a))->map(fn ($v, $k) => "{$k}: {$v}")->implode('; ');
    }

    /**
     * String de `style` com a MARCA do tenant (Etapa D): acento (cor principal/
     * secundária + accent do Flux + cor de frente legível) e a TIPOGRAFIA. NÃO
     * emite superfícies — fundo/superfície/texto vêm dos tokens de claro/escuro
     * (app.css `:root`/`.dark`, controlados pelo @fluxAppearance). Assim a marca é
     * constante nos dois modos e as superfícies seguem o modo escolhido.
     */
    public static function cssVarsAcento(?array $a = null): string
    {
        $a = $a ?? self::doTenant();

        $marca = ['--cor-principal', '--cor-secundaria', '--cor-sobre-principal',
            '--color-accent', '--color-accent-content', '--color-accent-foreground',
            'font-family', 'font-size'];

        return collect(self::mapaVars($a))
            ->only($marca)
            ->map(fn ($v, $k) => "{$k}: {$v}")
            ->implode('; ');
    }

    /** Especificação Google da fonte atual do tenant — null se for fonte do sistema. */
    public static function googleDaFonte(?array $a = null): ?string
    {
        $a = $a ?? self::doTenant();

        return self::FONTES[$a['fonte']]['google'] ?? null;
    }

    /**
     * `<link>`(s) para carregar a fonte (Google) atual do tenant no `<head>` —
     * string vazia quando a fonte é do sistema/empacotada. Usado nos layouts
     * (portal/painel/auth) para que a tipografia escolhida realmente apareça.
     */
    public static function linkFonteGoogle(?array $a = null): string
    {
        $g = self::googleDaFonte($a);

        return $g === null ? '' : self::montarLinkGoogle([$g]);
    }

    /**
     * `<link>` único que carrega TODAS as fontes Google do catálogo — para a
     * PRÉVIA ao vivo do editor de aparência, onde qualquer seleção precisa
     * renderizar na hora (antes de salvar/recarregar).
     */
    public static function linksFontesGoogle(): string
    {
        $familias = collect(self::FONTES)->pluck('google')->filter()->values()->all();

        return self::montarLinkGoogle($familias);
    }

    /**
     * Monta o(s) `<link>` para a API do Google Fonts a partir de especificações
     * de família do catálogo FONTES (lista fechada — sem entrada do usuário, logo
     * sem injeção). A `href` ainda é escapada por segurança.
     *
     * @param  array<int, string>  $familias
     */
    private static function montarLinkGoogle(array $familias): string
    {
        if ($familias === []) {
            return '';
        }

        $query = collect($familias)->map(fn ($f) => 'family='.$f)->implode('&');
        $href = 'https://fonts.googleapis.com/css2?'.$query.'&display=swap';

        return '<link rel="preconnect" href="https://fonts.googleapis.com">'
            .'<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            .'<link rel="stylesheet" href="'.e($href).'">';
    }
}
