---
projeto: Nextgest
tipo: decisões-de-arquitetura
status: vivo
criado: 2026-06-14
tags: [nextgest, arquitetura, decisões]
---

# Nextgest — Decisões de Arquitetura

Registro das decisões tomadas, com motivo. Documento vivo: novas decisões entram
ao fim, sem apagar as antigas. Ver também [[Nextgest - Visão Geral]].

## D01 — Multi-tenancy: banco por tenant (stancl/tenancy)
- **Decisão:** cada estabelecimento tem seu próprio banco, gerenciado pelo
  pacote `stancl/tenancy`.
- **Motivo:** isolamento físico dos dados (forte argumento de segurança e venda).
- **Alternativas:** banco único com `tenant_id` (mais leve, porém risco de
  vazamento entre clientes por query sem filtro).
- **Custo aceito:** operação mais pesada (mais bancos, backups, migrations em N
  bancos) — mitigado pela automação do stancl.

## D02 — Identificação por caminho (path-based)
- **Decisão:** tenant identificado pela URL `nextgest.com.br/{slug}`.
- **Motivo:** preferência do dono; um só domínio e certificado SSL.
- **Cuidados:** lista de slugs reservados (`admin`, `login`, `api`...) e sessão
  escopada por tenant para evitar vazamento de login entre estabelecimentos.

## D03 — Logins separados (guards)
- **Decisão:** `users` (equipe interna, com papéis spatie) e `clientes`
  (clientes finais, portal de agendamento) em tabelas e guards separados.
- **Motivo:** segurança — cliente final nunca ganha permissão de equipe.

## D04 — RBAC flexível (spatie/laravel-permission)
- **Decisão:** papéis prontos (Dono, Gerente, Recepção, Profissional), com o dono
  podendo editar permissões e criar papéis personalizados.
- **Regra:** financeiro é uma permissão separada (`ver_financeiro`), por padrão
  só do Dono.

## D05 — Multi-unidade por tenant
- **Decisão:** um tenant pode ter várias unidades (filiais); some na interface
  quando há apenas uma.
- **Motivo:** adicionar unidades depois, com agenda em produção, é caro.

## D06 — Autoagendamento pelo cliente
- **Decisão:** cliente cria conta e agenda sozinho pelo portal (filial → serviço
  → profissional → dia/horário). A equipe também pode agendar.

## D07 — Agendamento com múltiplos serviços, um profissional
- **Decisão:** um agendamento pode ter vários serviços (corte + barba), mas um
  único profissional atende o agendamento inteiro.
- **Detalhe técnico:** itens em `agendamento_servico` com **snapshot** de preço
  e duração.

## D08 — Serviço do tenant disponível por unidade (pivô)
- **Decisão:** serviço pertence ao tenant e fica disponível em uma ou mais
  unidades via `servico_unidade`.

## D09 — Pagamentos: nunca armazenar dados de cartão (a aplicar)
- **Decisão preliminar:** gateway plugável; guardar apenas o **token** que o
  gateway devolve, nunca número de cartão/CVV (evita conformidade PCI-DSS).
- **Status:** a detalhar no bloco de Pagamentos.

## D10 — Portal do cliente: mobile-first
- **Decisão:** o portal de agendamento do cliente final é desenvolvido primeiro
  para celular (onde o cliente realmente usa) e depois adaptado para desktop
  (design responsivo, mas com prioridade no mobile).
- **Motivo:** o cliente acessa pelo navegador do celular; é o uso real principal.
- **Impacto técnico:** layout Livewire + Alpine pensado em telas estreitas
  primeiro; o painel de gestão (equipe) segue caminho próprio.

## D11 — Confirmação de agendamento configurável
- **Decisão:** cada estabelecimento escolhe se o agendamento feito pelo cliente
  entra confirmado automaticamente ou pendente de aprovação da equipe. Padrão:
  confirmado automaticamente.
- **Impacto técnico:** exige um lugar para guardar configurações do tenant
  (tabela `configuracoes`). Bloqueios (folgas/almoço) confirmados no núcleo.

## D12 — Estoque opcional, por unidade
- **Decisão:** cada produto marca se controla estoque; a quantidade é por filial
  (`produto_unidade`). Movimentações registradas em `movimentacoes_estoque`.
- **Motivo:** multi-unidade exige estoque separado; rastreabilidade para gastos.

## D13 — Venda/comanda unificada
- **Decisão:** a venda reúne produtos e serviços e pode estar ligada a um
  agendamento ou ser avulsa (balcão). É a base do financeiro.
- **Detalhe:** itens em `venda_itens` com snapshot; serviços vindos de um
  agendamento são copiados para a venda no fechamento.

## D14 — Comissão por profissional
- **Decisão:** comissão por item de venda, atribuída ao profissional que executou
  ou vendeu. % padrão no serviço/produto; valor gravado como snapshot no item.
- **Confirmado:** override de % por profissional via `comissoes_profissional`.

## D15 — Clube: benefício configurável (ilimitado ou cota)
- **Decisão:** cada benefício de um plano é ilimitado ou tem cota (X usos por
  ciclo). Configurado em `plano_beneficios`.

## D16 — Clube: restrição por benefício
- **Decisão:** um benefício pode restringir dias da semana e faixa de horário
  (ex.: "corte seg–sáb"). Campos `dias_semana_permitidos`, `hora_inicio`,
  `hora_fim` em `plano_beneficios`.

## D17 — Clube: descontos/cupons
- **Decisão:** além dos serviços inclusos, o plano pode dar desconto em produtos
  e serviços extras (`plano_descontos`). Refletido no preço do item da venda,
  com `assinatura_id` no `venda_item`.

## D18 — Clube: ciclo da cota por data de adesão
- **Decisão:** a cota reinicia no dia do mês em que o cliente assinou, não no
  dia 1º. Mais justo para quem assina no meio do mês.

## D19 — Pagamentos: arquitetura plugável, Mercado Pago primeiro
- **Decisão:** interface comum `GatewayPagamento` (padrão adapter); cada provedor
  é uma implementação. Primeiro provedor integrado: Mercado Pago.
- **Motivo:** o estabelecimento escolhe o gateway pelas taxas; trocar/adicionar
  provedor não mexe no resto do sistema.

## D20 — Pagamentos: métodos online e presencial
- **Decisão:** online (Pix e cartão via gateway) e presencial (dinheiro,
  maquininha) com registro manual (sem gateway).

## D22 — Kanban com dois quadros
- **Decisão:** dois tipos de quadro: fila de atendimento do dia (cartão liga a
  agendamento) e CRM de leads/tarefas (responsável, prazo). Estrutura genérica:
  `kanban_quadros`, `kanban_colunas`, `kanban_cartoes`.

## D23 — WhatsApp via API oficial (Meta Cloud)
- **Decisão:** automações iniciais: lembrete de agendamento,
  confirmação/cancelamento e aniversário do cliente. Usa API oficial (número
  verificado e templates aprovados). Credenciais criptografadas.

## D21 — Segurança de pagamentos (formaliza D09)
- **Decisão:** nunca armazenar dados de cartão (só token do gateway);
  credenciais do gateway gravadas criptografadas (cast `encrypted`); confirmação
  de pagamento via webhook do gateway.

## D24 — Autenticação sob medida por guard
- **Decisão:** auth própria (sem starter kit) para os guards `web` (equipe),
  `cliente` (portal) e `admin` (central). Integra melhor com o tenancy por path.

## D25 — UI com Flux
- **Decisão:** usar o Flux (kit oficial do Livewire) como base de componentes,
  com Tailwind v4. Portal do cliente mobile-first; painel da equipe responsivo.

## D27 — Padrão de UI/UX elevado (obrigatório)
- **Decisão:** nada de tela simplista. Toda tela segue o [[Padrao de UI-UX (Design System)]]:
  componentização, modais/slide-overs, micro-interações, estados de
  loading/vazio/erro/sucesso, navegação tipo SPA, dark mode, responsivo e
  acessível — com efeitos tastefully aplicados (performance preservada).
- **Aplicação:** todo prompt de desenvolvimento referencia esse padrão como
  Definition of Done de UI.

## D26 — Convenções de implementação (1A)
- **Livewire 4 class-based** (não single-file): melhor para rotear
  (`Route::get(..., Componente::class)`) e testar; evita nomes de arquivo com emoji.
- **Testes em SQLite** (`:memory:` central e arquivo por tenant): isolamento real
  entre tenants sem tocar o MySQL nem usar comandos destrutivos.
- **Idioma pt-BR** via `laravel-lang/common` (Laravel 11+ não traz traduções).
- Equipe criada com senha inicial definida pelo gestor (convite por e-mail só
  quando o mail estiver configurado).

## D28 — Identidade visual por estabelecimento (tema via CSS variables)
- **Decisão:** cada tenant tem sua aparência (cores principal/secundária/fundo/
  texto, fonte e tamanho, logo, imagens de header/fundo, posição de menu, estilo
  dos ícones) guardada no banco do tenant (tabela/JSON de aparência em
  `configuracoes`). Aplicada em runtime como **CSS custom properties** no layout
  do portal/painel; nada de CSS por tenant compilado.
- **Motivo:** escalável (um só build), editável a quente, e a prévia em tempo real
  é só reescrever as variáveis. Sem imagens/segredos no Vault.

## D29 — Onboarding guiado de estabelecimento (Super-Admin) com prévia ao vivo
- **Decisão:** criar tenant deixa de ser "nome+slug" e passa a ser um **wizard**
  por etapas (identidade do negócio → responsável/dono → horário → segmento →
  aparência), com **prévia em tempo real** do portal do cliente ao lado,
  consumindo as variáveis da D28.

## D30 — Templates visuais (presets de aparência)
- **Decisão:** templates prontos (barbearia, salão feminino/masculino, neutro,
  premium, moderno, minimalista) são **presets** dos campos da D28 (cores, fonte,
  ícones, estrutura), aplicáveis no onboarding e **100% editáveis** depois.

## D31 — Painel do dono como dashboard completo
- **Decisão:** o painel do tenant evolui para um dashboard com indicadores e
  gráficos (agendamentos por período, faturamento estimado, clientes novos/
  recorrentes, serviços/profissionais/horários de maior movimento), além das
  áreas de gestão (agenda, clientes, profissionais, serviços, planos/clube,
  identidade visual) e um **kanban** de atendimentos/processos. Os números saem
  de dados reais; nada de tela/indicador falso.
- **Implementado (Etapa 4):** `App\Livewire\Painel\Dashboard` +
  `App\Services\Dashboard\Metricas`. **Faturamento é ESTIMADO** por snapshots de
  `agendamento_servico` de agendamentos `concluido` (não há módulo de Vendas ainda).
  Ver [[Dashboard do Dono]].

## D32 — Drivers em dev: cache/sessão/fila = `file`/`file`/`sync`
- **Decisão:** no ambiente de **desenvolvimento**, `CACHE_STORE=file`,
  `SESSION_DRIVER=file` e `QUEUE_CONNECTION=sync`.
- **Motivo:** a tenancy (stancl) **troca a conexão padrão** para o banco do tenant
  durante a requisição. Com esses drivers em `database`, o cache (inclusive o do
  spatie/permission), a sessão e a fila passam a procurar as tabelas
  `cache`/`sessions`/`jobs` **dentro do banco do tenant**, que não as possui →
  `SQLSTATE[42S02] ... tenant_*.cache doesn't exist`. Ver
  [[Bug - Drivers database dentro do tenant]].
- **Trade-off / futuro:** em produção (VPS) o ideal é voltar a **Redis** (cache/
  sessão/fila num store central, fora do banco do tenant). `file`/`sync` evita o
  problema em dev sem exigir serviço novo.
- **Atenção:** o `.env` **não é versionado** (segredos). Esta decisão precisa ser
  reaplicada em cada clone/ambiente novo.

## D33 — Segmento do estabelecimento na coluna JSON `data` do tenant central
- **Decisão:** o **segmento** do negócio (barbearia, salão…) é metadado central e
  fica na coluna JSON `data` da tabela `tenants` (banco **central**), não no banco
  do tenant nem como coluna própria.
- **Motivo:** é consultável no `/admin` sem precisar inicializar o tenant; o stancl
  já guarda atributos extras do tenant em `data` (colunas reais são só `id`, `nome`,
  `slug`, `ativo` — ver `Tenant::getCustomColumns()`).
- **Origem:** definido no onboarding (Etapa 3,
  `App\Livewire\Admin\OnboardingEstabelecimento`). Sugere o template de aparência,
  mas não trava a escolha do operador.

## D34 — Descrição e horário de funcionamento em `configuracoes` (tenant)
- **Decisão:** **descrição** (texto exibido no portal) e **horário de funcionamento**
  ficam na tabela `configuracoes` do **banco do tenant**, sob as chaves `descricao` e
  `horario_funcionamento`.
- **Motivo:** são conteúdo do estabelecimento, exibidos no portal do cliente; moram
  junto da aparência (chave `aparencia`, D28) no mesmo mecanismo chave/valor, sem
  nova migration. Ver [[Modelo de Dados - Núcleo de Agendamento]].

## D35 — Arquivos por tenant servidos por rota própria (path-based)
- **Decisão:** logo/cabeçalho/fundo enviados pelo tenant são gravados no disco
  `public` isolado por tenant (`storage/tenant{id}/app/public`) e servidos por uma
  **rota própria** `GET /{tenant}/arquivo/{path}` (`tenant.arquivo`,
  `App\Http\Controllers\TenantArquivoController`), com `InitializeTenancyByPath` e
  proteção anti path-traversal. URLs geradas por `Aparencia::urlArquivo($path)`.
- **Motivo:** o helper `tenant_asset()` e a rota de assets do stancl identificam o
  tenant por **domínio** — incompatível com este projeto, que é por **caminho**.
- **Nota:** ajusta o conselho antigo de "use `tenant_asset()`" — aqui o correto é
  `Aparencia::urlArquivo()`. Ver [[Identidade Visual do Estabelecimento (Tema)]].
