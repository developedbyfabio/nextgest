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

---

## D59 — Tela "Faturamento" no admin: configurar assinatura + gerar/marcar faturas (Fase 4b)
> Consome o modelo da 4a (D58). **Operação manual + visualização**: NENHUM bloqueio de login (4c) e
> NENHUM gateway (Fase 5). Cobrança salão → Nextgest (≠ Clube). Ver [[Cobrança da Assinatura SaaS]].
- **Componente** `App\Livewire\Admin\Faturamento` (view `faturamento.blade.php`), rota
  **`admin.tenant.faturamento`** (`/admin/estabelecimentos/{tenantId}/faturamento`, `auth:admin`).
  Botão **"Faturamento"** na lista (`Tenants`) + atalho no `TenantDetalhe`.
- **firstOrNew + save no 1º uso:** abre a assinatura do tenant (cria com defaults se não existir —
  plano atual, `preco_mes`, `data_inicio=created_at`, `trial` padrão, `em_teste`).
- **Situação no topo** via `Assinatura::situacaoAcesso()` (badge) + "vencida há N dias / carência até
  DD/MM" quando atrasada/suspensa. **Só informativo — não bloqueia nada** (confirmado: login do tenant
  com assinatura `suspensa` continua 200).
- **Config:** `valor_mensal` (editável — lançamento/acordo), `data_inicio`, `trial_dias` **ou**
  `data_primeira_cobranca` (sobrescreve), `dia_vencimento` (1–28), `status` e `observacoes`. **Status
  manual só `em_teste/ativa/cancelada`** — `atrasada/suspensa` são derivadas (Rule::in barra setar à
  mão). **Não** mexe em plano/recursos (isso é a tela Editar).
- **Faturas:** "Gerar fatura" (competência mês + valor default `valor_mensal` + vencimento default
  derivado, editável); respeita o unique (competência duplicada → erro amigável, **sem 500**). Por
  fatura: **marcar paga** (data + forma, default `manual`), **reverter** (paga→aberta, correção) e
  **cancelar**. `link_pagamento` continua **nulo** (gateway é a Fase 5). Dinheiro em **decimal**.
- **Sem trilha de auditoria** de quem marcou/reverteu (melhoria futura).
- **Testes:** `tests/Feature/Admin/FaturamentoTest.php` (8) — guard; cria no 1º uso; salva config;
  barra status derivado; gera + barra duplicada; marca paga→ativa→reverte→cancela; badge suspensa no
  admin (o **bloqueio efetivo do login chegou na 4c/D60**); botão na lista. Suíte **515/515**.
  Verificado no dev (fase3ademo: em_teste → fatura aberta/Suspensa → paga/Ativa). **Sem deploy.**

---

## D60 — Suspensão por pagamento: bloqueio do painel + banner de carência (Fase 4c)
> Fatia que toca o login do painel. **Auditoria-primeiro**: confirmado que o grupo `tenant`
> (bootstrap/app.php) faz `InitializeTenancyByPath` → `GarantirTenantAtivo` → sessão, e embrulha
> portal + painel; um middleware no grupo `painel` roda **depois** disso e **só** no painel.
> Enforcement **ao vivo** via `situacaoAcesso()` (sem cron). Ver [[Cobrança da Assinatura SaaS]].
- **Middleware `App\Http\Middleware\GarantirAssinaturaAtiva`** no grupo `painel` (guard `web`) —
  **nunca** no portal/cliente nem no `/admin`. `suspensa`/`cancelada` → redireciona para a tela de
  suspensão. `em_teste`/`ativa`/`atrasada` → segue (atrasada **não** bloqueia). Tenant **sem
  assinatura** (null) → não bloqueia (defesa). **Auto-isento** em `painel.assinatura.suspensa` (evita
  loop) e em `painel.logout` (deixa sair).
- **Tela `App\Livewire\Auth\AssinaturaSuspensa`** (rota `painel.assinatura.suspensa`, layout `auth`,
  **sem exigir login** → o dono cai nela ao tentar o painel). Mostra a **fatura pendente**
  (`Assinatura::faturaPendente()`); botão **"Pagar agora"** só quando houver `link_pagamento` (nulo
  hoje → orientação de regularização, sem botão quebrado; pronta pro gateway da Fase 5). Se não estiver
  bloqueada, redireciona ao login (sem ficar presa).
- **Banner de carência** no layout do painel (`atrasada`): "sua fatura venceu em DD/MM, regularize em
  até N dias (até DD/MM)…". N e datas saem do vencimento + `config('cobranca.carencia_dias')`. **Gate
  por `can('ver_financeiro')`** (permissão existente, **exclusiva do Dono** no seeder — sem papel, sem
  permissão nova; ajustável). Calculado no `@php` do topo do layout (mesmo bloco — evita o bug de match
  guloso do Blade, D52).
- **Distinto do inativo:** `ativo=false` segue **404** (`GarantirTenantAtivo`) — caminho separado, não
  vira tela de suspensão. **Portal do cliente intacto** (guard `cliente`, fora do middleware).
  **Reversível ao vivo:** marcar a fatura paga (tela 4b) → próximo request do painel volta a 200.
- **Testes:** `tests/Feature/Cobranca/SuspensaoTest.php` (10) — matriz HTTP: ativa/atrasada não
  bloqueiam (banner só p/ Dono); suspensa/cancelada redirecionam (login e painel); tela isenta sem
  loop; logout isento; portal 200; inativo 404 (mesmo com assinatura suspensa); reversível ao pagar.
  Suíte **525/525**. Verificado no dev (banner em atrasada; tela de suspensão; portal do suspenso no ar).
  **Sem deploy** — produção depois, com cuidado redobrado (login de clientes reais) + backup.

---

## D61 — Adesão recorrente via Mercado Pago Preapproval (Fase 5a, sandbox)
> Modelo "Netflix": o dono cadastra o cartão **uma vez** e o MP debita o plano todo mês. Esta fase é só
> a **ADESÃO** (criar a recorrência + obter o link). Confirmação das cobranças mensais = **webhook (5b)**.
> **Passo 0 (doc oficial) confirmado**: o fluxo de `init_point` existe → sem Bricks. Ver
> [[Cobrança da Assinatura SaaS]] (mapeamento da API).
- **API:** `POST https://api.mercadopago.com/preapproval`, fluxo **"pago pendente"**: `status:"pending"`
  + **sem `card_token_id`** → resposta traz **`init_point`** (página hospedada do MP onde o dono põe o
  cartão). Payload: `reason`, `external_reference`(=tenant_id), `payer_email`, `back_url`, `status`,
  `auto_recurring{frequency:1, frequency_type:"months", transaction_amount, currency_id:"BRL",
  start_date}`. **`start_date` exige milissegundos + `Z`** (`Y-m-d\TH:i:s.v\Z` em UTC — `toIso8601String()`
  do Carbon falha). 1ª cobrança = `primeiraCobranca()` (fim do trial); se já passou, `now()+buffer`.
- **Segredo:** `MERCADOPAGO_ACCESS_TOKEN` só via `config('mercadopago.access_token')` (`config/mercadopago.php`).
  **Nunca** cravado/logado/exposto; só **token de TESTE** nesta fase. Em falha, loga só `http_status` +
  `mp_message` (sem token).
- **Client** `App\Services\MercadoPago\PreapprovalClient` (`criarPreapproval`/`consultar`); erro →
  `MercadoPagoException` (mensagem amigável). **Colunas centrais** em `assinaturas`:
  `mp_preapproval_id` (unique), `mp_status`, `link_adesao` (init_point), `cobranca_automatica`.
- **UI:** botão **"Ativar cobrança automática"** na tela Faturamento — **idempotente** (não recria se já
  há `mp_preapproval_id`), exige `valor_mensal>0` e `dono_email` (tela Dados), trata erro sem 500, e
  exibe o link de adesão + `mp_status`. **Falha de cobrança mensal:** carência de 20 dias começa na
  falha (efetivo na 5b — só registrado aqui).
- **Testes:** `tests/Feature/Cobranca/PreapprovalTest.php` (7) — API **mockada** (Http::fake): payload do
  fluxo pending (sem card_token_id), persistência, **idempotência** (1 chamada), erro tratado, guardas
  (valor 0 / sem e-mail). Suíte **532/532**.
- **Validação real (sandbox):** conectividade OK; token TEST confirmado; o erro de **formato de
  `start_date` foi pego e corrigido** numa chamada real. A criação real ainda retorna **HTTP 500 opaco**
  do MP ("Internal server error") — atribuído à **conta de teste** (Assinaturas não habilitadas / test
  buyer ausente), **não** ao nosso payload. A autorização via `init_point` (test buyer + cartão de teste)
  é o passo **manual** do Fabio no painel do MP. **`faturas`/webhook/motor/portal/produção intactos.
  Sem deploy.**

---

## D62 — Webhook do Mercado Pago: confirmação das cobranças + reconciliação (Fase 5b)
> Fatia **mais sensível de segurança** (endpoint público). **Passo 0 confirmado** (doc oficial):
> assinatura `x-signature` validável por HMAC — sem divergência → segui. Ver
> [[Cobrança da Assinatura SaaS]] (algoritmo registrado).
- **Validação de assinatura (item nº 1):** `App\Services\MercadoPago\ValidadorWebhook`.
  `x-signature = "ts=<ts>,v1=<hash>"`; manifest **`id:{data.id};request-id:{x-request-id};ts:{ts};`**
  (`data.id` em lowercase; o PHP troca o ponto por `_` no query → leio `data_id`/corpo); HMAC-SHA256 hex
  com `config('mercadopago.webhook_secret')`; `hash_equals` (timing-safe). **Sem assinatura válida →
  401, sem processar.** Sem segredo configurado → 401 (seguro).
- **Não confia no corpo:** sempre **consulta o recurso na API** (`PreapprovalClient::consultar` /
  `consultarPagamentoAutorizado`) e espelha o estado real.
- **Idempotência:** tabela central `webhook_eventos` (unique `gateway`+`evento_id`). Chave por
  recurso/estado: `authorized_payment:<id>` (pagamento) e `preapproval:<id>:<status>` (recorrência) —
  reenvio do MP é ignorado; status novo processa. Soma-se a idempotência de dado (`updateOrCreate` da
  fatura por competência).
- **Efeitos** (`ProcessadorWebhook`): `subscription_authorized_payment` **approved** → fatura **paga**
  (espelho: `forma_pagamento=mercadopago`, `gateway_referencia`=payment.id) + assinatura `ativa`;
  **rejected** → fatura **vencida na DATA DA FALHA** (não paga) → `situacaoAcesso()` conta os 20 dias
  dali (4c suspende após o prazo). `subscription_preapproval` → `mp_status`; `cancelled` →
  assinatura `cancelada`.
- **Resposta:** evento válido (mesmo duplicado) → **200**; assinatura inválida → **401**; falha ao
  consultar a API → **500** (o MP reenvia).
- **Reconciliação** (rede de segurança): comando `nextgest:reconciliar-assinaturas` (agendado em
  `console.php`, `dailyAt 03:10`, `withoutOverlapping`) — para cada assinatura com `cobranca_automatica`,
  consulta o MP e reaplica via o MESMO `ProcessadorWebhook` (mesmo dedupe → idempotente). Não depende
  só do webhook chegar.
- **Segredos:** `MERCADOPAGO_ACCESS_TOKEN` e `MERCADOPAGO_WEBHOOK_SECRET` só via `config()`; nunca
  logados/expostos; só sandbox.
- **Rota:** `POST /webhooks/pagamentos/{gateway}` → `WebhookPagamentoController` (mercadopago; outros
  gateways = stub 200). Central, CSRF já dispensado em `webhooks/*`.
- **Testes:** `tests/Feature/Cobranca/WebhookMercadoPagoTest.php` (10, API mockada + HMAC real):
  rejeição 401 (assinatura inválida/sem segredo); aprovado→paga/ativa; **idempotência** (reenvio →
  1 fatura/1 registro); recusado→vencida na falha→atrasada/suspensa; preapproval authorized/cancelled;
  preapproval desconhecido (ack); falha de API→500 sem registrar; reconciliação. Suíte **542/542**.
- **Validação ao vivo (dev):** POST sem assinatura → **401**; com assinatura forjada → **401**; gateway
  desconhecido → 200. O teste de transporte real (túnel HTTPS + "simular notificação" no painel MP +
  segredo no `.env`) é **manual do Fabio**. **Sem deploy** — produção: credenciais/URL de produção + HTTPS
  público + backup.

## D63 — Deploy de produção da leva D54–D62 (visual admin, planos, cadastro, cobrança, MP)

- **Escopo:** publicação em produção (`nextgest.com.br`) de D54 (visual admin) a D62 (webhook MP),
  passando por planos+gating (D55), cadastro central do estabelecimento (D56/D57), modelo de cobrança
  (D58), tela Faturamento (D59), suspensão+carência (D60) e adesão recorrente MP (D61). Inclui o D53
  (seeder de tenant aditivo) que já estava no `main`.
- **Migrations centrais ACUMULADAS e aditivas:** 5 numa leva (`estabelecimentos`, `assinaturas`,
  `faturas`, `+mercadopago em assinaturas`, `webhook_eventos`). **Backup central+tenants antes** e
  **checkpoint do `migrate:status`** (parar, conferir que são centrais/aditivas e nenhuma de tenant)
  antes do `migrate --force`. Nada de `tenants:migrate`/`fresh`.
- **Provisionamento idempotente:** `nextgest:provisionar-assinaturas` (dry-run → `--apply`) criou a
  assinatura do tenant real `teste` como **`em_teste`** (não bloqueia — está em trial). Rodar de novo
  não recria.
- **Suspensão mexe no login real:** validado explicitamente que o dono do `teste` cai no **login**
  (não na tela de suspensão) e que o **portal do cliente** segue 200. O `GarantirAssinaturaAtiva` só
  barra `suspensa`/`cancelada`; enforcement ao vivo via `Assinatura::situacaoAcesso()`.
- **MP inerte no deploy:** app sobe sem as vars do Mercado Pago; ativação (credenciais + URL do
  webhook + 1ª adesão real) é um **passo separado** feito pelo Fabio quando quiser. Segredos só no
  `.env`, nunca no chat/log.
- **Efeito colateral bom:** o `config:cache` desta leva assou `APP_TIMEZONE=America/Sao_Paulo`
  (antes o cache estava em UTC) — corrige o "agendar hoje" diagnosticado anteriormente.
- **Estado final:** produção no commit do D62; landing/admin/painel/portal intactos; worker+cron
  ativos; nenhuma suspensão acionada. Procedimento detalhado no [[Roteiro de Deploy Seguro]].

---

## D64 — Deploy de produção do incremento pós-D63: observabilidade do webhook + guard de testes

- **Escopo:** subir os 2 commits que entraram após o D63 — `ac68b93` (logs de observabilidade no
  webhook do MP, **sem mudar lógica**) e `b80f386` (travas anti-incidente na suíte de testes) + doc.
  **Sem migration** (deploy só-código backend + testes). MP de produção **segue não ativado**.
- **Logs do webhook = só fatos:** registram `tipo`, `action`, `data_id`, `live_mode`, `chave`,
  `status`, ids e desfecho (recebido / assinatura inválida→401 / válida / aprovada / recusada /
  duplicado / não encontrada). **Nunca** token, secret, x-signature ou corpo. Validado no diff e no
  log real (POST de teste → 401 logado sem segredo).
- **Gotcha LOG_LEVEL:** em produção `LOG_LEVEL=warning`, então os `Log::info` do webhook (recebido,
  assinatura válida, cobrança aprovada) **não são gravados** — só `warning`/`error`. Para
  observabilidade completa durante a ativação/depuração do MP, baixar temporariamente para
  `LOG_LEVEL=info` no `.env` + `config:cache` (decisão do Fabio).
- **Guard de testes (não afeta runtime):** `tests/bootstrap.php` limpa cache de config antes da
  suíte e o `TestCase` exige conexão sqlite — impede que um config cacheado aponte o `php artisan
  test` para o banco real. Afeta só a suíte.
- **Checkpoint do `migrate:status`:** confirmado **ZERO pendente** (D54–D62 no batch [3]); nenhum
  `migrate` rodado. Procedimento "deploy só-código" no [[Roteiro de Deploy Seguro]].
- **Regressão validada:** site 200, admin (login + Editar/Dados/Faturamento, guest→login sem 500),
  **dono real do `teste` cai no login (não na suspensão)**, portal do cliente no ar, webhook de pé
  (401 sem assinatura), MP inerte, `laravel.log` sem erro novo.

---

## D65 — Confirmações nativas do navegador → modal padrão do sistema (Flux)
> Só a CAMADA de confirmação mudou (popup `confirm()`/`wire:confirm` → modal Flux). Lógica das ações,
> permissões/RBAC e a exigência de confirmar nas destrutivas: **intactas**. Auditoria-primeiro: o
> painel do tenant já estava no padrão; só o /admin tinha `wire:confirm` (4 pontos das fatias recentes).
- **Reuso (não criei nada novo):** componente já existente **`x-ng.confirmar`**
  (`components/ng/confirmar.blade.php`) — modal Flux com `titulo`/`texto`/`icone`/`tom` (**red**
  destrutiva | **amber** atenção/reversível), botão "Voltar" + o confirmar no **slot**. Padrão D27 usado
  em equipe/unidades/serviços/bloqueios.
- **Pattern aplicado:** disparo `wire:click="pedirX(...)"` → no componente `Flux::modal('nome')->show()`
  (guarda o id alvo quando há); confirmar no slot chama o método **existente** (`inativar`/`cancelar`/
  `reverter`/`trocarPlano`), que faz o trabalho e dá `Flux::modal('nome')->close()`.
- **4 pontos trocados (/admin):** Inativar estabelecimento (`tenants`, amber), Aplicar plano
  (`tenant-detalhe`, amber, `pedirTrocarPlano` valida a seleção antes de abrir), Reverter pagamento
  (`faturamento`, amber) e **Cancelar fatura** (`faturamento`, **red**). Reset de 2FA já era modal.
- **Testes:** `tests/Feature/Admin/ConfirmacaoModalTest.php` (3) — as telas do admin **não** contêm
  `wire:confirm` e o modal de confirmação está renderizado. Suíte **545/545**. Verificado por HTTP
  (Playwright): modais abrem na marca (amber/red) e **zero diálogos nativos** detectados. Sem migration.
  **Sem deploy** (mudança só-código; produção é deploy à parte).

---

## D66 — Bugs do Clube (modal de assinante) + polimentos de UI de baixo risco
> Fatia segura: 2 bugs + 2 polimentos. **Não** tocou `MotorDisponibilidade`, fluxo de atendimento,
> faturamento, RBAC/anonimato nem backend de cobrança — só UI/navegação. Auditoria-primeiro: bugs do
> Clube reproduzidos no dev antes de corrigir.
- **Bug (causa raiz comum) — modal "Adicionar assinante":** o gatilho era
  `wire:click="$set('novoClienteId', null); $flux.modal('novo-assinante').show()"` — misturar a magia
  **Alpine `$flux`** dentro de um **`wire:click`** (Livewire) é malformado: ao renderizar a aba
  Assinantes o `.show()` disparava sozinho (**modal abria automático**) e o clique ficava
  não-determinístico. **Correção:** método server-side `Clube\Index::novoAssinante()` (reseta +
  `Flux::modal('novo-assinante')->show()`, igual a `novoPlano`/`gerirBeneficiarios`) + botão
  `wire:click="novoAssinante"`. Regra de negócio do Clube intacta.
- **Polimento — estado vazio do Início:** card "Nenhum agendamento para hoje" (`resumo-do-dia`) ganhou
  link **"Ver agendamentos →"** (cor de acento, `wire:navigate`) para `painel.agenda`, nos dois blocos
  (casa/gestão e pessoal/profissional). Só navegação.
- **Polimento — animação do menu:** transição suave ao abrir um grupo do acordeão da sidebar — keyframe
  CSS `ng-menu-reveal` em `ui-disclosure[data-flux-sidebar-group] > div` (`app.css`), respeitando
  `prefers-reduced-motion`. **Não** mexe na lógica do acordeão nem no highlight do grupo/item ativo
  (D47/D52); roda no abrir (display→block) e no load; sobrevive a `wire:navigate`.
- **Testes:** 2 em `ClubeTest` (botão usa `novoAssinante`, sem `$flux` no `wire:click`; adicionar
  assinante cria a assinatura). Suíte **547/547**. Verificado por HTTP (Playwright): modal não abre
  sozinho, abre no clique, assinante criado; link do estado vazio presente. **Sem migration. Sem deploy.**

---

## D67 — Filtro por profissional em "Últimos serviços" (sem enfraquecer o anonimato D51)
> Mais um filtro na aba de avaliações, **só na visão do Dono**. O anonimato do profissional (D51)
> continua forçado **no servidor** — o filtro não vira brecha. Reusa a permissão existente; sem RBAC
> novo. Ver [[Últimos serviços (Avaliações)]].
- **Visão do Dono** (`can('ver_avaliacoes')`): select "Profissional" na barra de filtros; filtra a
  lista por profissional **mantendo o nome do cliente** (gestão). Combina com os demais filtros; o
  resumo do topo recalcula (usa o mesmo `escopo()`). A lista de profissionais é coerente com a unidade
  selecionada; trocar a unidade limpa o profissional escolhido.
- **Visão do profissional** (`ver_avaliacoes_proprias`): **inalterada** — só os atendimentos dele,
  **anônimo** (a query nem carrega `cliente`), e o select de profissional **não é renderizado**.
- **Blindagem (lição 8):** o filtro só é aplicado quando `podeVerTudo()` é true
  (`->when($this->podeVerTudo() && $this->filtroProfissional, …)`). Para o profissional o gate é false
  → o `filtroProfissional` recebido é **ignorado** e o escopo já está forçado em `profissional_id =
  auth id`. Mandar outro `profissional_id` **não** vaza dados de outro nem o nome do cliente.
- **Componente:** `App\Livewire\Painel\Avaliacoes\Index` (+ view) — só somou o filtro; query/anonimato/
  permissão preservados. **Não** tocou motor/atendimento/faturamento/RBAC.
- **Testes:** `AvaliacoesPainelTest` +3 (Dono filtra e mantém o cliente; select não renderiza p/ o
  profissional; **SEGURANÇA**: profissional forçando outro id segue só com os dele e anônimo). Suíte
  **550/550**. Verificado por HTTP (Playwright): Dono filtra por "Jorge Tesoura" com nomes de cliente
  e resumo recalculado; profissional (Ana) sem o filtro, sem coluna de cliente. **Sem migration. Sem deploy.**

---

## D68 — Início: card "Previsão de faturamento" (substitui "Vendas pagas") + gráfico da semana
> Lê a **agenda** (tabela `agendamentos`) — **NÃO** toca o `MotorDisponibilidade` (engine de slots é
> outra coisa). Só apresentação/leitura. Ver [[Dashboard do Dono]].
- **Regra (a receber):** previsão = Σ `agendamentos.valor_total` da **semana corrente** (Seg–Dom, no
  fuso do app) cujo status ainda é **a atender** — `whereNotIn('status', ['concluido','cancelado',
  'nao_compareceu'])` (= pendente/confirmado/em_andamento). Exclui concluído/cancelado/no-show. É
  **a receber**, não realizado — sublinha "a receber esta semana".
- **Sempre a semana atual:** independe do filtro de período do dashboard; respeita o filtro de
  **unidade**. `Carbon::now()->startOfWeek(MONDAY)..endOfWeek(SUNDAY)` (fuso `APP_TIMEZONE`).
- **Onde:** métodos `Metricas::previsaoSemana()` e `previsaoSemanaPorDia()` (query **agregada**, sem
  N+1). O card **substitui** "Vendas pagas" no bloco Financeiro (mesmo público — `ver_dashboard`,
  Dono/Gerente; quem não via o bloco continua sem). Gráfico de barras `chave="previsao"` reusando
  `x-ng.grafico` (Chart.js já existente; sem lib nova), por dia Seg–Dom.
- **Testes:** `DashboardTest` +4 (regra exclui cancelado/concluído/no-show e só semana corrente;
  por-dia 7 dias Seg–Dom; filtro de unidade; card substitui "Vendas pagas"). `ContagemQueriesTest`
  do dashboard segue **≤ 25** (as 2 queries agregadas extras não viram N+1). Suíte **554/554**.
  Verificado por HTTP (Playwright): card "Previsão de faturamento" R$ 570,00 + gráfico "Previsão da
  semana (a receber)" coerentes. **Sem migration. Sem deploy.**

---

## D69 — Aviso "próximo atendimento chegando" (toast por polling, 15 min antes)
> Avisa o **profissional logado** quando ele tem atendimento "a atender" começando em ≤ 15 min, com um
> **toast** (reusa o sistema do Flux) que some sozinho. Tempo real por **polling Livewire leve**, sem
> WebSocket. Só **LÊ** a agenda — não toca o `MotorDisponibilidade`. Ver [[Aviso de próximo atendimento]].
- **Componente global** `App\Livewire\Painel\AvisoProximoAtendimento` (view só com o gatilho), embutido
  no **layout do painel** — porém **só montado quando `e_profissional`** (`@if` no layout): não-profissional
  nem carrega o componente → zero polling/queries.
- **Checagem** `verificar()`: se profissional, busca o **próximo** atendimento dele com status a atender
  (`whereNotIn('status', ['concluido','cancelado','nao_compareceu'])`) e `data_hora_inicio` em
  `(agora, agora+15min]` (fuso `APP_TIMEZONE`, índice composto `(profissional_id, data_hora_inicio)`).
  Achou → `Flux::toast(heading: 'Seu próximo atendimento está chegando', text: '<cliente> · HH:MM ·
  <serviço>')`.
- **Idempotência:** guarda os ids avisados na **sessão** (`aviso_proximo:<userId>`) — avisa **uma vez**
  por agendamento, sem repetir a cada poll nem entre navegações.
- **Disparo por `wire:init` + `wire:poll.60s` (NÃO no mount):** telas que redirecionam no `mount` (ex.:
  o Dashboard manda o profissional p/ a agenda) ainda **renderizam** o layout/este componente antes do
  redirect — rodar no `mount` "consumia" o aviso numa página descartada (marcava a sessão e o toast se
  perdia). `wire:init` roda no cliente, só em páginas realmente exibidas. (Bug pego e corrigido na
  validação.)
- **Testes:** `tests/Feature/Painel/AvisoProximoAtendimentoTest.php` (7) — dispara na janela;
  idempotente (sessão); marca na sessão; fora da janela não dispara; status encerrado não dispara;
  não-profissional não dispara; **só o profissional daquele atendimento** (não o de outro). Suíte
  **561/561**. Verificado por HTTP (Playwright): toast aparece p/ a profissional (Ana) e some sozinho.
  **Sem WebSocket. Sem migration. Sem deploy.**

---

## D70 — Modal de atendimento: "todo concluído gera comanda" (remoção do botão "Concluído")
> **Decisão de negócio (Fabio):** não existe "atendimento concluído sem comanda". Logo, o único
> caminho até o status `concluido` passa a ser o **"Finalizar atendimento"** (que gera/abre a comanda).
> Auditoria que embasou: [[Fluxo de atendimento (modal da agenda)]] (Fatia 5 — Parte 1).
- **Botão "Concluído" removido** do modal/flyout da agenda (a opção que chamava
  `mudarStatus('concluido')`).
- **`Agenda\Index::mudarStatus()` com whitelist** (`STATUS_VIA_MUDAR =
  ['confirmado','em_andamento','cancelado','nao_compareceu']`): qualquer destino fora dela — em
  especial `'concluido'` — é **rejeitado** com toast "Para concluir, use 'Finalizar atendimento'".
  Fecha a brecha de concluir manipulando o componente. **O `Agendador::mudarStatus()` NÃO mudou** —
  continua aceitando `concluido` porque é o `finalizarAtendimento()` quem o usa (concluir + comanda).
- **Status `concluido` preservado** (não removido do sistema): segue consumido por **Avaliações (D51)**
  (escopo `where status=concluido`, popup/elegibilidade), **Previsão (D68)** (que o exclui do "a
  receber") e **métricas** de comparecimento/ranking (`Metricas`). Comanda/venda/comissão/estoque não
  leem o status (independentes). Sem observer reagindo a `concluido`.
- **Botões reorganizados (só view):** Confirmado/Em andamento/Não compareceu/**Remarcar** ficam
  **grandes (largura cheia)** empilhados **acima**; **Cancelar** com **realce de risco** (vermelho),
  mantendo a confirmação em modal (D65) e a liberação de horário inalterada; o **"Finalizar
  atendimento"** segue `primary`, embaixo, como ação conclusiva. Não tocamos `MotorDisponibilidade`
  nem a lógica de cada ação — só tamanho/posição/variante.
- **Testes:** `AgendaTest` ganhou 2 — "NÃO conclui pelo mudarStatus" (status intacto + 0 comandas +
  toast) e "modal não oferece o botão Concluído" (`assertDontSeeHtml mudarStatus('concluido')` +
  `assertSeeHtml finalizarAtendimento`). `FinalizarAtendimentoTest` segue verde (conclusão via
  Finalizar). Suíte **563/563**. Verificado por HTTP (Playwright, Dono): flyout sem "Concluído",
  botões grandes acima e "Finalizar" em destaque. **Sem migration. Sem deploy.**

---

## D71 — Suspensão por pagamento revalidada também nas AÇÕES Livewire (correção M1)
> Achado **M1** da [[Auditoria de Segurança (rev. 1)]]: o `GarantirAssinaturaAtiva` só rodava no **GET
> de página**; o middleware persistente do tenancy reaplicava só o `InitializeTenancyByPath`. Uma aba
> aberta **antes** da suspensão continuava **executando ações Livewire** (furava o lever de cobrança).
> Ver [[Cobrança da Assinatura SaaS]].
- **Correção (1 linha, centralizada):** `GarantirAssinaturaAtiva` entra na lista de
  **persistent middleware do Livewire** (`AppServiceProvider::boot`), **depois** do
  `InitializeTenancyByPath`.
- **Por que é seguro/auto-escopado:** o Livewire só reaplica um persistent middleware se ele estava na
  **rota original** do componente (`PersistentMiddleware::getApplicablePersistentMiddleware` junta a
  middleware da rota e filtra pela lista). Como o `GarantirAssinaturaAtiva` só vive no **grupo do
  painel**, ele é reaplicado nas ações **do painel** e **nunca** nas do **portal/cliente** (cuja rota
  não o tem) → **portal intacto**. Ordem: `InitializeTenancyByPath` antes → a tenancy inicializa
  primeiro (lição 4, senão 500). O `redirect` para a tela de suspensão é respeitado pelo Livewire
  (`Utils::applyMiddleware` faz `abort($response)`), igual ao `Authenticate` persistente.
- **Escopo:** só painel (guard web). `em_teste`/`ativa`/`atrasada` agem normal. A tela de suspensão
  não tem ação Livewire → sem loop. **GET inalterado** (persistent só atua no `/update`).
- **Reprodução + verificação (área sensível, fim-a-fim por HTTP/Playwright):** ANTES — suspenso, ação
  `navegar` **executava** (data 27→28/06, sem redirect). DEPOIS — suspenso, a ação é **bloqueada e
  redireciona** para `assinatura-suspensa` (data não muda). Dono **ativo** age normal. **Portal** do
  tenant suspenso segue **200**.
- **Testes:** `SuspensaoTest` +2 — (1) `Livewire::getPersistentMiddleware()` contém o middleware;
  (2) a rota do painel o tem e a do portal **não**. Suíte **565/565**.
- **B1 (gating de recurso nas ações Livewire):** fechado em seguida — ver **D72**. **Sem migration.
  Sem deploy.**

---

## D72 — Gating de recurso de plano revalidado nas AÇÕES Livewire (correção B1)
> Achado **B1** da [[Auditoria de Segurança (rev. 1)]]: o `VerificaRecurso` (`recurso:clube|whatsapp|
> gateway`) também só rodava no **GET**; uma aba de um componente gated aberta antes de o recurso ser
> desligado seguia executando ações. Baixo (sem o recurso a página nem carrega — 404 no GET), fechado
> "de graça" com a **mesma técnica do D71**.
- **Correção:** `VerificaRecurso` entra na lista de **persistent middleware do Livewire**
  (`AppServiceProvider`), depois do `InitializeTenancyByPath` e do `GarantirAssinaturaAtiva`.
- **Auto-escopado:** o Livewire só reaplica o que estava na **rota original**; o `VerificaRecurso` só
  vive nas rotas de **clube/integrações** → reaplicado só nesses componentes; resto do painel e portal
  intactos. O filtro casa por classe ignorando o argumento (`Str::before(':')`), então `recurso:clube`
  é reaplicado **com** o argumento.
- **Aborta 404** (não redirect) — padrão idêntico ao `Illuminate\Auth\Middleware\Authorize` (o `can:`),
  que **já** é persistente por padrão e aborta 403. Sem falso 404 no uso normal (quem não tem o recurso
  não tem snapshot).
- **Verificação (HTTP/Playwright):** clube carregado (recurso ON) → desliga `clube` na aba viva →
  ação Livewire do componente de clube retorna **404 (bloqueada)**. Recursos restaurados após o teste.
- **Testes:** `RecursosTenantTest` +2 — (1) `VerificaRecurso` está nos persistentes; (2) a rota do
  clube o tem (com `:clube`) e a de integrações (sem gate) não. Suíte **567/567**. **Sem migration.
  Sem deploy.**

---

## D73 — Transição suave da sidebar (expandir↔colapsar) + card "agendamento hoje" clicável
> Dois polimentos de UI/navegação no painel do tenant. Acordeão dos grupos (D66) e os dois estados
> da sidebar (D36/D47/D52) ficam **idênticos** — só ganham a transição. **Só view/CSS.**
- **Transição da sidebar inteira:** o Flux troca a largura por **classe** (`w-64` ↔
  `data-flux-sidebar-collapsed-desktop:w-14`, + `p-4` ↔ `px-2`) ao clicar no hambúrguer; **sem
  `transition` era "teleporte"**. Adicionada UMA regra em `resources/css/app.css` (fora de `@layer`,
  p/ vencer utilitários):
  `@media (min-width:1024px){ [data-flux-sidebar]{ transition: width .25s ease, padding .25s ease } }`.
  O conteúdo central **desliza junto** porque a coluna do grid do app é `min-content` (acompanha a
  largura a cada frame) — **sem reflow/salto**. Os rótulos são ocultados pelo próprio Flux
  (`display:none` no recolhido) e a sidebar tem `overflow-hidden` → **não quebram linha nem vazam**.
- **Só desktop (lg+)**, onde o colapso acontece; no **mobile** a sidebar é drawer com
  `transition-transform` (x-init do Flux) — **não tocado**. **`prefers-reduced-motion`** já é
  respeitado pelo bloco global do `@layer base` (zera a duração com `!important`) → troca instantânea.
  CSS puro → **sobrevive ao `wire:navigate`** (não volta a teleportar).
- **Card "N agendamento(s) hoje" clicável:** no `resumo-do-dia`, os estados **cheios** (casa e pessoal)
  ganharam o mesmo link **"Ver agendamentos →"** (`wire:navigate`) que os estados vazios já tinham
  (Fatia 1) — leva à agenda, navegável por teclado, cor de acento.
- **Verificação (HTTP/Playwright):** card cheio mostra o link e navega p/ `/painel/agenda`; sidebar
  anima **256→103→56px** (frame intermediário capturado — não teleporta), `transition-property =
  width, padding` / `.25s`, reexpande p/ 256; com `prefers-reduced-motion` a duração cai p/ ~0. Sem
  vazamento de rótulos no frame do meio. Suíte **567/567**. **Sem migration. Sem deploy.**

## D74 — Deploy de produção da leva D65–D73 + M1 + B1 (painel, segurança Livewire, sidebar)

- **Data:** 27/06/2026. **Commit publicado:** `66ab3d7` (D73). **Rollback:** `d1a580d` (D64).
- **Escopo:** entrou em produção a leva de melhorias do painel — D65 (admin: confirmação por modal
  `x-ng.confirmar`), D66 (Clube: modal de assinante + polimentos), D67 (Últimos serviços: filtro por
  profissional só p/ Dono, anonimato blindado), D68 (Início: card Previsão de faturamento + gráfico da
  semana), D69 (aviso "próximo atendimento chegando" por polling), D70 (modal: remove botão
  "Concluído" — todo concluído gera comanda), **D71/M1** (suspensão revalidada nas ações Livewire),
  **D72/B1** (gating de plano revalidado nas ações Livewire), D73 (sidebar anima + card "agendamento
  hoje" clicável).
- **Migrations: NENHUMA.** O índice composto `agendamentos(profissional_id, data_hora_inicio)` que a
  Fatia 4 (D68) usa **já existia** (criado antes na migration de tenant
  `2026_06_22_130001_add_indices_performance_agendamentos_vendas`). Deploy efetivamente **só-código**
  (+ build, pois `app.css`/views mudaram).
- **M1/B1 (defesa em profundidade Livewire):** o `GarantirAssinaturaAtiva` (suspensão) e o gating de
  recurso passaram a valer **também nas ações Livewire** (registrados como **persistent middleware**
  no `AppServiceProvider`; o `redirect` é respeitado via `Utils::applyMiddleware`→`abort`). Antes só
  o carregamento de página era barrado; agora um `wire:click` de tenant suspenso/sem-recurso também
  é bloqueado.
- **Validação de regressão:** site 200; admin (login + Editar/Dados/Faturamento, guest→login sem
  500); **Dono real do `teste` cai no login (não na suspensão)** — assinatura `em_teste`; portal do
  cliente no ar; tenant inexistente → 404 (`GarantirTenantAtivo` intacto, fora do diff); webhook MP
  de pé (401 sem assinatura); `laravel.log` sem erro novo; workers reciclados.
- **MP de produção:** **não ativado** (credenciais/URL/1ª adesão = passo manual à parte). Render
  visual fino do painel (gráfico, animação da sidebar, modal) confere no navegador do Fabio.

---

## D75 — WhatsApp Fatia 1: config por tenant + `WhatsAppService`/driver Evolution (envio de teste)
> Fundação de envio de WhatsApp no Nextgest, falando com a **Evolution única** (Fatia 0,
> `127.0.0.1:8088`). **Sem automação** (Fatia 3) e **sem tela de QR** (Fatia 2) — só envio MANUAL
> validado ponta a ponta. Ver [[WhatsApp (Evolution) no Nextgest]] e [[Infra — Evolution API (WhatsApp)]].
- **Modelo:** 1 Evolution, **1 instância por salão** (`ng_{tenantId}`, único na Evolution compartilhada).
- **Separação de credenciais (segurança):** a **key GLOBAL** da Evolution é segredo de **infra** —
  só no `.env` do Nextgest (`EVOLUTION_BASE_URL`/`EVOLUTION_API_KEY`/`EVOLUTION_TIMEOUT`) via
  `config('whatsapp.*')`. No **banco do tenant** (`whatsapp_config`, migration **aditiva**) vai só o
  que identifica o salão: `instancia` (nome) + `instancia_token` (token DAQUELA instância, cast
  `encrypted` + `$hidden`) + `status_conexao`. **A chave-mestra nunca entra no banco do tenant.**
- **Camadas:** `WhatsAppGateway` (contrato) ← `EvolutionGateway` (HTTP puro, por nome de instância) +
  `WhatsAppService` (orquestra por tenant: lê/grava `whatsapp_config`, usa o token da instância e cai
  para a global se ausente). Binding no `AppServiceProvider` (trocar de provedor = trocar o binding).
  Número normalizado (BR → DDI 55, sem `+`). Falha/timeout → `WhatsAppException` (log sem segredo,
  nunca 500).
- **Disparo manual (CLI, sem UI):** `nextgest:whatsapp-conectar {tenant}` (cria/garante a instância,
  persiste nome+token, salva o QR em PNG) e `nextgest:whatsapp-teste {tenant} {numero}` (envia texto).
  Ambos **gated pelo recurso `whatsapp`** (abortam se off).
- **Verificação:** 8 testes (`WhatsAppFatia1Test`, Evolution mockada): chamada certa à instância do
  tenant; token da instância vs global; normalização; erro tratado; gating; segredo cifrado/sem vazar
  + key global fora do banco. Suíte **575/575**. **Ponta a ponta real** (até o QR) contra a Evolution
  viva: `nextgest:whatsapp-conectar barbeariateste` criou `ng_barbeariateste`, persistiu a config e
  gerou o QR. **Entrega validada (28/06/2026):** número de teste conectado (state `open`),
  `whatsapp-teste` enviou e a mensagem **chegou** no destinatário. Gotcha: o WhatsApp pode dar
  "não é possível conectar novos dispositivos no momento" após vários scans seguidos (throttle do
  WhatsApp, não da Evolution — esperar e tentar 1 vez). **Sem deploy.**

---

## D76 — WhatsApp Fatia 2: item próprio no menu + tela de conexão (QR ao vivo + status)
> Tira a conexão do terminal e leva ao painel: item **"WhatsApp"** (saiu de Integrações) + tela de
> conexão (QR que renova, status ao vivo, desconectar/reconectar). Reusa a fundação D75. **Sem
> automação** (Fatia 3). Ver [[WhatsApp (Evolution) no Nextgest]] e [[Papéis e Permissões (RBAC)]].
- **Permissão — reuso, sem nova:** a permissão já existia como **`gerenciar_whatsapp`** (no
  `TenantDatabaseSeeder::PERMISSOES`, concedida ao Dono; Gerente mantém). **Não** criei `gerir_whatsapp`
  (seria duplicar). Confirmado em tenant existente: permissão presente + Dono já a tem → **sem
  mudança de seeder, sem backfill**. (O seeder já é aditivo/idempotente: `findOrCreate`/
  `givePermissionTo`, nunca `syncPermissions`.)
- **Editor antigo aposentado:** o editor *Integrações → WhatsApp* (API Cloud da Meta:
  `phone_number_id`/`token`) foi **removido** (rota `integracoes.whatsapp` + componente + view) e o
  WhatsApp **saiu do enum `Integracao`** (o hub de Integrações fica só com Pagamento → passa a exigir
  `gerenciar_pagamentos`, logo Dono-only). Testes dos 3 arquivos repontados/enxugados (o cofre cifrado
  agora é o `instancia_token` da Evolution, coberto na D75).
- **Item de menu próprio "WhatsApp"** (grupo Gestão), gated por `@recurso('whatsapp')` +
  `@can('gerenciar_whatsapp')`. Rota `painel.whatsapp` (`recurso:whatsapp` + `can:gerenciar_whatsapp`)
  → `App\Livewire\Painel\Whatsapp\Conexao`.
- **Tela (reusa WhatsAppService D75; + `desconectar()` no gateway/serviço = logout da instância):**
  estados **desconectado / aguardando / conectado / caiu / erro**. `wire:init=sincronizar` confirma o
  estado REAL no load (sem bloquear render, sem quebrar se a Evolution cair). `aguardando`:
  `wire:poll.3s` que **para ao conectar**. `conectado`: `wire:poll.20s` detecta **queda** → `caiu`
  (reconectar). QR renovável (expira ~1 min). Erros/timeout viram aviso amigável (sem 500, sem expor
  segredo). Nada de token/key na tela.
- **Verificação:** `WhatsAppConexaoTest` (9) — conectar/QR, status ao vivo (poll que para),
  sincronizar (estado real no load), monitorar (queda→caiu), desconectar, erro tratado, gating de
  recurso (on→200/off→404). `CredenciaisPermissaoTest` repontado para `/whatsapp`. Suíte **582/582**.
  Status ao vivo confirmado contra a Evolution real (`ng_barbeariateste` = `open` → tela "conectado").
  Screenshot via browser não capturado nesta rodada (cache do Playwright foi limpo + isolamento de
  loopback do navegador no sandbox — infra, não a feature). **Sem deploy.**

---

## D77 — WhatsApp Fatia 3: painel de configuração de automações (on/off + templates + testar)
> Painel de controle das automações (liga/desliga + template editável com variáveis por automação),
> em **transacionais** e **broadcast**, + botão **testar**. **NADA dispara automaticamente** — só
> config e teste manual. Reusa D75 (envio) e D76 (conexão/menu/permissão). Ver
> [[WhatsApp (Evolution) no Nextgest]].
- **Dados (aditivo, sem clash):** coluna JSON **`whatsapp_config.automacoes`** (cast `array`) com os
  OVERRIDES por tenant `{chave: {ativo, template}}`. O **catálogo** (categoria, variáveis, template
  padrão, rótulo) vive no enum **`App\Enums\AutomacaoWhatsapp`** (fonte da verdade). Escolhi JSON na
  config (singleton) em vez de tabela para evitar colidir com a legada `whatsapp_automacoes` (era API
  Cloud, sem uso) e manter 100% aditivo.
- **Catálogo (6):** transacionais — `lembrete_servico`, `cobranca_clube`, `avaliacao_pos_servico`;
  broadcast — `noticias`, `funcionamento`, `avisos_gerais`. Cada uma com suas variáveis
  (`{cliente}`/`{data}`/`{hora}`/`{servico}`/`{profissional}`/`{salao}`/`{plano}`/`{valor}`/
  `{vencimento}`/`{link}`) e template padrão.
- **Broadcast = sensível:** disparo em massa no WhatsApp não-oficial → risco de **ban**; exige opt-in/
  LGPD. Off por padrão + banner de aviso na tela. (Disparo em massa real, com throttle/opt-in, é fatia
  própria — aqui **não** dispara.)
- **Template seguro:** `RenderizadorTemplate::render()` é só `str_replace` (sem eval) — variável
  conhecida vira valor; **`{xpto}` desconhecida fica literal** (nunca quebra); valores têm caracteres
  de controle removidos (sem injeção).
- **Tela** `App\Livewire\Painel\Whatsapp\Automacoes` (rota `painel.whatsapp.automacoes`, mesmo gating
  `recurso:whatsapp` + `can:gerenciar_whatsapp`): aba (Conexão | Automações) na área WhatsApp; cards
  agrupados com toggle + editor + chips de variáveis; banner no broadcast; campo "número para teste";
  botão **Testar** = renderiza com **dados de exemplo** (`{salao}` = nome do tenant) e envia via D75.
  Erro/timeout vira aviso (sem 500). **Nenhum job/gatilho novo.**
- **Verificação:** `WhatsAppAutomacoesTest` (10) — catálogo (3+3), renderizador (literal/sem injeção),
  defaults off, persistência JSON, broadcast off, testar (variável inválida literal + envio), exige
  número, erro tratado, gating recurso (on→200/off→404). Suíte **592/592**. Print por HTTP/Playwright
  (servidor + navegador no mesmo shell p/ compartilhar o namespace de rede): tela com transacionais,
  broadcast (aviso), variáveis e abas. **Sem deploy.**

---

## D78 — Gateway de pagamento do tenant (Modelo A, direto pro dono) — Fatia G1: conexão OAuth
> O salão pluga a **própria** conta Mercado Pago via **OAuth**; o dinheiro cai nele, o Nextgest
> **orquestra mas não toca no dinheiro** (sem split/marketplace). Esta fatia faz **só conectar** —
> **não cobra** (G2). Ver [[Gateway de pagamento do tenant (Modelo A)]]. **Separado** da cobrança SaaS
> do admin ([[Cobrança da Assinatura SaaS]], Preapproval — dinheiro salão→Nextgest).
- **Decisão de unificação:** o editor manual antigo (*Integrações → Mercado Pago*, "colar token") foi
  **aposentado** e o **hub de Integrações deixou de existir** (WhatsApp já saíra no D76; só restava o
  MP). Removidos `Enums\Integracao`, `Integracoes\{Index,MercadoPago}` + views + rotas. Nada consumia
  o token manual (o adapter de cobrança é stub) → sem perda de função. MP do tenant agora é **só** via
  OAuth.
- **Credenciais (separação):** `client_id`/`client_secret`/`redirect_uri` do **app Nextgest** só no
  `.env` → `config/pagamentos.php` (placeholders; o Fabio registra o app no MP depois). **Nunca** no
  banco/log. O **token do salão** fica **cifrado** no cofre `gateways_pagamento.credenciais`
  (`encrypted:array`, `hidden`); colunas **públicas** aditivas p/ exibir: `conta_externa_id`,
  `conta_externa_nome`, `conectado_em`.
- **Abstração:** reusa o cofre + a interface `Services\Pagamentos\GatewayPagamento`/`GatewayResolver`
  (cobrança, stub). OAuth é uma peça à parte: `MercadoPagoOAuth` (URL de autorização + troca de code +
  `/users/me`) + `ConexaoGatewayMercadoPago` (orquestra state/sessão e gravação).
- **OAuth seguro:** "Conectar" gera `nonce` na **sessão** + `state = base64(tenant|nonce)` → redireciona
  ao MP. **Callback CENTRAL** `GET /oauth/mercadopago/callback` (slug `oauth` reservado; grupo `web`
  com sessão) valida o `state` contra a sessão (anti-CSRF de login — rejeita ausente/divergente),
  troca o code, grava no cofre do tenant e volta à tela. Token nunca logado.
- **Tela + menu:** item próprio **"Gateway de pagamento"** (Gestão), rota `painel.pagamentos`, gated
  `@recurso('gateway')` + `can('gerenciar_pagamentos')` (Dono). Estados desconectado/conectado (mostra
  só a conta pública) + conectar/desconectar.
- **Verificação:** `GatewayOAuthTest` (9, MP mockado) — state anti-CSRF (rejeita forjado, não troca),
  troca + token **cifrado** no cofre (cru no banco ≠ texto), desconectar limpa, callback HTTP
  conecta/rejeita, pendência de credenciais (não inventa), gating 404. `CredenciaisPermissaoTest` e
  `AutorizacaoTest` repontados para `/pagamentos`. Suíte **593/593**. Print (Playwright): tela
  desconectada + item de menu; estado conectado não vaza o token.
- **PENDENTE (esperado, não é falha):** o "conectar de verdade" depende do Fabio **registrar o app no
  Mercado Pago** (OAuth habilitado + Redirect URI = a rota de callback) e pôr `client_id`/`secret` no
  `.env`. **Não cobra nada. Sem deploy.**

---

## D79 — WhatsApp Fatia 4: lembrete de serviço (1ª automação real, anti-ban)
> Primeira automação que **dispara para cliente real**: lembrete X min antes do atendimento. Lê a
> agenda (**não toca o `MotorDisponibilidade`**), fuso `APP_TIMEZONE`, **idempotente**, **opt-out**
> respeitado e **freios anti-ban conservadores**. Reusa config/template (D77) + envio (D75).
> Ver [[WhatsApp (Evolution) no Nextgest]].
- **Comando** `nextgest:enviar-lembretes` no scheduler (`->everyMinute()->withoutOverlapping()`).
  Por tenant com `lembrete_servico` **ligada** + WhatsApp **conectado** (checa `status()` ao vivo):
  acha agendamentos a-atender com `data_hora_inicio ∈ (now, now+antecedência]`, cliente **não
  opt-out** e **ainda não avisado**, e **enfileira** o job. SÓ LÊ a agenda.
- **Idempotência:** tabela `lembretes_servico` (tenant) com `agendamento_id` **único**
  (`firstOrCreate` + `whereDoesntHave`) → **um** lembrete por agendamento; re-run/remarcação/restart
  não duplica.
- **Anti-ban (conservador, configurável via `.env`/`config('whatsapp.lembretes')`):** teto **por
  minuto** (`limite_por_minuto=4`) e **por dia** (`limite_por_dia=150`) por tenant; excedente do
  minuto fica pro próximo (a janela segura). Espaçamento intra-minuto via `delay()` do job — **vale
  com fila ASSÍNCRONA**; em dev a fila é `sync` (envia na hora), então o teto/minuto é o freio
  efetivo. WhatsApp **caído → não enfileira** (vencidos na queda saem da janela, **não acumulam**).
  Tabela `jobs` (central) criada p/ a fila `database`.
- **Job** `EnviarLembreteWhatsApp` (`tries=1`, sem retry = anti-storm): reinicializa a tenancy,
  **revalida** (status/futuro/opt-out/automação/telefone), renderiza o template (D77) com os dados
  reais (cliente/data/hora/serviço/profissional/salão) e envia via D75; marca `enviado`/`falhou`.
- **Aditivo:** `clientes.whatsapp_optout`; antecedência editável no card do lembrete (D77 →
  `automacoes['lembrete_servico']['antecedencia_min']`, fallback `antecedencia_min_padrao=120`).
- **Verificação:** `LembreteServicoTest` (10, Evolution mockada) — janela/fuso; idempotência (2x → 1);
  fora da janela; automação off; desconectado (não enfileira/não acumula); opt-out e status encerrado;
  **teto/minuto** (6 elegíveis, limite 4 → 4); job renderiza+envia+marca; job não reenvia; opt-out no
  job → falhou sem enviar. Suíte **603/603**.
- **PENDENTE (esperado):** disparo **real** validado pelo Fabio com **número de teste** (em dev a fila
  é `sync` → `php artisan nextgest:enviar-lembretes` envia na hora; produção precisa de
  `QUEUE_CONNECTION=database` + worker p/ o espaçamento). **Sem deploy.**
  > **Validado (28/06/2026):** lembrete real entregue ao número de teste (status `enviado`);
  > 2ª execução não reenviou (idempotência). Achado: a sessão do WhatsApp **caía** entre usos → motivou
  > a detecção de queda (D80).

---

## D80 — WhatsApp Fatia 4.5: número dedicado + termo de risco (trava) + detecção de queda
> Três proteções de UI/monitoramento — **não dispara nada**. Reusa o status ao vivo (D76) e a config
> de automações (D77). Ver [[WhatsApp (Evolution) no Nextgest]].
- **Aviso de número dedicado:** banner na tela de **Conexão** — usar um número **secundário/dedicado**
  (não o principal), risco de bloqueio.
- **Termo de risco que TRAVA (servidor):** `whatsapp_config` ganha `termo_aceito_em`/`termo_aceito_por`/
  `termo_versao` (aditivo). `WhatsappConfig::termoAceito()` = aceito **na versão atual**
  (`config('whatsapp.termo_versao')`; bump re-exige aceite). Em `Automacoes::salvar()`, **sem aceite
  nenhuma automação liga** — força todas `ativo=false` mesmo via request forjado (+ toast); ação
  `aceitarTermo()` registra quem/quando/versão. Toggles desabilitados no front até aceitar (defesa em
  profundidade; a trava real é no servidor).
- **Detecção de queda (2 lugares):** estado `caiu` já existia na tela (D76). **Banner no topo do
  painel** via componente `Painel\AvisoWhatsappConexao` embutido no layout (padrão do D69): `wire:init`
  chama `status()`; aparece **só** se recurso `whatsapp` + `can('gerenciar_whatsapp')` + **já conectou**
  (`instancia` setada) + status ≠ `open`. Evolution fora do ar (erro) → **não alarma** (é infra, não
  queda de sessão). Link "Reconectar". Condicional estrito (não polui quem não usa WhatsApp / nunca
  conectou).
- **Verificação:** `TermoEAvisoTest` (8) — trava (salvar não liga sem aceite, mesmo forjado), aceite
  registra+libera, bump de versão re-exige, aviso caiu/open/nunca-conectou/sem-permissão. Suíte
  **610/610**. Prints (Playwright): termo bloqueando os toggles, aviso de número dedicado.
  **Não dispara mensagem. Sem deploy.**

---

## D81 — WhatsApp Fatia 5: avaliação pós-serviço (link assinado p/ a avaliação D51)
> Envia, **X min após a conclusão** (configurável), uma mensagem com **link** para a tela de avaliação
> que já existe (D51). **Não recebe resposta** no WhatsApp (Fatia 8). Reusa envio (D75), config/
> template (D77), anti-ban/idempotência (D79) e o termo (D80). Ver
> [[WhatsApp (Evolution) no Nextgest]] e [[Últimos serviços (Avaliações)]].
- **Anonimato preservado (auditado):** a avaliação acontece **na web**, onde o anonimato já é forçado
  (o painel esconde o cliente do profissional, D51/D67). O **link** é uma **URL ASSINADA**
  (`URL::temporarySignedRoute('tenant.avaliar', validade, [tenant, agendamento])`, middleware `signed`)
  → **não-adivinhável** (HMAC do APP_KEY), **expira**, e **não expõe dado pessoal** (só o id do
  agendamento + assinatura). Não dá p/ avaliar o atendimento de outro (assinatura de um não vale no
  outro → 403). Página pública `Portal\AvaliacaoPublica` (sem login) **reusa** a criação de `Avaliacao`
  (mesmos campos) — não duplica a avaliação nem o anonimato.
- **Job/comando espelham o D79:** `nextgest:enviar-avaliacoes` (scheduler, a cada minuto) por tenant
  com `avaliacao_pos_servico` **ligada** + **termo aceito (D80)** + **conectado**: acha **concluídos**
  com `data_hora_fim ∈ (now-apos-buffer, now-apos]` (fuso), **não avaliados** e **não pedidos**,
  cliente não opt-out → enfileira o `EnviarAvaliacaoWhatsApp` (espaçado, tetos). SÓ LÊ a agenda.
- **Idempotência:** `pedidos_avaliacao.agendamento_id` único (1 pedido por atendimento). `apos_min`
  editável no card (D77); `janela_buffer_min` evita inundar atendimentos antigos; `link_validade_dias`
  na config.
- **Verificação:** `AvaliacaoPosServicoTest` (10) — link assinado abre (200) / sem assinatura ou de
  outro atendimento → 403 / URL sem dado pessoal; tela cria a `Avaliacao` (anonimato no painel intacto);
  indisponível se já avaliado; janela/idempotência/filtros (não-concluído/opt-out/já-avaliado/sem-termo);
  teto/minuto; job envia o link assinado + marca. Suíte **620/620**. Print (Playwright): página pública
  da avaliação abrindo por link assinado. **Não recebe nada pelo WhatsApp. Sem deploy.**
  > **Validado (28/06/2026):** disparo real ao número de teste (status `enviado`); idempotente.

---

## D82 — WhatsApp Modo Aquecimento: curva de volume para número novo
> Curva de volume **crescente (~21 dias, configurável)** **por cima** das travas anti-ban (D79/D81),
> protegendo o número novo (não-oficial bane com volume). **Não dispara nada novo** — só **modula o
> volume** do que já existe. Ver [[WhatsApp (Evolution) no Nextgest]].
- **Teto efetivo do dia = `min(teto normal, teto da curva do dia)`**, e o consumo do dia passou a ser
  **COMBINADO** (lembrete + avaliação) — o número tem um **orçamento diário único** (fecha de quebra a
  brecha latente do teto separado D79/D81). Tudo no serviço `Services\WhatsApp\Aquecimento`
  (`diaAtual`/`tetoEfetivoDia`/`consumoHoje`/`restanteHoje`/`broadcastLiberado`), consumido pelos
  comandos D79/D81 no lugar do teto fixo.
- **Dia 1 = `whatsapp_config.conectado_em`** (aditivo), capturado no `WhatsAppService::status()` ao
  **entrar** em `open` (transição). Fuso `APP_TIMEZONE`.
- **Troca de número reinicia a curva:** o `status()` busca o `ownerJid` (Evolution
  `/instance/fetchInstances`) na transição; **mudou → reinicia** (`conectado_em=now`, novo
  `numero_conectado`); **mesmo número reconectado → continua**.
- **Broadcast no aquecimento:** `broadcastLiberado()` só a partir de `broadcast_a_partir_dia` (default
  **11**) — política pronta p/ o futuro sender de broadcast (hoje não há sender).
- **Defaults conservadores** (`config('whatsapp.aquecimento')`, override por
  `whatsapp_config.aquecimento`): dias 1–2 **10/dia**, 3–6 **20**, 7–13 **40**, 14–21 **80**, 22+
  **normal**. **Tela "Aquecimento"** (3ª aba, gated, **validada**: 1ª fase ≤ 30, ≤ normal, dias
  crescentes, teto não-decrescente → não dá p/ anular o aquecimento) mostra o dia/teto atual.
- **Verificação:** `AquecimentoTest` (6) — teto sobe pela curva e termina no normal; `min(normal,
  curva)`; broadcast bloqueado até a fase; desligado → sem cap; comando aplica o teto do aquecimento
  (14 elegíveis, dia 1 → **10**, abaixo do per-minute 20); troca de número reinicia / mesmo número
  mantém. Suíte **626/626**. Print: aba Aquecimento (dia 2 · teto 10/dia · broadcast bloqueado).
  **Não dispara nada novo. Sem deploy.**

## D83 — WhatsApp Controle de mensagens: histórico + janela de horário + opt-out
> **Governança** das automações: **log de envios** (metadados + conteúdo, com **expurgo** automático
> do texto), **janela de horário permitido** (global + override por automação) decidida **no servidor**
> (adia/descarta), e **tela de opt-out**. **Não recebe mensagem** (sem webhook — fica para a fatia de
> Conversas). Anonimato **D51 preservado** (envio ≠ avaliação). Ver [[WhatsApp (Evolution) no Nextgest]].
- **Log `mensagens_whatsapp`** (tenant, aditiva): automação, agendamento/cliente (nullOnDelete),
  telefone (metadado), status (`enviado|falhou|descartado`, string — sem enum no DB), motivo, conteúdo
  (expurgável), `conteudo_expurgado_em`, `enviado_em`. Gravado pelos jobs D79/D81 e pelo "testar"
  manual (`automacao=teste`), via `Services\WhatsApp\RegistroMensagem`, que **mascara links** — o
  link **assinado** da avaliação **não** é persistido como credencial viva (`[link]`).
- **Expurgo** (`nextgest:whatsapp-expurgar-conteudo`, diário 03:30): após
  `config('whatsapp.historico.expurgo_dias')` (**90**, `0` desliga) apaga o **conteúdo**, mantém os
  **metadados**. `UPDATE` sempre com `WHERE` (prazo + conteúdo ≠ null).
- **Janela de horário** (`Services\WhatsApp\JanelaEnvio`): resolução **override-automação >
  override-global (`whatsapp_config.janela`) > defaults (`config('whatsapp.janela')`, 08:00–20:00)**.
  `ativa=false` = sem restrição. **Decidida no ENVIO (no job)**, fuso `APP_TIMEZONE`. Fora da janela:
  **lembrete** → **descarta** se o atendimento já teria começado na próxima abertura (log
  `descartado`), senão **adia** (`agendado_para`); **avaliação** (evento já ocorreu) → **sempre adia**.
  O **registro é criado já na 1ª elegibilidade** (claim), então o adiamento **não perde** atendimentos
  quando a janela de elegibilidade móvel da avaliação "anda".
- **Represamento sem mexer no enum:** `lembretes_servico.agendado_para` / `pedidos_avaliacao.agendado_para`
  (aditivo). `enfileirado` + `agendado_para` futuro = **adiado**; os comandos D79/D81 **re-despacham**
  os vencidos (`agendado_para <= now`, status `enfileirado`) **antes** dos novos, dentro do mesmo teto.
  Sem re-dispatch dentro do job → **sem recursão** em fila `sync` (dev) e idêntico em `async` (prod).
- **Telas (3 abas novas, gated `recurso:whatsapp` + `can:gerenciar_whatsapp`):** **Janela** (global +
  override por automação, prévia "aberta agora"), **Histórico** (filtros automação/status/período;
  conteúdo expurgado sinalizado), **Opt-out** (lista + busca p/ marcar/desmarcar `clientes.whatsapp_optout`
  do D79). Total da área WhatsApp = 6 abas (Conexão, Automações, Aquecimento, Janela, Histórico, Opt-out).
- **Anonimato (D51):** o histórico registra **ENVIO** (gated a Dono/Gerente, nunca ao profissional) e
  **não consulta `avaliacoes`** — saber "pedi avaliação ao cliente X" ≠ "o que o X avaliou". Teste prova
  que a nota/comentário **não vaza** no histórico.
- **Verificação:** `ControleMensagensTest` (13) — log enviado/falhou; link mascarado; expurgo (limpa
  conteúdo, mantém metadados); janela (dentro→envia, fora+futuro→adia, fora+evento passou→descarta,
  fuso); avaliação sempre adia; override por automação; re-despacho de represados; opt-out pela tela
  bloqueia/libera; gating 403 (Profissional) × 200 (Dono); anonimato (não vaza a nota). Suíte
  **639/639**. **Não recebe mensagem. Sem deploy.**

## D84 — WhatsApp melhorias de UI/UX da aba
> Só **UI/UX** da área WhatsApp (6 abas). **Lógica intacta** (D75/D77/D79/D81/D82/D83) — comportamento
> funcional idêntico. Reusa o modal `x-ng.confirmar` (D65) e o toast. Ver [[WhatsApp (Evolution) no Nextgest]].
- **Confirmações nativas → modal (D65):** `wire:confirm` do **Desconectar** (Conexão) e o **"voltar a
  enviar"** do Opt-out (re-habilita envio = consentimento) viram `x-ng.confirmar` (Desconectar tom red;
  opt-out tom amber, com o nome do cliente). Sem `confirm()` do navegador na área.
- **Erro de validação → toast + foco:** trait `Concerns\FocaPrimeiroErro` (valida com `Validator::make`;
  ao falhar → `setErrorBag` + `Flux::toast` + `dispatch('wa-erro-validacao')`). Um `@wa-erro-validacao.window`
  (Alpine, no root das telas de form) **rola/foca** o 1º `[data-invalid]` (Flux marca o campo). Usado em
  Automações/Janela/Aquecimento. *(Cada aba é rota/componente própria → o campo inválido está sempre na
  aba atual; não há troca de aba a fazer.)*
- **Indicador de aba ativa (bug — causa raiz):** `_abas` decidia a aba por `request()->routeIs()`, que é
  falso no `/livewire/update` → o destaque **sumia no erro/re-render**. Agora cada tela passa um **literal**
  `@include('_abas', ['ativa' => '...'])`; o destaque **persiste** em erro, re-render e `wire:navigate`, e
  a **inicial (Conexão)** já vem marcada. Abas com **ícone + rótulo** consistentes nas 6.
- **Número de teste persistente por tenant:** coluna aditiva `whatsapp_config.numero_teste` (não-secreta).
  Automações pré-preenche no `mount` e salva no `salvar`/`testar`. Isolado por tenant (config do tenant).
- **Verificação:** `MelhoriasUiTest` (5) — número persiste (salvar/testar) e pré-preenche; validação
  inválida dispara `wa-erro-validacao` + erro e **não salva**; válida não dispara e salva; opt-out
  `confirmarRemocao` → modal e `desmarcar` tira do opt-out. Sem regressão (Automações D77 intacto).
  Suíte **644/644**. Prints: aba marcada (inclusive após erro, com toast+foco), modal no opt-out,
  número persistido. **Só UI. Sem deploy.**

## D85 — WhatsApp: botão "Salvar" por card (aba Automações)
> Só **UX/persistência** da aba Automações (D77). Cada card de automação ganha o **próprio "Salvar"**
> (o global do rodapé continua). Lógica de envio/disparo **intacta**. Ver [[WhatsApp (Evolution) no Nextgest]].
- **`salvarCard($chave)`** persiste **só** aquela automação: faz **merge** na entrada do card em
  `whatsapp_config.automacoes[chave]`, deixando os demais cards e as subchaves intactos. Reusa a trava
  do termo (D80) e o toast+foco (D84); toast "<automação> salvo.".
- **Helper `entradaCard()`** (usado pelo por-card e pelo global): monta ativo/template + o campo extra
  da automação (`antecedencia_min`/`apos_min`) **preservando** o que já existia.
- **Correção de quebra (perda de dado):** o **salvar global** reconstruía o `automacoes` do zero e
  **apagava a janela própria** por automação (D83). Agora ele faz **merge** (via `entradaCard`), então
  salvar tudo de uma vez **não apaga** mais os overrides de janela.
- **Verificação:** `MelhoriasUiTest` (+4) — `salvarCard` persiste só o card e não toca os outros;
  preserva a janela própria (D83); inválido não salva e dispara o foco (D84); o salvar global também
  preserva a janela. Sem regressão (Automações D77). Suíte **648/648**. Prints: cards com "Testar" +
  "Salvar" próprios; toast "Lembrete de serviço salvo.". **Só UI. Sem deploy.**
