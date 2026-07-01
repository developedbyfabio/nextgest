# Documentos Legais e Rodapé do Portal

> Projeto: [[Nextgest - Visão Geral]] · Decisões: [[Decisões de Arquitetura]] (D93) ·
> Relacionado: [[Identidade Visual do Estabelecimento (Tema)]] · Atualizado: 2026-07-01.

## Ideia (D93)
O portal do cliente ganhou um **rodapé compartilhado** com links para **Política de
Privacidade** e **Termos de Uso**, e **duas páginas públicas** (sem login) com esses
documentos. Conteúdo **único e compartilhado** por todos os tenants; muda só o **slug**
na URL e o **nome do estabelecimento** exibido no cabeçalho da página.

## URLs (públicas, por tenant)
- `/{tenant}/politica-de-privacidade` → `App\Livewire\Portal\PoliticaPrivacidade`
- `/{tenant}/termos-de-uso` → `App\Livewire\Portal\TermosUso`
- Rotas no grupo **público** de `routes/tenant.php` (mesmo grupo do `tenant.home`,
  `middleware(['tenant'])` + `prefix('{tenant}')`), **sem** `auth`/`guest`. Nomes:
  `tenant.politica-privacidade` e `tenant.termos-uso`.
- Páginas são **componentes Livewire full-page** (`#[Layout('components.layouts.portal')]`
  + `#[Title]`) — o padrão do portal —, então renderizam **dentro do layout do portal**
  com o tema do tenant (cores, tipografia, marca, claro/escuro) e ganham o rodapé.

## Onde o conteúdo mora (e por quê)
- **Blade, não markdown.** Cada documento é **um único arquivo**:
  `resources/views/livewire/portal/politica-privacidade.blade.php` e `.../termos-uso.blade.php`.
  Justificativa: **sem dependência nova** (nada de parser markdown/plugin typography, que
  brigaria com a regra de build sem rede) e **estilização direta pelo tema** (CSS vars de
  claro/escuro). Um arquivo por documento = fonte única; nenhuma cópia por tenant.
- **Moldura compartilhada:** `x-portal.documento-legal` (voltar + cabeçalho com nome/título/
  data/versão + `.ng-prosa`). O texto de cada doc entra no slot.
- **Metadados:** `App\Support\Legal` (`VERSAO`, `ATUALIZADO_EM`, `atualizadoEmLabel()`) —
  fonte única de versão/data para os dois documentos.
- **Prosa temática:** classe `.ng-prosa` em `app.css` estiliza h2/h3/p/ul/ol/strong/a com as
  vars do tema (legível em claro/escuro), sem plugin de typography.

## Rodapé e consentimento
- `x-portal.rodape` (**partial único**): "Powered by Nextgest" + os dois links legais do
  **próprio tenant**. Estende o rodapé antigo (que só tinha "Powered by Nextgest"). Usado
  no layout do portal (`portal.blade.php`) **e** no layout de auth do cliente
  (`portal-auth.blade.php`) → aparece em **home, login e registro** (e nas páginas legais).
- `x-portal.consentimento` (**partial único**): a linha *"Ao continuar, você concorda com a
  Política de Privacidade e os Termos de Uso"* nas telas de **login** e **registro** do
  cliente, com os dois links do tenant. Estilo pelo tema; sobre imagem de fundo herda a
  superfície de leitura da coluna do formulário (não "some" na foto).

## Conteúdo dos textos
- pt-BR, linguagem de **LGPD**, genéricos à plataforma Nextgest (servem a qualquer tenant).
- **Placeholders explícitos** (sem dados reais): `[e-mail de contato]`, `[encarregado/DPO]`,
  `[endereço]`, `[foro/comarca]`, `[responsável/estabelecimento]`.
- Política: 12 seções (intro/aplicação, definições LGPD, dados coletados, finalidades e
  bases legais, compartilhamento, cookies, retenção, segurança, direitos do titular — art.
  18, menores, alterações, contato/DPO). Termos: 12 seções (objeto/aceitação, definições,
  cadastro/conta, condutas vedadas, agendamentos/cancelamentos, pagamentos/Clube,
  comunicações/opt-out, propriedade intelectual, limitação de responsabilidade, suspensão/
  encerramento, legislação/foro, contato).

> [!warning] Revisão jurídica obrigatória
> Estes textos são **base/modelo** e **NÃO** constituem aconselhamento jurídico nem
> conformidade legal definitiva. Antes de valerem como documento legal, precisam de
> **revisão por advogado**. Em especial, a definição de papéis LGPD —
> **controlador (estabelecimento/tenant) × operador (Nextgest)** — foi redigida no modelo
> SaaS usual, mas **deve ser validada juridicamente** (pode variar conforme o contrato e o
> tratamento real de cada dado). O corpo público **não** afirma conformidade definitiva.

## Fora de escopo (próximas fatias)
- Edição do conteúdo legal **por tenant**; banner/aceite de **cookies** (LGPD); versionar
  aceites por usuário.

## Testes
`tests/Feature/Portal/PortalLegalTest.php` — 200 público nas duas URLs; conteúdo LGPD e
layout do portal; dois tenants servem o mesmo conteúdo nas próprias URLs; rodapé em
home/login/registro com os links do tenant; linha de consentimento no login/registro.
