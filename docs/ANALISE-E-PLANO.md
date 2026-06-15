# Nextgest — Análise do estado atual e plano da evolução visual

Levantamento das frentes da evolução pedida (portal do cliente, identidade visual
por estabelecimento, onboarding guiado com prévia ao vivo, templates, dashboard do
dono, kanban, áreas de gestão). Legenda: ✅ existe · 🐛 bugado · 🚧 incompleto ·
♻️ refatorar · ✨ criar do zero.

## 1. Portal do cliente
- ✅ Fluxo completo: home (público e logado), wizard de agendar (serviço →
  profissional → dia → horário → confirmar), próximos/cancelar, login/registrar.
  Motor de disponibilidade e concorrência sólidos (1C).
- 🐛 **(corrigido nesta etapa)** textos invisíveis (nome do serviço/profissional)
  por dark-mode do sistema sobre superfície clara forçada.
- 🚧 login/registrar usam o layout "split" da marca Nextgest, não o tema do
  estabelecimento — alinhar quando os templates existirem.
- ♻️ alguns realces ainda em indigo fixo; migrar gradualmente para as vars do tema.

## 2. Identidade visual por estabelecimento (tema)
- ✅ **(criado nesta etapa)** fundação: `App\Support\Aparencia` (armazenamento em
  `configuracoes.aparencia` JSON + defaults) e aplicação via CSS variables no
  portal (`--cor-principal/secundaria/fundo/superficie/texto`, e `--color-accent`
  do Flux). Ganchos para logo/imagens/menu/ícone já previstos.
- ✨ tela de edição de tema (próxima etapa) — formulário do dono.
- 🚧 aplicar as vars também no painel/auth do tenant (hoje só portal).

## 3. Onboarding guiado com prévia ao vivo
- ✨ tudo a criar: wizard pós-criação do tenant (dados do negócio → tema/template
  → primeiro serviço/horário) com **prévia ao vivo** (iframe/preview do portal
  reagindo às CSS vars). A fundação de tema (item 2) já habilita a prévia trocando
  só variáveis.

## 4. Templates visuais
- ✨ a criar: conjunto de "presets" de `Aparencia` (combinações de cor/fonte/menu)
  selecionáveis no onboarding/edição. Como é só um conjunto de variáveis, é barato
  sobre a fundação atual.

## 5. Dashboard do dono (gráficos/indicadores)
- 🚧 hoje o `painel.dashboard` é um placeholder ("em construção").
- ✨ criar: indicadores (agendamentos do dia/semana, faturamento, taxa de
  comparecimento, top serviços/profissionais) + gráficos. Há dados (agendamentos,
  vendas no schema). Precisa de uma lib de charts (ex.: Chart.js via Vite).

## 6. Kanban
- 🚧 schema existe (`kanban_*`), sem UI.
- ✨ criar: quadros (atendimento e CRM), colunas, cartões, drag-and-drop. Fatia
  própria; depende de Livewire + uma lib de DnD acessível.

## 7. Áreas de gestão (cadastros)
- ✅ unidades, serviços, equipe, horários, papéis, bloqueios, agenda (dia/semana,
  manual, status, remarcar) — funcionando, com permissões e testes.
- ♻️ pós-tema: aplicar identidade visual e revisar densidade/affordances.

## Fundação transversal já entregue
- Design system (Flux + `x-ng.*`), dark mode no painel/admin, 100 testes + smoke
  HTTP. Multi-tenancy por caminho com Livewire estável (endpoint único +
  persistent middleware).
- **Tema:** `App\Support\Aparencia` (CSS vars por tenant), 7 presets em código
  (`Aparencia::TEMPLATES`), prévia reutilizável `x-ng.previa-portal`.

## Ordem de implementação proposta
1. **Etapa 1 (esta):** corrigir o portal + fundação de tema por CSS vars. ✅
2. **Etapa 2:** templates (presets de tema) + tela de edição de aparência do dono
   (`painel.aparencia`, permissão `gerir_aparencia`) + prévia ao vivo reutilizável
   + uploads de logo/cabeçalho/fundo por tenant. ✅
3. **Etapa 3:** onboarding guiado com prévia ao vivo (usa os presets/edição).
4. **Etapa 4:** dashboard do dono (indicadores + gráficos).
5. **Etapa 5:** kanban (atendimento + CRM).
6. **Etapa 6:** polimento das áreas de gestão sob o tema e do portal logado.

> Racional da ordem: tema é base de templates e da prévia; onboarding consome
> ambos; dashboard e kanban são módulos independentes que entram depois da
> identidade visual estar madura.

> **Uploads por tenant (resolvido):** logo/cabeçalho/fundo são gravados no disco
> `public` (isolado por tenant pelo `FilesystemTenancyBootstrapper`, em
> `storage/tenant{id}/app/public`). Como a rota de assets do stancl
> (`tenancy/assets/{path?}`, helper `tenant_asset()`) identifica o tenant por
> DOMÍNIO — incompatível com este projeto (por CAMINHO) — criamos rota própria
> `GET /{tenant}/arquivo/{path}` (`tenant.arquivo`) com `InitializeTenancyByPath`
> e `App\Http\Controllers\TenantArquivoController` (serve via `response()->file`
> com proteção anti path-traversal). URLs via `Aparencia::urlArquivo($path)`.
