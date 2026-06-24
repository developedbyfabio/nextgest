# Landing (Site Institucional)

> Página pública da marca, na **raiz** do domínio central (`nextgest.com.br` →
> rota `landing`, `routes/web.php`). NÃO passa por tenancy. Atualizado: 2026-06-24.

## Decisão / escopo
SaaS premium, leve e confiável (NÃO "data center" preto-neon). Marca: degradê
**violeta `#7C3AED` → índigo `#4F46E5` → azul `#2563EB`** (= `violet-600 / indigo-600 /
blue-600` do Tailwind), com o **motivo geométrico de blocos** da logo (o "N") como fio
condutor (grade sutil no hero, bloco em degradê no canto dos cards). Tipografia: Instrument
Sans (local, @fontsource). Sem Livewire — só Blade + Alpine (vem do Flux) para interações leves.

## Fase 1 (FEITA) — primeira dobra + globais
- **`resources/views/landing.blade.php`** (standalone; `@fluxAppearance` + `@fluxScripts`).
  `<html class="scroll-smooth">`, `id="topo"`. SEO: `<title>`/`<meta description>` definidos,
  favicon + Open Graph com a logo, **um único `<h1>`** (hero), `<h2>` nas seções, semântica
  `<header>/<main>/<section>/<footer>`.
- **Componentes** em `resources/views/components/landing/` (reutilizáveis nas Fases 2/3):
  - `header.blade.php` — sticky **glassmorphism** (`backdrop-blur` + branco/escuro translúcido),
    logo + wordmark, nav por âncoras (Recursos/Como funciona/Planos/FAQ/Contato), "Acesso
    administrativo" (`route('admin.login')`), CTA "Começar agora" (degradê, → `#contato`),
    **menu mobile** (Alpine `x-data`/`x-show`/`x-transition`), `<x-landing.tema-toggle>`.
  - `tema-toggle.blade.php` — alterna claro/escuro pela **abordagem padrão do projeto**
    (`$flux.appearance`, igual painel/portal), lendo/invertendo a classe `.dark`. **Sem
    localStorage nosso.**
  - `mockup-celular.blade.php` — smartphone em **HTML/CSS** (sem imagem) com a tela de
    agendamento do cliente (cabeçalho do salão, serviço, grade de horários com um selecionado,
    "Confirmar"). Visual/estático.
  - `card-destaque.blade.php` — card de recurso (ícone Heroicons em bloco de marca, bloco
    geométrico no canto, hover com elevação). Props: `icone`, `titulo` + slot.
  - `footer.blade.php` — `<footer id="contato">` com marca/tagline, colunas de links, contato,
    "© {ano} Nextgest".
  - `botoes-flutuantes.blade.php` — fixos no canto inf. direito: **WhatsApp** (verde),
    **Instagram** (degradê), **E-mail** (degradê de marca), com `aria-label`, tooltip no hover,
    nova aba; SVG de marca p/ WhatsApp/IG, Heroicons p/ e-mail.
- **Hero:** 2 colunas (texto + mockup), empilha no mobile. Badge, headline "Sua agenda no
  **piloto automático**" (degradê no destaque), subheadline, 2 CTAs (degradê + outline) com
  micro-interação de hover, reveal suave no load (`.ng-suben`/`.ng-suben-2` em `app.css`,
  respeitando `prefers-reduced-motion`). Fundo: degradê + grade de blocos (mascarada) + brilho radial.
- **Faixa de destaques** (`id="recursos"`): os 3 cards atuais repaginados (Agenda inteligente /
  Portal do cliente / Equipe e permissões).
- **Dark mode** premium (fundo `#0B1120`, superfícies `slate-800`), via classe `.dark` do Flux.

## Guarda-corpo respeitado
- Rotas de **admin/portal/painel** intactas (a landing é só a raiz). Sem migration, sem banco,
  sem tocar o motor/negócio. Teste de tema continua verde (a landing **não emite `--cor-principal`**:
  usa cores de marca fixas). Suíte 443. Build (fontes locais) ok. Mobile sem scroll horizontal.
- A **logo** `public/nextgest-logo.png` passou a ser **versionada** (a landing a referencia; produção puxa via git).

## Pendente — Fases 2 e 3 (NÃO feitas)
Seções com âncoras já prontas no header/footer (hoje sem alvo, no-op): **Como funciona**,
**bento de recursos**, **tipos de negócio**, **preview do painel completo**, **Planos**, **FAQ**,
**CTA final**. Construir reaproveitando os componentes da Fase 1.

## Personalização (Fabio pode ajustar depois)
- **Textos:** headline/subheadline (landing.blade.php), textos dos 3 cards, tagline do footer.
- **Links de contato** (footer + `botoes-flutuantes.blade.php`): WhatsApp `5541991541757`,
  Instagram `instagram.com/nextgest`, e-mail `fabio9384@gmail.com`.
- **Marca:** degradê `from-violet-600 via-indigo-600 to-blue-600` (trocar nos componentes se mudar a paleta).

## Deploy
Só publicar em produção **quando a landing estiver mais completa** (Fases 2/3) — ver
[[Roteiro de Deploy Seguro]]. Por ora, fica no dev/`main`.
