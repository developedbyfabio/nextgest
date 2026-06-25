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

## D36 — Tema: marca = acento; superfícies = modo claro/escuro/sistema (Flux)
> [!important] Substitui parte de D28 e da evolução visual (Etapas A/B)
> As Etapas A/B pintavam as **superfícies** (fundo/superfície/texto) com a cor da
> marca do tenant e forçavam `.dark` pela **luminância** da superfície escolhida.
> **Essa parte está revogada.**
- **Decisão (Etapa D):** o sistema tem **modo claro / escuro / sistema**, via o
  sistema de aparência do Flux (`@fluxAppearance` + classe `.dark`), no **painel** e
  no **portal do cliente**.
  - **Marca por tenant = ACENTO** (`--cor-principal` / `--color-accent`) **+ logo +
    tipografia**, constante nos dois modos (emitida inline via
    `Aparencia::cssVarsAcento()`).
  - **Superfícies** (fundo/superfície/texto/texto-suave/divisores) = **tokens de
    claro/escuro** definidos em `resources/css/app.css` (`:root` e `.dark`).
    `--cor-fundo/superficie/texto/texto-suave` deixaram de ser cor da marca e viraram
    esses tokens — então as views e classes `.ng-*` que já usavam essas vars passaram
    a seguir o modo **sem alteração**.
- **Motivo:** preferência do Fabio; acessibilidade (respeitar o SO do usuário) e
  legibilidade garantida nos dois modos. **Lição da Etapa A:** superfície e texto
  agora trocam **juntos** (ambos tokens), eliminando o bug de texto invisível por
  modo escuro meio-aplicado.
- **Persistência:** escolha do modo em `localStorage` (padrão do Flux). Cross-device
  por usuário fica como melhoria futura (coluna no `users`).
- **Controles:** seletor Claro/Escuro/Sistema no **header do portal**
  (`x-ng.seletor-tema`) e no **menu de perfil do painel**.
- **O que permanece de D28/D30:** a aparência por tenant e os **presets** continuam —
  expressando identidade pelo **acento** (e logo/tipografia), não mais pelas
  superfícies. `Aparencia::cssVars()` (superfícies completas) segue só na **prévia**
  do editor. Ver [[Identidade Visual do Estabelecimento (Tema)]].

## D37 — Recursos por tenant (feature flags "à la carte") no banco central
> Fase 0a. Fundação para ligar/desligar módulos (clube, whatsapp, gateway) por
> estabelecimento, controlado pelo super-admin. NÃO existe `.env` por tenant — esse é
> justamente o ponto. Credenciais criptografadas dos gateways ficam para a Fase 0b.
- **Onde mora a flag:** no **registro central** do estabelecimento (`tenants`), dentro
  do JSON **`data`** do stancl, sob a chave **`recursos`** (array de slugs ligados).
  **Sem migração** — `data` já está em uso (ex.: `segmento`), então é a opção de menor
  risco (zero alteração de schema, 100% aditiva) e evita coluna redundante.
- **Fonte única da lista válida:** enum `App\Enums\Recurso`
  (`clube`/`whatsapp`/`gateway`, com rótulos pt-BR). Nada de string solta espalhada.
- **Default = TUDO DESLIGADO** para tenants existentes e novos: chave ausente → `[]`.
  O comportamento atual não muda para ninguém.
- **Leitura normalizada (ponto único):** `Tenant::recursosAtivos()` limpa o que vier do
  `data` (descarta `null`/lixo) e **intersecta com os valores do enum** — slug
  desconhecido nunca liga recurso. `Tenant::temRecurso()` e o helper global
  `tenant_tem_recurso()` passam **todos** por aí. Sem contexto de tenant → `false`
  (não lança); slug fora do enum → `false` + aviso no log.
- **Escrita preserva o `data`:** persistir **só** o atributo virtual
  (`$tenant->recursos = [...]; $tenant->save()`), **nunca** reatribuir `$tenant->data`
  inteiro (apagaria o `segmento`). O `TenantDetalhe` recarrega o tenant completo e
  salva por ação explícita ("Salvar recursos"), guard `admin`.
- **Gating:** middleware de rota `recurso:{slug}` (alias em `bootstrap/app.php`,
  `App\Http\Middleware\VerificaRecurso`) → 404 quando o recurso está off; diretiva
  Blade `@recurso('whatsapp') ... @endrecurso` para blocos de UI. Ambos reusam o
  **mesmo** helper.
- **Convenção:** todo recurso futuro **nasce embrulhado na sua flag** (rota com o
  middleware + bloco Blade com a diretiva). Assim "recurso desligado nem aparece" passa
  a valer automaticamente quando cada módulo for construído. Nesta fase **não há UI/menu
  novo** (clube/whatsapp/gateway ainda não têm tela) — só o mecanismo + a convenção.
  Ver [[Recursos por Tenant (Feature Flags)]].

## D38 — Credenciais de integração por tenant: REUSAR os cofres existentes (Fase 0b)
> Cada salão guarda as credenciais das integrações dele **no banco do próprio tenant**,
> **cifradas**. Formaliza/estende D21. Esta fase é **só armazenamento + UI** — NÃO chama
> nenhuma API externa (sem testar conexão/webhook/SDK; isso é Fase 2/4).
- **Reuso, não nova tabela:** já existiam dois cofres de tenant prontos para cifragem —
  **`gateways_pagamento`** (model `App\Models\GatewayPagamento`, `credenciais`
  `encrypted:array`, D21; tem o `GatewayResolver` que a Fase 2 vai usar) e
  **`whatsapp_config`** (token cifrado). Criar uma tabela `integracoes` nova duplicaria
  o segredo e divergiria do resolver. Decisão: **mercadopago → `gateways_pagamento`;
  whatsapp → `whatsapp_config`** (criado o model `App\Models\WhatsappConfig`,
  `token` cast `encrypted`, `$hidden`). **Sem migração** (tabelas já existem). `clube`
  NÃO tem credencial (consome o gateway).
- **Fonte única:** enum `App\Enums\Integracao` (`mercadopago`/`whatsapp`) mapeia cada
  integração → recurso (flag 0a), → permissão (spatie) e → rota do editor.
- **Segredo write-only:** o campo de segredo **carrega vazio**; salvar vazio **mantém**,
  preenchido **substitui**. A tela mostra só status (configurado/não) + **máscara**
  (`••••1234`) — **nunca** o valor cheio, nunca renderizado de volta, nunca logado
  (`$hidden` + cast `encrypted`).
- **Gating (1º consumidor real da 0a):** tela **Integrações** no grupo "Gestão" do painel
  (`App\Livewire\Painel\Integracoes\*`). O índice lista só os cards disponíveis = recurso
  ligado (`tenant_tem_recurso`) **+** permissão (`gerenciar_pagamentos`/`gerenciar_whatsapp`,
  Dono+Gerente). Cada **editor** é rota gated por `recurso:{slug}` (0a) + `can:` → recurso
  off dá **404**. Nenhum recurso ligado → "Nenhuma integração disponível" (correto).
  Ver [[Integrações por Tenant (Credenciais)]].
  > [!note] Atualizado por D39: `gerenciar_pagamentos` passou a ser **só Dono**.

## D39 — RBAC adaptável: gate por permissão, multi-papel e "atende" como atributo
> Decisão do Fabio para equipes reais variadas (dono que também atende, dois donos, salão
> sem gerente). Princípios que passam a valer; a maior parte já estava de pé (auditoria).
- **Credenciais de pagamento = só Dono:** `gerenciar_pagamentos` removido do **Gerente**
  (fica só no Dono). `gerenciar_whatsapp` permanece em **Dono + Gerente**. Aplicado no
  `TenantDatabaseSeeder` (lista de exclusão do Gerente) e re-sincronizado nos tenants
  existentes com **`php artisan tenants:seed`** (idempotente: `syncPermissions`, sem wipe).
- **Gate por permissão, nunca por papel:** acesso sensível checa `can('permissão')`. A
  auditoria confirmou que **não havia** `hasRole`/`@role`/`role:` decidindo acesso — nada
  a trocar. Papel é só um pacote de permissões.
- **Multi-papel por membro:** a UI de equipe passou de **papel único** (`select`) para
  **vários papéis** (`flux:checkbox.group`, `syncRoles($papeis)`). Ex.: **Dono +
  Profissional** na mesma pessoa. Model spatie já suportava; o gap era só a UI.
- **"Atende/agendável" é atributo, não papel:** já era — coluna `users.e_profissional`
  (switch próprio na equipe); `MotorDisponibilidade` e as queries de agendáveis filtram por
  ela. Um Dono atende marcando o switch (Dono é superset). **`MotorDisponibilidade` NÃO foi
  tocado** (zero regressão de agenda/portal).
- **Dono é sempre superset** (todas as permissões) — garante cobertura em salão sem Gerente.
- **Travas de integridade (multi-tenant):** não criar membro **sem papel** (validação
  `required|array|min:1`); não remover o papel Dono nem inativar o **último Dono ativo** do
  estabelecimento (bloqueado no `Equipe\Index`). Ver [[Papéis e Permissões (RBAC)]].

## D40 — Estabelecimento inativo bloqueia acesso (404); cross-tenant falha limpo (302)
> Fecha os achados VULN-002 e VULN-001 da verificação de segurança. Ver
> [[Segurança e Isolamento]].
- **Tenant inativo → `abort(404)`** em todo o grupo de tenant (painel `web` e portal
  `cliente`), via `App\Http\Middleware\GarantirTenantAtivo` (logo após o init da tenancy).
  Inclui o **login** do tenant (não se loga em salão suspenso). Rotas **centrais/`admin`**
  não passam pelo grupo → **intactas**; a reativação é feita pelo super-admin (`/admin` →
  "Ativar"). Inativar é **reversível** (não apaga dado). Sem página "suspenso" agora (fica
  para o billing — troca de 1 linha).
- **Cross-tenant → 302 limpo:** `EscoparAutenticacaoPorTenant` roda **antes** do
  `Authenticate` (`prependToPriorityList`), descartando a sessão de outro tenant antes da
  autenticação — redirect limpo ao login, **nunca 500**. Já era sem vazamento; isto é
  higiene do modo de falha. Defesa em profundidade: guarda de usuário nulo no `Dashboard`.

## D41 — 2FA (TOTP) OPCIONAL e SÓ do Dono (app autenticador), local e sem custo
> Implementa o item 3 do plano de [[Segurança e Isolamento]] (defesa real da conta que mexe
> em dinheiro/credenciais). Ver [[2FA (TOTP) do Dono]].
- **Opcional + só Dono:** ninguém é forçado; outros papéis nem veem a opção. Gate por
  **permissão** (D39, nunca por papel): `gerenciar_2fa_proprio`, atribuída só ao Dono no
  `TenantDatabaseSeeder` (excluída do Gerente; re-sync via `tenants:seed`).
- **Biblioteca consagrada, nunca cripto à mão:** `pragmarx/google2fa` (TOTP RFC 6238) +
  `bacon/bacon-qr-code` (QR SVG inline, sem GD/Imagick). É o stack que o Fortify usa por
  baixo — **sem** Fortify, que sequestraria as rotas de auth e conflitaria com os 3 guards
  custom + tenancy por caminho (D24).
- **Setup é do Dono** (ele escaneia o QR); o super-admin NÃO configura por ele. **Componente
  ÚNICO** (`App\Livewire\Painel\Seguranca\DoisFatores`) reusado em DOIS lugares do Dono:
  perfil (modal no layout do painel) e **passo opcional/skippável do 1º login** (rota
  `painel.2fa.onboarding`, após a troca de senha). Onboarding do Dono ≠ wizard do super-admin
  (`OnboardingEstabelecimento`), onde o Dono nem está logado.
- **Nunca ativa sem um código de confirmação válido:** o segredo é gravado (cifrado) já no
  "Ativar", mas só vira ATIVO ao gravar `two_factor_confirmed_at` após o Dono digitar um
  código correto (prova que o app sincronizou). `temDoisFatores()` = segredo **e** confirmado.
- **Segredo + códigos de recuperação cifrados (D38):** colunas aditivas em `users` (tenant)
  `two_factor_secret` (encrypted), `two_factor_recovery_codes` (encrypted:array),
  `two_factor_confirmed_at`; os dois segredos no `$hidden` (nunca em log/HTML/snapshot — o
  segredo nem é propriedade pública do Livewire, só dado local da view durante o setup).
- **Desafio no login (painel/web):** senha OK + Dono com 2FA → estado "aguardando 2FA"
  (só `id`+`remember` em sessão `2fa.pendente`, login DESFEITO), tela de desafio aceita
  **código TOTP OU código de recuperação** (uso único, consumido) → só então `loginUsingId`
  + `regenerate`. **Throttle** próprio (~5 tentativas → bloqueio). **Caminho só-senha
  inalterado byte a byte** para quem não tem 2FA (admin/cliente/usuário sem 2FA): o trait
  `AutenticaPorGuard` segue com `attempt()` + `regenerate()`; o ramo 2FA só roda quando
  `precisaSegundoFator()` é verdadeiro (sobrescrito só no `PainelLogin`).
- **Ordem com `ForcarTrocaSenha`:** senha → 2FA → `loginUsingId` → (middleware) troca de
  senha se `deve_trocar_senha`. Na prática são disjuntos (quem tem 2FA já trocou a senha),
  mas a ordem é testada.
- **Reset pelo super-admin** (último recurso, perdeu app E códigos): `/admin` → detalhe →
  "Resetar 2FA" (modal de confirmação) limpa os campos do Dono no banco do tenant; **logado**
  (`Log::info`) sem dado sensível. **Desativar pelo Dono** (perfil) exige reconfirmar a senha.
- **Impersonação de suporte e portal/`admin` não mudam** (impersonate entra por
  `loginUsingId`, fora do componente de login → não passa pelo desafio, por design).

## D42 — Clube de Assinatura (Fase A): fundação pronta p/ gateway, cobrança atrás de costura
> Constrói modelos+aba+indicadores+relatórios do Clube, gated pela flag `clube` (D37). **Não
> cobra de verdade** (recorrência real = MP Preapproval+webhook = Fase 2/3, com VPS). Ver
> [[Clube de Assinatura (Fase A)]] e [[Modelo de Dados - Clube de Assinatura]].
- **Schema rico (D15–D18) já existia** (migração `190003`: planos/benefícios/descontos/assinaturas/
  usos + colunas em `venda_itens`). A Fase A só **adiciona** `eventos_assinatura_clube` (auditoria/
  churn) + índice `assinaturas_clube.status`. Status real: enum `ativa|suspensa|cancelada|inadimplente`
  (não há `pendente`).
- **Costura do gateway recorrente:** interface `App\Services\Clube\GatewayRecorrente` +
  `GatewayRecorrenteManual` (não cobra, sem webhook; status manual). O MP Preapproval entra como
  OUTRA implementação no futuro, sem mudar a aba. `gateway_assinatura_id`/`proxima_cobranca` prontos.
- **Eventos = fonte do churn/evolução:** toda mudança de status (via `App\Services\Clube\Assinaturas`)
  grava um evento. Indicadores (`IndicadoresClube`) são **set-based** (ativos/inadimplentes por status;
  novos/cancelados/evolução por eventos), com **teste de contagem constante**.
- **Benefício v1 = desconto %** (`plano_descontos` percentual/todos), aplicado na comanda do
  assinante **ativo** reusando `Comanda::definirDesconto` (núcleo intocado). Cota/inclusos
  (`plano_beneficios`/`usos_clube`) = schema pronto, aplicação futura.
- **Gate:** `recurso:clube` (off → menu some + rota 404) + permissão **reusada `gerenciar_clube`**
  (Dono+Gerente, sem novo seeder; nunca `hasRole`). Aba com Visão/Planos/Assinantes/Relatórios+CSV.
- **Lição (auditoria):** as tabelas do clube já existiam de `190003` — a T0 mirou models (não havia)
  e perdeu isso. Guards `hasTable` na migração nova + reconciliação evitaram schema divergente.
  Confirma a lição "auditar migrations, não só models, antes de criar tabela".

## D43 — Financeiro v1: números do negócio (sem despesas), prontos pro contador
> Item-pai "Financeiro" com faturamento/recebimentos/lucro BRUTO por período + export CSV.
> Leitura/agregação (sem migração). Ver [[Financeiro (v1)]]. v2 = despesas → lucro líquido.
- **Responsabilidade explícita (decisão do Fabio):** mostra os números organizados/exportáveis;
  **NÃO** calcula imposto/DAS/regime, **NÃO** substitui contador. Banner fixo **na tela e no
  export** ("não é cálculo de impostos"). Nenhuma afirmação de conformidade fiscal.
- **Fonte única:** `App\Services\Financeiro\ResumoFinanceiro` reusa o critério de receita do
  `Metricas` (paga + `data`) → o Financeiro **bate** com o dashboard (garantido por teste). Filtro
  extra por profissional/unidade/forma. Tudo set-based, contagem de query constante.
- **Lucro BRUTO** = receita − comissões − **CPV**; fórmula visível na tela. CPV = Σ
  (`produtos.preco_custo` atual × qtd) dos itens-produto — **sem snapshot histórico** (ressalva na
  tela; `venda_itens` não guarda custo). **Lucro líquido NÃO é prometido** (despesas = v2).
- **Recebido = `vendas.data` + `status=paga`** (regime alinhado ao dashboard, não `pago_em`).
  Recebimentos por forma = `pagamentos` aprovados dessas pagas (somam o faturamento).
- **Export CSV** agregado (cabeçalho tenant+período+aviso), **sem PII**.
- **Gate `ver_financeiro`** (só Dono, D40), camada dupla (rota `can:` + mount), nunca `hasRole`.
  Grupo "Financeiro" no menu só com a permissão. Sem migração (leitura).

## D44 — Clube: benefício de COBERTURA (100%) + família/beneficiários (substitui % desconto)
> Migra o benefício do Clube de "% desconto" (Fase A, depreciado) para "cobertura de serviços".
> Regra definitiva em [[Regra de Negócio — Clube de Assinatura]]. Adapta a Fase A (não reconstrói).
> **NÃO toca a agenda** (agendar para beneficiário = próximo prompt).
- **Plano (cobertura):** colunas aditivas em `planos_clube` — `ilimitado`, `limite_usos`, `periodo`,
  `dias_semana` (json 0=dom..6=sáb; null=todos), `capacidade`. **Serviços cobertos reusam a pivô
  `plano_beneficios`** (plano_id+servico_id). Limite/dias/capacidade são **do plano** (compartilhados
  pela assinatura — a família divide o teto). `plano_descontos` (% ) **depreciado**, não dropado.
- **Beneficiários:** nova `beneficiarios_assinatura` — `cliente_id` (com conta) **ou** `nome` (sem
  conta), `titular` bool. O titular vira beneficiário na criação. Trava `≤ capacidade`
  (`App\Services\Clube\Assinaturas::adicionarBeneficiario`). 1 titular ↔ 1 plano ativo (já era).
- **Cobertura na comanda** (`BeneficioClube::aplicarCobertura`): assinante **ativo** (titular OU
  beneficiário com conta) → zera 100% os itens de **serviço coberto**, no **dia permitido**, dentro
  do **teto** (saldo por assinatura/mês em `usos_clube`); marca `coberto_por_assinatura`+`assinatura_id`
  e registra `uso_clube`. Resto (produto/fora do plano/dia errado/além do teto) cobrado no balcão.
  **Reusa `Comanda::recalcular`** (zera `subtotal` do item) — núcleo intocado; substitui o botão de %
  no `Vendas\Detalhe`.
- **Consumo compartilhado:** contado por **assinatura** no mês (não por beneficiário) — a família
  divide o teto. Ilimitado nunca bloqueia.
- **Gate `gerenciar_clube`** (Dono+Gerente) reusado; flag `recurso:clube`. Indicadores set-based
  seguem **constantes** (não regrediu). Migração só aditiva; sem destrutivo.
- **Cobrança recorrente** continua **costura manual** (`GatewayRecorrente`) — sem gateway/webhook.
- **Hardening (pós-D44, 3 correções isoladas):** plano exige **1+ serviço** (`required|min:1`); modo
  **teto exige `limite_usos > 0`** (fim do "ilimitado silencioso"); **cancelar comanda / remover item
  coberto DEVOLVE a cota** (estorna `uso_clube` em `Comanda`, idempotente, sem tocar estoque/
  pagamento/comissão). Penalidade de **no-show < 1h** (que mantém o uso) fica para o passo da agenda.
  Detalhe em [[Regra de Negócio — Clube de Assinatura]].

---

## D45 — Shell do painel: sidebar colapsável (reuso do Flux), cabeçalho/rodapé fixos, radius só à direita
> Só **layout/visual** do painel (`resources/views/components/layouts/painel.blade.php`). NÃO toca
> rotas, gates, lógica de negócio. Reaproveita o `flux:sidebar` nativo — não reinventa em Alpine.
- **Colapsável reusando o Flux:** trocado `stashable` → **`collapsible`**. Um único
  `flux:sidebar.toggle` (o hambúrguer) resolve os dois modos: o evento `flux-sidebar-toggle` recolhe
  para faixa de **ícones** (`w-14`) no **desktop** e abre/fecha o **drawer** sobreposto (com backdrop)
  no **mobile**, decidindo pelo viewport. O conteúdo ao lado **alarga sozinho** (o grid do Flux usa
  coluna `min-content`). Grupos `navlist.group` recolhidos viram menu suspenso no hover (nativo).
- **Hambúrguer na linha do nome:** dentro de `flux:sidebar.header`, à direita da marca, **na cor de
  acento** (`style="color: var(--cor-principal)"` — a mesma var de D36, sem cor nova). O topbar mobile
  (`flux:header lg:hidden`) abre o mesmo drawer, também no acento. `aria-label` traduzido (`Toggle
  sidebar` em `lang/pt_BR.json`).
- **Cabeçalho/rodapé fixos, scroll só nos itens:** a sidebar é flex-col de altura cheia
  (`lg:sticky lg:top-0 lg:h-dvh`, `overflow-hidden`); header e rodapé são `shrink-0`; **só a
  `flux:navlist`** rola (`flex-1 min-h-0 overflow-y-auto`). O `min-h-0` é o pulo do gato — sem ele o
  overflow vazaria pro container (rolagem dupla). Rodapé usa `flux:sidebar.profile` (recolhe p/ avatar).
- **Radius só à direita:** removido o `ng-surface` (arredondava os 4 cantos = "cartão solto"); agora
  `rounded-e-2xl` + `border-e` + a mesma cor de fundo/borda. O lado esquerdo encosta na borda da tela.
- **Persistência:** **sem `localStorage`** (regra do projeto). O Flux só grava o recolhido em
  localStorage com a prop `persist` (que o componente nem expõe aqui), então o estado fica **por
  sessão de navegação**. Não inventamos cookie novo. Verificado por Playwright (desktop recolhe/alarga
  e gruda no scroll; mobile vira drawer; gates Clube/Financeiro intactos) e suíte 416/416 verde.
- **Isolamento:** portal (`portal.blade.php`) e a **prévia da Aparência** (`x-ng.previa-portal`) não
  usam `flux:sidebar` → inalterados.

---

## D46 — Polimento do menu do painel: "Início" no padrão, dropdown com nome, e foto de perfil
> Continuação do D45 (mesmo arquivo `painel.blade.php` + 1 componente Livewire novo). Layout +
> upload **cercado**; NÃO toca rotas/gates/negócio. Suíte 433/433 verde.
- **"Início" usa `flux:sidebar.item` (não `flux:navlist.item`).** Causa real do "espremido" (lida na
  fonte dos componentes Flux, não suposição): só o `flux:sidebar.item` traz as classes do estado
  **recolhido** (`in-data-flux-sidebar-collapsed-desktop:w-10` + `justify-center` + oculta-texto +
  tooltip). O `navlist.item` não tem nenhuma → na faixa de ícones (`w-14/px-2`) ficava `w-full px-3`
  com texto visível. Trocado, o Início acompanha os **cabeçalhos de grupo** (os outros itens de topo):
  expandido idêntico (h-8, px-3, gap-3, ícone+texto, estado ativo outline), recolhido vira ícone
  centralizado, mobile h-10. Os **itens dentro dos grupos** seguem `navlist.item` (o grupo troca o
  container inteiro por ícone+dropdown ao recolher — não tinham o bug). Ver gotcha em [[Gotchas e Aprendizados do Projeto]].
- **Dropdown do perfil com cabeçalho (Item 4):** o **nome** vira cabeçalho em destaque e o **e-mail**
  fica menor/secundário abaixo (antes o e-mail era um `flux:menu.item` inerte, que parecia clicável e
  não fazia nada). Cores zinc do Flux (superfície do popover, claro/escuro).
- **Foto de perfil (Item 5) — REUSA o caminho da Aparência (D36), sem caminho paralelo:** coluna
  **aditiva** `users.foto_perfil` (string, nullable) + `$fillable`. Upload self-service (todos os
  papéis) pelo dropdown → modal com **recorte QUADRADO no cliente** (Cropper.js **empacotado via
  Vite**, sem CDN, sem localStorage): canvas 512×512 → blob PNG → `$wire.upload('foto')` →
  `salvar()`. Servidor: `WithFileUploads` + `store('aparencia','public')` no disco do tenant
  (isolado), servido por `TenantArquivoController` via `Aparencia::urlArquivo`; validação
  `['nullable','image','mimes:png,jpg,jpeg,webp','max:5120']` (**sem SVG**). **Não reintroduz** o
  gotcha do 500 (disco temp central + tenancy antes do throttle, intactos). Avatar do rodapé
  (`flux:avatar` via `flux:sidebar.profile`) usa `:avatar` com **fallback nas iniciais** quando nulo;
  "Remover foto" volta às iniciais. Após salvar/remover, **reload** (`navigate:false`) para o avatar
  do layout refletir (mesmo padrão da Aparência). `[x-cloak]` no `app.css` evita flash do palco.
- **Gates intactos (guard-rail testado):** Recepção não vê Financeiro/Comissões; Dono vê. O ajuste do
  menu não afrouxou `@can`/`@recurso` (Clube `recurso:clube`, Financeiro `ver_financeiro`).

---

## D47 — Menu fechado no load + login do portal responsivo por CONTAINER QUERY (prévia = login real)
> Três correções (commits por item). NÃO toca auth do guard `cliente`, rotas, agenda, comanda, Clube,
> Financeiro nem 2FA. Sem `localStorage`/`sessionStorage`. Suíte 433/433 verde (sequencial).
- **Menu — grupos fechados ao carregar (T1):** os três `flux:sidebar.group` (Operação/Gestão/
  Financeiro) passaram de `:expanded="true"` para **`:expanded="false"`**. Estado **fixo** (sempre
  fechado a cada load, sem persistir — coerente com D45, que não usa `localStorage`); expandem só ao
  clique e voltam a fechar ao navegar (`wire:navigate` re-renderiza o layout). "Início" e o flyout do
  modo recolhido **inalterados**. Verificado por Playwright (dashboard com os 3 grupos colapsados).
- **Login do portal responsivo por CSS, não por aparelho (T2+3):** o login real do guard `cliente`
  (`ClienteLogin`/`ClienteRegistrar`) **já** era responsivo por `lg:` (não havia user-agent sniffing),
  **mas não mostrava o fundo (D36)** — o painel de marca pintava `--cor-principal`, nunca a
  `fundo_imagem` (só o `portal.blade.php` aplicava o fundo). E a **prévia** da Aparência tinha uma
  **maquete divergente** do login (mobile-portal estática), não o componente real.
- **Fonte de verdade única `x-portal.auth`:** novo componente que monta o shell de autenticação do
  portal — 2 colunas (painel de marca com `fundo_imagem` **cover** + tinta da marca / fallback
  `--cor-principal`; coluna do formulário) no largo, **coluna única** no estreito (formulário sobre o
  fundo translúcido `ng-com-fundo`). O **corpo** do formulário entra por **slot**: campos `flux:input`
  reais no login/registro, maquete estática (`--cor-*`) na prévia — mesmo padrão do `x-portal.tela-inicio`.
- **Responsividade por CONTAINER QUERY (`@container` + `@3xl:`), não viewport (`lg:`):** é o pulo do
  gato que deixa **a prévia simular o breakpoint mudando só a largura**. No login real o container é a
  viewport (reflui como antes); na prévia o container é a moldura → o toggle **celular/desktop** muda a
  largura e o **mesmo** componente reflui (1↔2 colunas) sem detecção de aparelho. Altura cheia via
  `flex flex-col` no root + `grid flex-1 grid-rows-1` (a linha 1fr preenche → o fundo do root não vaza).
  Tailwind v4 tem container queries nativas (já usadas no projeto).
- **Login real fora do `auth.blade.php` genérico:** `ClienteLogin`/`ClienteRegistrar` passam ao layout
  fino **`components.layouts.portal-auth`** (corpo = tela cheia; o 2-col é do `x-portal.auth`).
  Admin/Painel/Troca-de-senha/2FA seguem no `auth.blade.php` (intactos). **Auth logic e rotas
  inalteradas.**
- **Prévia fiel:** a tela "Login" do carrossel renderiza `x-portal.auth` (mesmo componente; slot
  estático), com toggle **celular/desktop** além do claro/escuro. No desktop a moldura larga é
  **escalada para caber na coluna** (transform: scale; a **largura de layout** e o breakpoint seguem
  reais), e as demais telas do portal **centralizam em `max-w-md`** com o fundo nas laterais — fiel ao
  portal real (mobile-first mesmo no desktop). Reutilizada também no onboarding.
- **Verificado por Playwright (Chromium):** login real desktop (2-col + fundo) e mobile (1-col, **sem
  scroll horizontal**), dark mode, e a prévia (login mobile/desktop + Início centralizado) — todos
  batendo com o real. `laravel.log` vazio.

## D48 — Deploy de produção (Fase 1): nextgest.com.br no ar, stack completa, base limpa

- **Servidor/stack:** KVM Ubuntu 24.04, `/srv/www/nextgest` (clone do `main`). Nginx 1.24 + **PHP
  8.5-FPM** (PPA `ondrej/php` — repo Ubuntu só tem 8.3) + MySQL 8 + Redis 7 + Composer + Node 22.
  Fila por **supervisor** (2× `queue:work redis`), **cron** do `schedule:run`, caches de produção
  (`config/route/view/event`). Detalhe em [[Deploy de Produção (Fase 1) — D48]].
- **Banco DB-per-tenant:** central `nextgest_central` + usuário dedicado `nextgest` (grants em
  `nextgest_central.*` e ``tenant\_%``). Tuning p/ 8 GB (`buffer_pool=1536M`, `max_connections=100`,
  `flush_log_at_trx_commit=1`, `open_files_limit=65535` via override systemd) + swap 2 GB.
  Redis `maxmemory 512mb` / `volatile-lru` (protege jobs sem TTL).
- **SSL wildcard por DNS-01 (Cloudflare):** cert `nextgest.com.br` + `*.nextgest.com.br` (cobre todos
  os tenants), renovação automática + hook de reload. Nginx 443 + redirect 80→443 + **HSTS**.
  HTTPS atrás do proxy via `fastcgi_param HTTPS on` + **TrustProxies escopado às faixas do Cloudflare**
  (`bootstrap/app.php`) + real-IP (`CF-Connecting-IP`). Cloudflare **Proxied + Full (strict)**.
- **Produção LIMPA:** só migrations centrais + **super-admin** único (`admins`, login
  `fabio9384@gmail.com`, trocar senha no 1º acesso). **Zero tenants demo / zero seeders de volume.**
  Único tenant é o **`teste`** (validação, mantido). Tenancy **path-based** (`/{slug}`), pipeline
  síncrono CreateDatabase→Migrate→Seed.
- **Correções necessárias ao deploy (commitadas):** criada a tabela **`failed_jobs`** (faltava no
  repo; driver `database-uuids` quebrava ao registrar falha) e TrustProxies no `bootstrap/app.php`.
- **Segredos:** só no servidor (`.env` 640, cofres `/root/*.cred` 600). Nada documentado.
- **Fora do escopo (próximas fases):** e-mail transacional real (hoje `log`), Gateway/webhook (Fase 2),
  clube recorrente (Fase 3), WhatsApp (Fase 4), hardening de firewall (restringir origem às faixas CF).

---

## D49 — Atribuição serviço/profissional ↔ unidade: UI sempre utilizável + 1 unidade por profissional
> Corrige o sintoma "Nenhum serviço disponível nesta unidade" (cliente). Causa era **UI**, não
> modelo: os pivôs (`servico_unidade`, `user_unidade`, `servico_user`, `horarios_trabalho`) já
> existiam e a query do portal (`Portal\Agendar`) estava certa — voltava vazia porque o pivô estava
> vazio. **Sem migration, sem backfill** (órfãos = tarefa à parte), **`MotorDisponibilidade` intocado**.
> Suíte 438/438 verde.
- **Serviço = MULTI-unidade (Serviços):** removido o `@if count()>1` que escondia o seletor com 1
  filial (dependia de auto-select frágil). O checkbox.group "Oferecido nas unidades" aparece **sempre**;
  salvar **exige ≥1 unidade** (`required|array|min:1`) → não nasce serviço órfão/invisível. `editar()`
  já re-seleciona; `novo()` auto-seleciona a filial única.
- **Profissional = UMA unidade (Equipe):** seletor virou **`flux:select` único** (não checkboxes);
  `user_unidade` recebe **sync de 1 elemento** (substitui a anterior). Unidade **obrigatória** quando
  `e_profissional`; opcional para os demais papéis. `editar()` carrega a unidade atual; `novo()`
  auto-seleciona a única.
- **Troca de filial move os horários (CRÍTICO):** `horarios_trabalho` é **por unidade**. Ao mudar a
  unidade do profissional, as janelas DELE são movidas para a nova filial (`horariosTrabalho()->update(['unidade_id'=>nova])`),
  **escopado ao próprio usuário, idempotente, só quando a unidade muda** — senão ele ficaria sem
  disponibilidade. Não é backfill em massa; não toca o motor. O "não em dois lugares ao mesmo tempo"
  segue garantido por `horarios_trabalho` + conflito de agendamento (motor).
- **Gestão pelo lado da Unidade (a descoberta que faltava):** modal **"Gerir"** na tela de Unidades
  edita os **serviços oferecidos ali** (sync `servico_unidade`, multi — espelha o pivô de Serviços) e
  **lista os profissionais** da unidade com indicadores (nº de serviços; **com/sem horários** na
  unidade) + link para a Equipe. **Atribuição/troca de profissional fica só na Equipe** (evita
  duplicar a regra de "1 unidade + move de horários"). Gate `gerir_unidades` (mesmo da tela; Dono/Gerente).
- **Pendência conhecida:** os **2 serviços órfãos** do `barbeariateste` (`Barba Cob`, `Corte Cob`)
  seguem sem unidade até o backfill (prompt à parte) — agora visíveis/corrigíveis no modal "Gerir".

---

## D50 — Backfill de órfãos de unidade: comando `nextgest:reconciliar-unidades` (idempotente, dry-run)
> Continuação do D49: a UI nova faz cadastros nascerem vinculados; este comando reconcilia o
> **legado** (órfãos antigos). **Não-destrutivo** (só liga, nunca desliga), **idempotente**, **não
> inventa horário**, **não toca o motor**, **sem migration**. Suíte 443/443 verde.
- **Comando:** `php artisan nextgest:reconciliar-unidades` (`app/Console/Commands/ReconciliarUnidades.php`).
  **Dry-run é o padrão** (só relata, nada escreve); escreve **apenas com `--apply`** (opt-in
  explícito = segurança). Itera todos os tenants via `Tenant::all()` + `$tenant->run()`.
- **Regra (decisão do Fabio):**
  - **Tenant com 1 unidade →** liga os órfãos àquela única unidade: serviço ativo sem
    `servico_unidade` e profissional ativo sem `user_unidade` → `syncWithoutDetaching([unidade])`
    (idempotente, só adiciona). Sem ambiguidade.
  - **Tenant com 2+ unidades →** **NÃO adivinha**: só lista os órfãos (nominal) para decisão manual
    no modal **"Gerir"** (serviços) / na **Equipe** (profissionais).
  - **Horários (`horarios_trabalho`) →** **nunca inventa**: só **sinaliza** o profissional ativo que
    tem unidade mas está sem janela nela (a jornada não dá para deduzir). O Fabio cadastra na tela Horários.
- **Aplicado no DEV:** `barbeariateste` (1 unidade) tinha **4 serviços órfãos** (`Barba Cob`,
  `Coloração`, `Corte + Barba`, `Corte Cob`) → ligados à Matriz Centro (`servico_unidade` 3→7,
  0 órfãos); 2ª execução = **sem mudança** (idempotente); `Dona que Atende` segue **sem horário**
  (apenas sinalizada). `salaoteste`/`volumeteste` sem órfãos.
- **PRODUÇÃO (quando o Fabio publicar):** pelo [[Roteiro de Deploy Seguro]] — (1) **backup** central+tenants;
  (2) **`--dry-run`** e conferir o relatório (os órfãos reais diferem do dev); (3) **`--apply`**;
  (4) validar no portal que os serviços antes órfãos passaram a aparecer. **Não rodado em produção
  por agente.**

---

## D51 — Avaliações de atendimento (Prompt 1: COLETA no portal)
> Após um atendimento **concluído**, o cliente avalia (1–5 estrelas + comentário opcional). Coleta
> pelo portal; a visualização no painel (RBAC, filtros) é o **Prompt 2**. Migration de tenant
> **aditiva**; motor/agenda **intocados**. Suíte 450/450.
- **Âncora = `Agendamento` concluído** (`status = 'concluido'`). Não inventou conceito novo: o
  atendimento já é o agendamento. Tabela **`avaliacoes`** (tenant): `agendamento_id` **UNIQUE**
  (1 atendimento = 1 avaliação) + `cliente_id` + `profissional_id` + `unidade_id` (denormalizados do
  agendamento p/ os filtros do Prompt 2) + `nota` (1–5) + `comentario` (nullable). O(s) **serviço(s)**
  são **derivados** do agendamento (`itens.servico`) — atendimento pode ter vários serviços, então
  não há `servico_id` único. Model `Avaliacao` (`$table='avaliacoes'`, plural irregular) +
  `Agendamento::avaliacao()` hasOne.
- **Elegibilidade / popup uma vez:** "avaliável" = concluído **sem** linha em `avaliacoes`. Coluna
  **`agendamentos.avaliacao_popup_exibido_em`** (timestamp nullable) marca que o popup já apareceu.
  No load do portal (`Portal\Home::mount`), se há um avaliável com popup ainda não exibido → marca a
  coluna e abre o modal (**uma vez**). **Ignorar** só fecha (não cria avaliação); o atendimento
  segue avaliável pelo **histórico**.
- **Refinamento (popup SÓ do mais recente — anti-bombardeio):** `mount` pega **o atendimento concluído
  MAIS RECENTE** (`orderByDesc('data_hora_inicio')->first()`) e SÓ mostra o popup se ELE estiver não
  avaliado e sem popup exibido. Se o mais recente já foi avaliado/ignorado → **nenhum popup** (não
  "promove" os anteriores). Antes iterava pelos sem-popup e mostrava um a cada acesso (cansativo).
  Os antigos pendentes ficam avaliáveis **apenas pelo histórico**; um atendimento concluído **mais
  novo** vira o do popup (uma vez). Teste-chave: recente ignorado/avaliado + antigos pendentes →
  `mostrarAvaliacao=false`.
- **Mesmo modal** para popup e histórico (sem duplicar): `abrirAvaliacao(id)` / `salvarAvaliacao` /
  `ignorarAvaliacao` no `Portal\Home`. Estrelas clicáveis (Alpine hover/seleção, `role=radio`,
  acessível) + comentário. Histórico: avaliado → `x-portal.estrelas` (read-only) + comentário; não
  avaliado → botão "Avaliar". Componente `x-portal.estrelas` reutilizável (Prompt 2).
- **Segurança:** cliente só avalia o **próprio** atendimento (escopo `cliente_id` + `findOrFail`),
  **concluído** e **não avaliado** (`avaliacaoAvaliavel()` aborta 404 caso contrário). 1-por-atendimento
  pela `unique`. Verificado por Playwright (popup → avaliar → 2º popup → ignorar → não reaparece →
  avaliar pelo histórico) e 7 testes (criar; nota obrigatória; ignorar não cria; histórico; unique;
  não avalia alheio; não avalia não-concluído). `SemearDemo` cria avaliações de exemplo (idempotente).
- **Prompt 2 (FEITO) — visualização no painel:** aba **"Últimos serviços"** (Operação,
  `Painel\Avaliacoes\Index`, rota `painel.avaliacoes`) lista os atendimentos **concluídos** + a
  avaliação de cada um (estrelas + comentário) ou "sem avaliação".
  - **RBAC por permissão (nunca papel):** `ver_avaliacoes` (Dono/Gerente) vê **tudo** + **nome do
    cliente** + filtro por cliente; `ver_avaliacoes_proprias` (Profissional) vê **só os dele** e
    **ANÔNIMO** — a query **nem carrega o cliente** (`with('cliente')` só quando `podeVerTudo`), então
    o nome não sai do banco. Sem nenhuma das duas permissões → **403** (nem aparece no menu). Escopo
    no **servidor** (`where profissional_id` p/ o profissional). Permissões adicionadas ao seeder e
    aplicadas por re-seed (`tenants:seed`).
  - **Resumo (termômetro)** no topo, mesmo escopo: média de estrelas, nº de avaliações, atendimentos
    concluídos e **taxa de avaliação**. Reflete os filtros de **escopo** (período/cliente/unidade);
    os filtros **nota** e **com/sem comentário** afetam **só a lista** (para a média/taxa seguirem
    significativas) — decisão reportada.
  - **Filtros (no servidor):** cliente (**só Dono**), período (hoje/semana/mês), estrelas (1–5),
    com/sem comentário, unidade (multiunidade). Usam os índices denormalizados da `avaliacoes`.
  - Verificado por Playwright (Dono: coluna+filtro Cliente, 200 concluídos; Profissional: sem
    coluna/filtro Cliente, 76 só dele, nenhum nome vaza) e 8 testes (RBAC, anonimato real pela rota,
    403, filtros). Reusa `x-portal.estrelas`. Coleta (popup/histórico) intacta.
  - **Feature completa no dev.** Publicar em produção pelo [[Roteiro de Deploy Seguro]] — o deploy
    inclui a **migration de tenant** do Prompt 1 (`tenants:migrate`) **e** o re-seed das permissões
    (`tenants:seed`) para a aba aparecer aos papéis certos. **Não rodado em produção.**

---

## D52 — Menu: grupo da rota atual expandido + acordeão (só um aberto)
> Refinamento de UX do menu (continua a D45/D46/D47). NÃO reverte "grupos fechados no load" (D47) —
> só adiciona a exceção do grupo da página atual. Só UX; motor/negócio intocados. Suíte 462/462.
- **Grupo da rota atual começa EXPANDIDO** (resolve o "esquece no reload"): no `painel.blade.php`,
  `$grupoAtivo` é computado no servidor (`request()->routeIs(...)` com a lista de rotas de cada grupo)
  e passado como `:expanded="$grupoAtivo === 'operacao'"` etc. Como é server-side, vale após reload E
  após `wire:navigate` (o layout re-renderiza com o grupo certo aberto). Início / páginas sem grupo →
  `$grupoAtivo = null` → todos fechados.
- **Acordeão (só um aberto):** o `flux:sidebar.group` (web component `ui-disclosure`) NÃO tem acordeão
  nativo. Um `<script>` no layout, ao abrir um grupo MANUALMENTE, fecha os demais **clicando no botão
  deles** (usa o próprio toggle do Flux — não mexe no estado interno). Detecção de "aberto" robusta:
  **visibilidade do conteúdo** (`getComputedStyle(div).display !== 'none'`), independente de como o
  `ui-disclosure` representa o estado pós-hidratação (o atributo `open` server-side não é confiável no
  cliente). Delegação no `document` (sobrevive ao `wire:navigate`) com guarda contra registro duplicado.
- **Não trava:** o usuário pode fechar o grupo ativo manualmente. **Highlight do item ativo** inalterado.
- **Gotcha (Blade):** o cálculo de `$grupoAtivo` entrou num bloco `@php ... @endphp`. Ao colocá-lo logo
  após os `@php(...)` inline do topo, o regex de bloco do Blade **engoliu** os inline (de `@php($tenantId...)`
  até o `@endphp`), deixando `$aparencia` etc. indefinidos (500). **Fix:** unir tudo num ÚNICO bloco
  `@php ... @endphp` (sem misturar inline + bloco no topo).
- Verificado por Playwright (Operação abre em "Últimos serviços" e **persiste no reload**; navegar p/
  Gestão fecha Operação; abrir manualmente fecha o anterior; fechar o ativo é permitido) + 4 testes
  (Início 0 grupos abertos; Operação/Gestão/Financeiro 1 cada). Sidebar colapsável/flyout/drawer/perfil intactos.

---

## D53 — TenantDatabaseSeeder aditivo/idempotente (seguro para re-seed em tenant real)
> Encerra a pendência aberta no deploy de avaliações (D51): o re-seed (`tenants:seed`) era
> **inseguro** em tenant customizado. Agora **garante o piso sem impor o teto**. Sem migration;
> motor/avaliações intocados. Suíte 472/472.
- **Problema:** o seeder usava **`syncPermissions`** por papel (substitui o conjunto inteiro →
  **apagaria** permissões que o Dono adicionou) e **`updateOrInsert`** nas 3 configs sensíveis
  (`confirmacao_automatica`, `intervalo_slots_minutos`, `cancelamento_antecedencia_horas` →
  **resetaria** valores ajustados pelo Dono). Inofensivo hoje (produção só tem o tenant `teste`,
  sem customização), mas perigoso num tenant real.
- **Permissões → concessão ADITIVA:** `Permission::findOrCreate` para cada base + `$role->givePermissionTo(...)`
  (spatie = `syncWithoutDetaching`): **garante** as base, **nunca revoga** extras. Papéis seguem
  `Role::findOrCreate`. Avaliações já no base (Dono/Gerente = `ver_avaliacoes`; Profissional =
  `ver_avaliacoes_proprias`).
- **Configs → criar só se faltar:** `DB::table('configuracoes')->insertOrIgnore([...])` (pela `chave`
  única). Tenant novo recebe os defaults; tenant existente **mantém** o valor do Dono (nunca sobrescreve).
- **Kanban** (`semearKanban`) já era idempotente (`firstOrCreate`) — inalterado.
- **Garantias (testadas):** provisionamento de tenant novo intacto (papéis + permissões base + configs);
  **idempotência total** (2ª execução não muda nada); **preserva customização** (permissão extra do
  Dono permanece; config alterada não reseta); **regarante** uma permissão base removida (piso).
  5 testes em `SeederAditivoTest`. `tenants:seed` rodado no dev (limpo, idempotente). **Sem deploy.**

---

## D54 — Identidade visual do painel super-admin alinhada à landing (Fase 0)
> Só apresentação (guard `admin`, `/admin/*`). NÃO toca banco/RBAC/modelo/rotas de negócio nem o
> painel do tenant/portal/motor. Reusa a fonte de verdade da landing. Suíte 472/472.
- **Layout `components/layouts/admin.blade.php`:** header **glassmorphism** igual à landing
  (`bg-white/80 backdrop-blur-md dark:bg-[#0B1120]/80`); `flux:brand "Nextgest Admin"` → **logo**
  (`asset('nextgest-logo.png')`) + wordmark "Nextgest" + **pill "ADMIN"** em degradê de marca; fundo
  `bg-white text-slate-900 dark:bg-[#0B1120] dark:text-slate-100`.
- **Dark/light:** **mesmo mecanismo da landing** — `@fluxAppearance` + `$flux.appearance` (persistido
  pelo Flux). Adicionado o **`x-landing.tema-toggle`** (sol/lua) no header, reusando o componente; o
  radio Claro/Escuro/Sistema do dropdown segue (mesmo estado).
- **Telas:** `livewire/admin/dashboard.blade.php` — card "Estabelecimentos" com ícone em degradê +
  bloco geométrico no canto e banner "Em construção" em degradê sutil (conteúdo/lógica intactos);
  `livewire/admin/tenants.blade.php` — botão **"Detalhes" → "Editar"** (ícone `pencil-square`, **mesma
  rota/destino** `admin.tenant.detalhe`); demais ações (Abrir/Criar dono/Inativar/Ativar) intactas.
- **Paleta = classes Tailwind da landing** (`from-violet-600 via-indigo-600 to-blue-600` + slate +
  `#0B1120`); não emite `--cor-*` (central, sem tema de tenant). Verificado por Playwright (login
  admin: logo ok; dashboard/estabelecimentos legíveis em claro e escuro; toggle persiste; "Editar"×3,
  "Detalhes"=0). Tenant/portal inalterados (200). **Sem deploy.**

---

## D55 — Plano nomeado dirige os recursos (catálogo + aplicação + troca + etapa no onboarding)
> Camada de NOME por cima das feature flags da Fase 0a (D37). Um "plano" liga um conjunto de
> `recursos` de uma vez. **Sem migração/tabela/seeder/permissão nova** — reusa o `data` central e o
> gating existente (`recurso:` + `@recurso`). Ver [[Planos (catálogo e aplicação)]] e
> [[Recursos por Tenant (Feature Flags)]].
- **Catálogo:** `config/planos.php` (fonte única). Mapa: **Básico=`[]`**,
  **Profissional=`['clube','gateway']`**, **Nextgest=`['clube','gateway','whatsapp']`**. `preco_mes`
  é **referência interna do admin** (a landing segue independente; unificação de preço é fase
  posterior — não é fonte única de preço ainda).
- **Persistência:** atributo virtual **`plano`** no `Tenant` (mora no JSON `data`, junto de
  `segmento`/`recursos`). `Tenant::planoAtual()` normaliza (null se não definido ou fora do catálogo).
- **Aplicação:** `Tenant::aplicarPlano($chave)` seta `plano` + redefine `recursos` para o padrão do
  plano **só via atributos virtuais** (regra de ouro do `data`: nunca reatribuir `$this->data` inteiro
  → `segmento` sobrevive). Chave fora do catálogo lança `InvalidArgumentException`.
- **Onboarding:** wizard passou a **6 etapas** — nova etapa **Plano** entre Aparência (4) e Revisão
  (6); seleção **obrigatória** (sem default silencioso). A Revisão mostra plano + recursos inclusos.
  Ao confirmar, chama `aplicarPlano()` no tenant recém-criado.
- **Troca de plano (Detalhe):** `TenantDetalhe::trocarPlano()` recarrega o tenant completo, reaplica
  os recursos e re-sincroniza os toggles. Os switches manuais viraram **"Ajuste fino de recursos"**
  (independentes), com aviso: **trocar o plano redefine os recursos**. Tenant **sem plano** (os atuais)
  → "Plano: não definido (recursos personalizados)"; nada é mutado em massa.
- **Rebaixar** (ex.: Nextgest→Básico) só **esconde o acesso** aos módulos retirados — os **dados** no
  tenant (ex.: clube) permanecem. Como o gating lê `recursos` ao vivo, a troca reflete no painel na
  hora (menu + rotas).
- **Testes:** `tests/Feature/Admin/PlanoTenantTest.php` (aplicarPlano + preserva segmento; chave
  inválida lança; planoAtual normaliza; rebaixar esconde; etapa Plano obrigatória; revisão mostra;
  troca re-sincroniza; sem-plano não muta; **gating real por HTTP**: Nextgest libera `/painel/clube`,
  Básico → 404). Suíte verde. **Dev apenas — sem deploy.** Em produção, o tenant real precisará ter o
  `plano` definido manualmente (com backup), fora desta fatia.

---

## D56 — Camada central `estabelecimentos` (1:1 com tenants) + validadores BR + onboarding ampliado
> Fase 3a (fundação + captura). Tabela CENTRAL aditiva (não é migration de tenant). O LOGIN do dono
> continua no tenant (`users`); o cadastro completo (admin/cobrança) nasce no central. A tela "Dados"
> (ler/editar + criar sob demanda p/ tenants antigos) é a 3b. Ver
> [[Cadastro Central do Estabelecimento]] e [[Mapeamento Central x Tenant (auditoria pré-planos)]].
- **Tabela `estabelecimentos`** (central, FK `tenant_id` string → `tenants.id`, **unique**; cascade
  on delete). Quase tudo nullable (o onboarding exige na captura; a 3b completa depois). Campos:
  estabelecimento (`nome_fantasia`, endereço `cep/logradouro/numero/complemento/bairro/cidade/uf`,
  `faturamento_mensal` decimal, `documento_tipo` cpf|cnpj, `documento`) + contato do dono
  (`dono_nome`, `dono_sobrenome`, `dono_email`, `dono_celular`, `dono_cpf`). Documentos/celular/CPF/CEP
  guardados **normalizados (só dígitos)** via `Estabelecimento::soDigitos()`.
- **Model `App\Models\Estabelecimento`** usa **`CentralConnection`** (stancl) — mora SEMPRE na central,
  mesmo se consultado dentro de um tenant. `Tenant::estabelecimento()` (hasOne) — pode ser null em
  tenants antigos.
- **Validadores in-house** (sem pacote novo — rede de build restrita): `App\Rules\Cpf`, `App\Rules\Cnpj`,
  `App\Rules\CelularBr` (conferem dígitos verificadores / DDD + 8–9 dígitos; aceitam máscara, normalizam
  para dígitos). Reusáveis na 3b.
- **Onboarding 6→7 etapas:** Identidade → Responsável → **Estabelecimento** → Funcionamento → Aparência
  → Plano → Revisão. Responsável ganhou **sobrenome, celular, CPF** (obrigatórios, validados). Nova
  etapa **Estabelecimento** (nome fantasia obrigatório, pré-preenchido pelo nome; endereço/faturamento/
  documento opcionais, documento validado se preenchido). Ao confirmar, grava o registro central ligado
  ao tenant (além de criar o Dono no tenant como antes, com `deve_trocar_senha`).
- **Criação rápida** (`Tenants::criar`) grava o central mínimo (`tenant_id` + `nome_fantasia`); o resto
  fica nulo (editável na 3b). `criarDono` faz **backfill** leve do contato (só campos vazios).
- **Observação (fora de escopo):** o e-mail de login vive em 2 lugares (tenant `users.email` e central
  `dono_email`); se o login mudar depois, pode divergir do central — reconciliação fica para fase futura.
- **Testes:** `EstabelecimentoTest` (validadores; captura central no onboarding; criação rápida; backfill;
  relação/normalização) + ajustes de renumeração em `OnboardingTest`/`PlanoTenantTest`. Suíte 490/490.
  Validado ponta-a-ponta no dev (tenant `fase3ademo` criado pelas 7 etapas; registro central gravado com
  dígitos normalizados). **Sem deploy** — em produção a migration roda com **backup antes** e o tenant
  real ganha o registro pela tela Dados (3b).

---

## D57 — Tela "Dados": ler/editar o cadastro central do estabelecimento (Fase 3b)
> Fecha a 3a. Componente admin que lê/edita `estabelecimentos` (D56). **Sem migration/tabela/seeder.**
> Reusa model + validadores BR da 3a. Edita o contato **CADASTRAL** do dono — NÃO o login (que vive no
> tenant). Ver [[Cadastro Central do Estabelecimento]] e [[Painel Super-Admin (Central)]].
- **Componente** `App\Livewire\Admin\EstabelecimentoDados` (view `estabelecimento-dados.blade.php`),
  rota **`admin.tenant.dados`** (`/admin/estabelecimentos/{tenantId}/dados`, `auth:admin`; caminho mais
  específico que `{tenantId}` → não conflita com o detalhe).
- **`firstOrNew(['tenant_id' => $id])`** no `mount` (formulário) e de novo no `salvar`: tenant antigo
  **sem** registro abre vazio e a linha é **criada sob demanda** no 1º save (com `tenant_id` setado);
  reabrir e salvar **atualiza a mesma linha** (não duplica — `tenant_id` é unique).
- **Form:** estabelecimento (nome fantasia obrigatório; endereço/faturamento/documento opcionais,
  documento validado se preenchido) + contato do dono (nome/sobrenome/email/celular/CPF obrigatórios,
  validados). Salva **normalizado** (`soDigitos`). Máscaras formatam os dígitos guardados na exibição.
- **UI:** nota explícita na seção do dono — "contato cadastral/cobrança; e-mail e senha de **login** são
  gerenciados à parte (no tenant), editar aqui não altera o acesso". Botão **"Dados"** em cada linha da
  lista (`Tenants`) + atalho no `TenantDetalhe`.
- **Testes:** `tests/Feature/Admin/EstabelecimentoDadosTest.php` (guard; carrega existente; edita e
  persiste normalizado; **cria sob demanda** p/ tenant antigo sem duplicar; barra CPF/celular inválidos;
  valida documento; botão na lista). Suíte **497/497**. Verificado no dev: `fase3ademo` (com registro)
  carrega com máscaras; `barbeariateste` (antigo) abre vazio e ganha o registro ao salvar. **Sem deploy.**

---

## D58 — Modelo central de cobrança SaaS: assinatura + faturas + situação (Fase 4a)
> Cobrança **salão → Nextgest** (≠ Clube, que é cliente → salão, no banco do tenant). Só **modelo de
> dados + cálculo de situação + backfill** — sem UI de operação, sem geração de faturas, sem gateway,
> sem bloqueio de login. Tela de Faturamento é a 4b; suspensão/bloqueio é a 4c. Ver
> [[Cobrança da Assinatura SaaS]].
- **Tabelas centrais aditivas:** `assinaturas` (1:1 com `tenants`, FK string + unique) e `faturas`
  (1:N de assinatura, **unique** `assinatura_id`+`competencia`). Migrations de central (rodam com
  `migrate`, **não** `tenants:migrate`).
- **Snapshots:** `assinaturas.valor_mensal` e `faturas.valor` guardam o preço no momento — mudar o
  catálogo (`config/planos.php`) **não** reescreve histórico (testado).
- **`config/cobranca.php`:** `carencia_dias = 20` (dias após o vencimento em que o acesso continua →
  "atrasada"; passado isso → "suspensa"), `trial_padrao_dias = 30`.
- **Regras de vencimento/trial (spec da fase 4):** vencimento por `dia_vencimento` (1–28, clamp p/ mês
  curto) OU dia da adesão OU `data_primeira_cobranca` combinada. Trial por `trial_dias` OU
  `data_primeira_cobranca` (que sobrescreve). 1ª cobrança = `data_primeira_cobranca ?? data_inicio +
  trial_dias`.
- **Fonte única `Assinatura::situacaoAcesso()`** (consumida por 4b e 4c): `cancelada` (manual) →
  cancelada; hoje < 1ª cobrança → `em_teste`; sem fatura não paga vencida → `ativa`; fatura não paga
  mais antiga vencida há 1..carência → `atrasada`; vencida há > carência → `suspensa`. "Vence hoje"
  ainda **não** é atraso (conta do dia seguinte). Testes de fronteira: dia 20 = atrasada, dia 21 =
  suspensa; carência lida da config.
- **Models** `App\Models\{Assinatura,Fatura}` usam `CentralConnection`; `Tenant::assinatura()` (hasOne).
- **Backfill idempotente** `nextgest:provisionar-assinaturas` (dry-run padrão, `--apply`): cria 1
  assinatura `em_teste` por tenant sem assinatura (plano = `planoAtual()`, valor = `preco_mes` do
  catálogo quando conhecido senão 0, `data_inicio` = `created_at`, `trial_dias` = padrão). **Só cria,
  nunca atualiza/apaga**; rodar 2× não muda nada.
- **Testes:** `tests/Feature/Cobranca/ModeloCobrancaTest.php` (10) — fronteiras de situação, snapshot,
  fatura paga não conta, cancelada, override de 1ª cobrança, backfill dry-run/apply/idempotência.
  Suíte **507/507**. No dev, backfill provisionou os 4 tenants (`em_teste`); 2ª execução criou 0.
  **Nada de painel/login/portal/Clube tocado. Sem deploy** (em produção: migrations com backup antes).
