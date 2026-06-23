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
