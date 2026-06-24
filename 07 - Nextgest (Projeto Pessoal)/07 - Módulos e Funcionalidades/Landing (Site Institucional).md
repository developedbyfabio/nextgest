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
  - `mockup-celular.blade.php` — smartphone em **HTML/CSS** (sem imagem, estático) que **espelha
    fielmente a tela REAL** do portal — passo **Data e horário** (`Portal\Agendar`, passo 4 /
    "Passo 3 de 3"). Mesmas seções e rótulos do app: cabeçalho do salão → "Novo agendamento" +
    "Passo 3 de 3 · Data e horário" → barra de progresso (3 cheios) → "Quando?" → campo "Dia" →
    "Horários disponíveis" (grade, um selecionado) → card do slot → Voltar/Confirmar. O accent
    "principal" do portal é representado pelo **degradê de marca** (a landing não emite `--cor-*`).
    Só espelha o visual — NÃO importa o Livewire real nem toca o motor.
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
- **Âncoras/scroll:** `scroll-smooth` no `<html>` + **`scroll-mt-24`** nos alvos (`#recursos`,
  `#contato`) para o título não ficar atrás do header sticky. Os links de seções que **ainda não
  existem** (`#como-funciona`, `#planos`, `#faq`, Fases 2/3) têm um **guard** leve
  (`document.querySelector(href) || preventDefault()`) → no-op gracioso: não rolam para o lugar
  errado nem deixam hash morto. Hero: "Quero conhecer" → `#contato`, "Ver demonstração" → `#recursos`.

## Guarda-corpo respeitado
- Rotas de **admin/portal/painel** intactas (a landing é só a raiz). Sem migration, sem banco,
  sem tocar o motor/negócio. Teste de tema continua verde (a landing **não emite `--cor-principal`**:
  usa cores de marca fixas). Suíte 443. Build (fontes locais) ok. Mobile sem scroll horizontal.
- A **logo** `public/nextgest-logo.png` passou a ser **versionada** (a landing a referencia; produção puxa via git).

## Fase 2 (FEITA) — seções abaixo do hero
Ordem no `<main>`: Hero → **Como funciona** → **Recursos (bento)** → **Tipos de negócio** →
**Preview do painel**. A faixa de destaques da Fase 1 (3 pilares com `card-destaque`) foi
**substituída** pelo bento (os 3 pilares viraram tiles em destaque) — `card-destaque.blade.php`
fica no repo como componente reutilizável, sem uso na página.
- **Como funciona** (`#como-funciona`, banda slate-50): 5 passos reais (`x-landing.passo` — número em
  degradê + ícone Heroicons): cadastrar serviços → equipe/horários → compartilhar link → cliente
  agenda → acompanhar no painel.
- **Recursos** (`#recursos`): **bento** `grid-cols-2 lg:grid-cols-4` com `x-landing.card-bento`. 12
  recursos; 4 em **destaque** (`destaque` = degradê + `col-span-2`): Agenda online, Link público,
  Multi-estabelecimento, Relatórios. O link "Recursos" do header ancora aqui (id movido da antiga
  faixa para o bento).
- **Tipos de negócio** (`#para-quem`, banda slate-50): 6 `x-landing.card-publico` (Barbearias /
  Salões / Estética / Autônomos / Pequenas equipes / Múltiplas unidades), cada um com 1 benefício específico.
- **Preview do painel** (`#painel`): texto + `x-landing.mockup-painel` — janela de desktop
  espelhando a **agenda semanal REAL** (`Painel\Agenda\Index`, visão "semana"): controles
  (‹ Hoje › · data · [Dia][Semana]) + 7 colunas de dia (hoje destacado) com cartões de agendamento
  (barra de status à esquerda nas **cores reais** STATUS_HEX + horário + cliente; "—" se vazio).
  Estático/HTML-CSS; accent pelo degradê (não emite `--cor-*`). **Gotcha:** o wrapper desktop é CSS
  grid (`lg:grid-cols-5`), mas no MOBILE virou `flex flex-col` — um grid de track `auto` no mobile
  cresce até o `max-content` da grade de 7 colunas (overflow horizontal); flex-col + `min-w-0` na
  coluna + `overflow-hidden` na seção resolvem.
- Ritmo: seções alternam fundo branco / banda `slate-50` (dark: `slate-900/40`), `py-16 sm:py-20`,
  `scroll-mt-24` nos alvos. Verificado por Playwright (desktop/mobile/dark; âncoras com offset;
  mobile sem scroll horizontal; mockup == agenda real).

## Pendente — Fase 3 (NÃO feita)
**Planos** (R$49 / R$99 / R$199) + **FAQ** + **CTA final**. Âncoras `#planos`/`#faq` já no header/
footer (hoje no-op gracioso). Construir reaproveitando os componentes das Fases 1/2.

## Personalização (Fabio pode ajustar depois)
- **Textos:** headline/subheadline (landing.blade.php), textos dos 3 cards, tagline do footer.
- **Links de contato** (footer + `botoes-flutuantes.blade.php`): WhatsApp `5541991541757`,
  Instagram `instagram.com/nextgest`, e-mail `fabio9384@gmail.com`.
- **Marca:** degradê `from-violet-600 via-indigo-600 to-blue-600` (trocar nos componentes se mudar a paleta).

## Deploy
Só publicar em produção **quando a landing estiver mais completa** (Fases 2/3) — ver
[[Roteiro de Deploy Seguro]]. Por ora, fica no dev/`main`.
