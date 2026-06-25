---
projeto: Nextgest
tipo: aprendizados
status: vivo
criado: 2026-06-14
tags: [nextgest, aprendizados, livewire, agendamento, gotchas]
---

# Nextgest — Gotchas e Aprendizados do Projeto

> Coisas não óbvias descobertas durante o desenvolvimento. Documento vivo.

## Concorrência no agendamento (1C)
Para impedir dois clientes no mesmo horário, o `Agendador::confirmar()` roda em
transação e faz **lock pessimista na linha do profissional** (`users.id … FOR
UPDATE`) antes de revalidar o slot e inserir.
- Por que travar o profissional e não a tabela `agendamentos`: o conflito é a
  *ausência* de um registro no horário. Travar `agendamentos` não impediria duas
  inserções simultâneas quando ainda não há nenhuma ("inserção fantasma"). Travar
  o profissional serializa as tentativas para aquele profissional.
- O segundo cliente, ao obter o lock, já enxerga o agendamento do primeiro e
  recebe `SlotIndisponivelException`; o portal mostra mensagem e recarrega os
  horários.

## SQLite ignora locks nos testes
Os testes rodam em SQLite (que ignora `FOR UPDATE`), então validam a
**revalidação lógica** sob confirmação. O lock pessimista garante a serialização
real só no **MySQL** (produção). Manter isso em mente ao ler os testes de
concorrência.

## Livewire 4: nome de variável `slots`
Uma variável de view não pode se chamar `slots` — colide com o `SlotProxy` do
Livewire 4. No motor de disponibilidade foi renomeada para `horarios`
(e `horariosRemarcar` na remarcação).

## Livewire 4: mais gotchas (1D)
- Método de componente não pode colidir com reservado do `Component` (ex.:
  `resetExcept`) — renomear.
- `authorize()` vira resposta **403** nos testes; usar `assertForbidden()`, não
  `toThrow()`.
- Remarcação: o motor aceita `ignorarAgendamentoId` em `slots/slotValido/`
  `intervalosOcupados` para o próprio agendamento não contar como conflito ao
  remarcar (mantendo a duração via snapshot).

## Fuso horário
`APP_TIMEZONE=America/Sao_Paulo`; a config passou a ler do `.env` e o phpunit fixa
o fuso nos testes. Fuso por tenant fica como melhoria futura.

## Acessar a VM (VirtualBox) a partir do host
O ambiente roda numa VM (IP `192.168.3.100`). Para abrir no navegador do host:
- `php artisan serve` precisa de `--host=0.0.0.0` (senão escuta só no loopback da VM).
- Abrir a porta: `sudo ufw allow 8000/tcp`.
- O IP da VM precisa estar na lista de **domínios centrais** do stancl (onde
  `127.0.0.1`/`localhost` já estão), senão a rota central e a resolução de tenant
  por caminho quebram ao acessar pelo IP.
- Acessar: `http://192.168.3.100:8000/barbeariateste` (portal) e `/admin/login`.

## Assets (CSS/JS) 404 nas rotas de tenant
**Causa:** `tenancy.filesystem.asset_helper_tenancy = true` deixa o `asset()`
"tenant-aware"; como o `@vite` usa `asset()`, nas rotas `/{slug}` as URLs viravam
`/tenancy/assets/build/...` (404). Nas rotas centrais (sem tenancy inicializado)
saíam normais, por isso só o tenant quebrava.
**Correção:** `asset_helper_tenancy => false` em `config/tenancy.php` (build é
global; para arquivos por tenant usa-se `tenant_asset()`).

## Endpoint do Livewire (`/livewire/update`) 404 — lacuna de teste
**Sintoma:** `POST /livewire/update` retornando 404 em todas as páginas (central
e tenant) derruba logins, modais e formulários — parece "tudo quebrado", mas é
um único defeito (rota do Livewire/cache/processo serve estagnado).
**Lição de teste:** `Livewire::test()` chama o componente direto, sem passar pelo
endpoint HTTP real nem pelo redirect de login no navegador. Por isso a suíte fica
verde com o sistema quebrado. **Sempre ter testes de fumaça HTTP** (GET nas rotas
esperando 200/302, POST real de login seguindo redirect) para pegar 404 global.

## Drivers em dev: cache/sessão/fila NÃO em `database` (tenancy)
A tenancy troca a conexão padrão para o banco do tenant; com `CACHE_STORE`/
`SESSION_DRIVER`/`QUEUE_CONNECTION` em `database`, essas tabelas são procuradas no
banco do tenant (que não as tem) → `tenant_*.cache doesn't exist`. Em dev usar
`file`/`file`/`sync`; em produção, Redis. O `.env` não é versionado → reaplicar em
cada clone. Ver [[Bug - Drivers database dentro do tenant]] e D32.

## MySQL faz commit implícito de DDL → tenant meio-migrado
Se a criação do tenant aborta no meio, as tabelas (`CREATE TABLE`) ficam (DDL não
volta atrás no MySQL), mas o ledger `migrations` e o seed (DML) são revertidos →
schema parcial e ledger desalinhado. Reparo **aditivo**: registrar no ledger as
migrations cujas tabelas já existem, depois `tenants:migrate` + `tenants:seed` (nada
de `migrate:fresh`/DROP). Ver [[Bug - Tenant meio-migrado (ledger)]].

## Flux: `:disabled`, nunca `@disabled`
Em componente Flux (`<flux:button>` etc.) a diretiva Blade `@disabled(cond)` quebra a
compilação; usar binding `:disabled="cond"`. `@disabled` só vale em HTML nativo. Ver
[[Bug - Flux disabled quebra Blade]].

## Migração de servidor: IP novo nos `central_domains`
O projeto saiu da VM `192.168.3.100` para o servidor de dev `192.168.11.210` (Ubuntu
22.04, PHP 8.5). Todo IP/host pelo qual o sistema é acessado precisa estar em
`config/tenancy.php → central_domains`, senão `/admin` e a resolução de tenant por
caminho quebram. (Mesma lição da seção "Acessar a VM" acima, agora para o IP novo.)

## Arquivos por tenant: `Aparencia::urlArquivo()`, não `tenant_asset()`
O `tenant_asset()`/rota de assets do stancl resolvem o tenant por **domínio**;
este projeto é por **caminho**. Para logo/cabeçalho/fundo usa-se a rota própria
`/{tenant}/arquivo/{path}` via `Aparencia::urlArquivo($path)` (D35). Isso **substitui**
o conselho antigo de usar `tenant_asset()` para imagens do tenant.

## Componente compartilhado com cor fixa não segue o tema do tenant
`x-ng.option-card`/`x-ng.empty` usavam `bg-white`/`zinc` e variantes `dark:`. No
portal (que aplica o tema do tenant por CSS vars e **não** usa `.dark`), isso fica
off-brand — e quebra se o dono escolher superfície escura (cartões brancos no
escuro). Passou despercebido porque todos os presets têm superfície branca. Solução:
prop `themed` que ativa `.ng-card-portal` (usa `--cor-superficie`/`--cor-texto`/
`--color-accent`). Ver [[Identidade Visual do Estabelecimento (Tema)]].

## Auditar UI rendendo com tema escuro custom (não só teste verde)
`Livewire::test()` verde não prova boa aparência (não exercita CSS/tema nem HTTP
real). Para auditar UI, renderizar o HTML aplicando uma **superfície escura custom**
expõe cores fixas que o tema padrão (claro) esconde. Ver [[Auditoria de UI (Portal e Painel)]].

## Cancelar/confirmar destrutivo: modal, não `wire:confirm`
O `wire:confirm` usa o confirm **nativo** do browser (feio, fora do design system).
Para ações destrutivas, usar `flux:modal` controlado por estado
(`Flux::modal('nome')->show()/close()`), com o alvo guardado numa propriedade.

## Livewire 4 NÃO auto-esconde `wire:loading` (skeleton preso)
Diferente do Livewire 2/3, o Livewire 4 não injeta `[wire\:loading]{display:none}`: o
diretivo só alterna o `display` inline **durante** a requisição. Um skeleton/spinner
"mostrar-no-loading" nasce **visível** e fica preso. Conserto: regra CSS no `app.css`
que esconde em repouso cada variante usada (`[wire\:loading], [wire\:loading\.delay],
[wire\:loading\.delay\.flex], [wire\:loading\.flex\.delay]{display:none}`) — o `display`
inline do loading vence a regra. O nome do atributo inclui os modificadores, então não
há seletor único; ao criar um combo novo, adicione-o (teste
`WireLoadingSkeletonTest` cobre). `.remove` não entra. Ver
[[Bug - Skeleton de loading preso (wire-loading nao some)]].

## Upload Livewire "Falha no upload" → limite do PHP, não o código
"Falha no upload do arquivo X" no Livewire costuma ser o **PHP barrando** antes do
framework: `upload_max_filesize`/`post_max_size` menores que o arquivo. Em dev o
`php artisan serve` usa o `php.ini` da **CLI**. A regra `max:` da aplicação precisa
**caber** no limite do servidor (aqui `upload_max_filesize=2M` → uploads de 2 MB). Para
mais, subir o limite no php-fpm (prod) ou `php -d ... artisan serve` (dev). Restringir
tipos a `mimes:png,jpg,jpeg,webp` (não `image`, que aceita SVG com script). Ver
[[Bug - Aparencia (upload, fonte e campos desconectados)]].

## Controle de UI que não aplica nada = controle que mente
Depois de uma decisão de arquitetura (ex.: D36, superfícies viram claro/escuro), campos
antigos da tela podem continuar visíveis **sem efeito** (cores de superfície, posição de
menu, estilo de ícone na Aparência). Parecem configurar o app e não mudam nada. Ao mexer
na tela, **alinhar ao modelo**: remover/explicitar o que não aplica — não basta
"consertar" o que está visível. Ver [[Identidade Visual do Estabelecimento (Tema)]].

## Fonte do tenant: Google Fonts sob demanda no `<head>`
A tipografia faz parte da marca (D36) e é emitida como `font-family` no `<body>` por
`cssVarsAcento()`. Mas a fonte só **renderiza** se a folha estiver carregada: o
`Aparencia::linkFonteGoogle()` injeta o `<link>` da fonte (Google) escolhida no `<head>`
dos layouts; o editor carrega **todas** (`linksFontesGoogle()`) para a prévia ao vivo.
Lista fechada (`Aparencia::FONTES`) → sem injeção; `href` escapada.

## Livewire + stancl: endpoints de arquivo precisam de tenancy ANTES do throttle
O `upload-file`/`preview-file` do Livewire são globais (grupo `web`, COM sessão, SEM
tenancy — não passam pelo persistent middleware do `/update`). O `ThrottleRequests`
chama `$request->user()` p/ a chave de rate-limit; logado no painel, o usuário do tenant
é procurado no banco CENTRAL (`nextgest_central.users` não existe) → **500** ("Falha no
upload"). Pegadinha dupla: (1) precisa um middleware que inicialize a tenancy nesses
endpoints (por `_tenant_sessao` na sessão, fallback Referer); (2) o Laravel ordena por
PRIORIDADE e puxa o `ThrottleRequests` para frente — use
`$middleware->prependToPriorityList(before: ThrottleRequests::class, prepend: ...)` p/
garantir tenancy ANTES do throttle. Reproduzir **logado** (deslogado dá 200 e esconde o
bug). Ver [[Bug - Upload 500 (usuario do tenant resolvido no banco central)]].

## `php artisan serve`: `-d ini` NÃO chega ao servidor; use PHP_INI_SCAN_DIR
`php -d upload_max_filesize=6M artisan serve` não eleva o limite: o `serve` lança um
`php -S` FILHO que ignora o `-d` (segue no php.ini da CLI, ~2 MB; uploads maiores dão um
302 de redirect). O que funciona em dev:
`PHP_INI_SCAN_DIR=":/dir/com/uploads.ini" php artisan serve ...` (o filho honra o scan
dir). Em produção (php-fpm), ajustar o php.ini/pool. A validação do app é 5 MB
(`max:5120`), mas o teto efetivo é o do PHP.

## Livewire + stancl: disco de upload TEMPORÁRIO precisa ser central (senão 500)
O endpoint global `/livewire/upload-file` roda **sem** tenancy (só `web`, sem
`{tenant}`); o `/update`/`salvar` rodam **com** tenancy. Se o disco temp for `local`
(que está em `tenancy.filesystem.disks` → suffixado), o arquivo gravado no upload
(central) "some" ao ser lido no /update (disco do tenant) → `UnableToRetrieveMetadata`
→ **500**. Correção: disco temp **dedicado e central**, FORA de
`tenancy.filesystem.disks` (`config/livewire.php` → `temporary_file_upload.disk`). O
arquivo final, esse sim, vai no disco `public` do tenant. Ver
[[Bug - Upload 500 (disco temp do Livewire x tenancy)]].

## Tamanho base da tipografia vai no `<html>` (rem), não só no `<body>`
Os utilitários de tamanho do Tailwind (`text-sm`…) são **rem** (relativos ao `<html>`).
Pôr o `font-size` base só no `<body>` (como o `cssVarsAcento` fazia) não escala a UI —
o "tamanho base" parecia não funcionar. Correção: emitir `font-size` no `<html>` dos
layouts (portal/painel/auth do tenant) → escala tudo. Ver
[[Identidade Visual do Estabelecimento (Tema)]].

## Flux/Blade: prop `value` com `value="{{ ... }}"` = escape DUPLO
`flux:select.option` (e afins) recebe `value` como **prop** e renderiza `value="{{ $value }}"`
(escapa). Se a view passa `value="{{ $valor }}"`, o Blade já escapa antes → o `'` vira
`&#039;`, o Flux escapa de novo o `&` → `&amp;#039;`. O navegador envia o valor **errado**
(com `&#039;` literal) e um `Rule::in` rejeita a própria opção da tela. Sintoma real: fontes
com aspas na stack (Open Sans, Poppins…) davam "campo fonte não contém valor válido"; as sem
aspas funcionavam. Correção: **binding** (`:value="$valor"`, `:style="..."`) → escape único.
Ver [[Bug - Fonte rejeitada na Aparencia (escape duplo do option)]].

## Flux: alternar tema = `x-model="$flux.appearance"` (bind direto, não cópia local)
O dark-mode do Flux é aplicado por um `Alpine.effect(() => applyAppearance($flux.appearance))`:
só dispara quando **`$flux.appearance`** (objeto reativo global) muda. Ligar o seletor a uma
**cópia local** (`x-data="{ appearance: $flux.appearance }" x-model="appearance"`) só lê o
valor inicial; os rádios escrevem na cópia, o effect nunca roda → **seletor inerte** (tema
preso). Correção: `x-data x-model="$flux.appearance"` (bind direto no magic). Vale para
qualquer toggle de tema. Ver [[Bug - Seletor de tema nao alterna (x-model local)]].

## Dark-mode do sistema sobre superfície clara forçada (texto invisível)
**Sintoma:** no portal, o nome do serviço/profissional sumia, mas a duração
(cinza-médio) aparecia.
**Causa:** `@fluxAppearance` aplica `.dark` no `<html>` conforme o sistema do
visitante; o portal forçava superfície clara sem variantes dark, então os cards
viravam escuros (`dark:bg-zinc-900`) e o texto sem cor própria herdava
`text-zinc-900` (escuro) — escuro no escuro. O cinza-médio sobrevivia.
**Correção:** o portal **não** segue o modo do sistema do visitante; reflete o tema
do estabelecimento (CSS vars). Sem `.dark`, cards claros e nomes herdam
`--cor-texto` com contraste correto. Lição: todo texto precisa de cor explícita
ou herdar de um token controlado — nunca depender do default do tema do browser.

## Livewire: `reset`/`resetValidation`/`mount` NÃO são ações públicas do frontend
**Sintoma:** clicar Cancelar/fechar um modal estourava 500
`MethodNotFoundException: Public method [reset] not found`.
**Causa:** `@close="$wire.reset(...)"` (ou `wire:click="reset"`). Tudo que o frontend
chama (`wire:click`, `@close`, `$wire.x()`) tem que ser um método **público próprio**;
`reset()` e amigos são internos (`protected`, herdados de `Livewire\Component`), então o
Livewire não acha um método público `reset` e estoura. **Correção:** criar um método
público (`limparFormulario()`) que internamente faça `$this->reset([...])` e apontar o
gatilho para ele. Ver [[Bug - Alterar senha 500 (reset nao e acao publica do Livewire)]].

## stancl: metadados do tenant vão no `data` como atributo virtual (não invente coluna)
O `Tenant` do stancl guarda em colunas reais só o que está em `getCustomColumns()`
(`id`, `nome`, `slug`, `ativo`); **qualquer outro atributo cai no JSON `data`** (via
VirtualColumn) e é lido transparente como `$tenant->x`. Já usamos isso para `segmento` —
e agora para `recursos` (array de feature flags). **Padrão:** leia/escreva o atributo
virtual (`$tenant->recursos = [...]; $tenant->save()`); **NUNCA** reatribua `$tenant->data`
inteiro (apaga os outros metadados que dividem o mesmo JSON). Não precisa de cast nem de
migração — um array sob a chave faz round-trip nativo (o `data` é cast `array`). Ver
[[Recursos por Tenant (Feature Flags)]] e [[Decisões de Arquitetura#D37]].

## Auditoria: checar MIGRATIONS, não só models, antes de criar tabela
Na Fase A do Clube, a T0 concluiu "não há clube" olhando `app/Models` (não havia models) e as
migrations recentes (`ls | tail`). Mas as tabelas (`planos_clube`, `assinaturas_clube`, …) **já
existiam** desde uma migração antiga (`190003`), com schema próprio (status `enum`, não `string`).
Criar uma migração nova com `Schema::create` cego teria dado "table already exists" (e o pior:
schema divergente em tenant novo, onde as duas migrações rodam). **Lições:** (1) auditar
`database/migrations/**` e o `EXPLAIN`/`SHOW COLUMNS` real, não só os models; (2) toda migração de
tabela que pode já existir usa **`Schema::hasTable` guard** (reparo aditivo, sem DROP); (3) alinhar
models/enum ao schema REAL (aqui: status `ativa|suspensa|cancelada|inadimplente`, sem `pendente`).
Ver [[Clube de Assinatura (Fase A)]].

## Componente CENTRAL com `$tenant->run()`: dois run() no mesmo processo podem purgar a conexão
Em componente do `/admin` que entra no tenant por **`$tenant->run()`** (ex.: `TenantDetalhe`),
quando o **mesmo processo** faz dois `run()` em sequência — um que **escreve** e outro que
**renderiza** logo depois — pode estourar `Database connection [tenant] not configured` (o
primeiro `run()` encerra a tenancy e purga a conexão `tenant`; o segundo tenta reabrir e, no
**sqlite de teste**/sessão longa de **tinker** com cache stale do spatie, falha). **Produção é
segura:** cada request faz só **um** `run()` (load = render; ação = ação + redirect). Padrões:
(1) ação que escreve e redireciona usa **`skipRender()`** (evita o render-run logo após);
(2) em **teste**, manter `tenancy()->initialize()` aberto (sem `end()`) faz o `run()` interno
**restaurar** o tenant (sem purge). Diagnosticado no reset de 2FA (ver [[2FA (TOTP) do Dono]]).

## stancl em testes: `initialize()` mantém o tenant STALE entre requests no mesmo processo
`Tenancy::initialize()` tem early-return: se já está inicializado com o **mesmo** tenant
key, **não troca a instância**. Em produção não importa (cada request é um processo novo,
resolve fresh). Mas num teste com **dois `$this->get()` para o mesmo tenant**, o tenancy
do 1º request continua vivo e o 2º reaproveita a instância **stale** — então alterar o
tenant no banco entre os dois requests **não é visto** (ex.: ligar uma flag e bater de
novo continuava "desligado"). **Lição/efeito:** teste de toggle por HTTP deve usar **um
request por teste** (cada teste = ciclo isolado, fiel à produção), não ligar/desligar no
mesmo teste. (Sintoma diagnosticado na Fase 0a do middleware `recurso:{slug}`.)

## flux:sidebar: `collapsible` já faz tudo; `min-h-0` p/ scroll só na lista; `persist`=localStorage
Ao mexer no shell do painel (D45), três coisas a lembrar do `flux:sidebar`:
- **Não reinvente o colapso.** A prop **`collapsible`** (não o `stashable` antigo) já entrega
  desktop=faixa de ícones (`w-14`) **e** mobile=drawer com backdrop. Um único `flux:sidebar.toggle`
  serve aos dois: o evento `flux-sidebar-toggle` decide pelo viewport. O conteúdo ao lado alarga
  sozinho (grid `min-content`). Construir isso à mão em Alpine é trabalho jogado fora.
- **Scroll só nos itens = `min-h-0` no filho que rola.** Sidebar flex-col de altura cheia, header e
  rodapé `shrink-0`, e a navlist `flex-1 min-h-0 overflow-y-auto`. **Sem `min-h-0`** o flex child não
  encolhe abaixo do conteúdo e o overflow vaza pro container → **rolagem dupla**. Erro clássico.
- **Estado recolhido só vai pro `localStorage` se a prop `persist` estiver ligada** (o stub do
  componente nem expõe `persist`). Logo, sem `persist`, **nada de localStorage** (cumpre a regra do
  projeto) e o estado é por sessão de navegação. Persistir entre páginas sem localStorage não é
  trivial aqui (`wire:navigate` reseta; `@persist` congelaria o realce do item ativo). Aceitável.
- **Cor de acento sem cor nova:** `style="color: var(--cor-principal)"` no toggle (D36). O estado
  ativo dos itens já segue `--color-accent`, que `Aparencia::cssVarsAcento` aponta p/ a cor da marca.

## Item de TOPO numa `flux:sidebar` colapsável precisa ser `flux:sidebar.item`, não `flux:navlist.item`
Dentro de uma `flux:sidebar collapsible`, item solto de topo (ex.: "Início") **tem** de ser
`flux:sidebar.item`. Só ele carrega as classes do estado **recolhido**
(`in-data-flux-sidebar-collapsed-desktop:w-10` + `justify-center` + oculta-texto + tooltip). O
`flux:navlist.item` **não tem nenhuma** dessas — na faixa de ícones (`w-14/px-2`) ele continua
`w-full px-3` com o texto visível e fica **"espremido"/desalinhado** (foi o sintoma real, D46). Os
itens **dentro** de `flux:sidebar.group` podem seguir `navlist.item`: ao recolher, o grupo **troca o
container inteiro** por um ícone + dropdown (o ramo de disclosure é escondido), então o filho nunca
aparece "cru" na faixa. Diagnóstico veio de **ler a fonte dos stubs** do Flux
(`vendor/livewire/flux/stubs/.../sidebar/item.blade.php` × `.../navlist/item.blade.php`), não de supor.

## Recorte de imagem no cliente: Cropper.js empacotado via Vite → blob → `$wire.upload`
Foto de perfil (D46) recorta no navegador e sobe pelo Livewire sem caminho de upload novo:
`getCroppedCanvas({width:512,height:512})` → `canvas.toBlob(...)` → `new File([blob],'x.png')` →
`this.$wire.upload('foto', file, finishCb)` e, no `finishCb`, `this.$wire.salvar()`. **Pinar Cropper.js
v1** (`cropperjs@^1.6.2`): a API `getCroppedCanvas` é da v1; a v2 é outra (web component). Importar o
CSS no `app.js` (`import 'cropperjs/dist/cropper.css'`) — o Vite extrai num asset próprio (`@vite` no
layout linka sozinho). `[x-cloak]{display:none!important}` no `app.css` evita o flash do palco antes do
Alpine montar. O `$wire` dentro de `Alpine.data` funciona porque o `x-data` está **dentro** do DOM do
componente Livewire. Avatar do rodapé é renderizado **no layout** (fora do componente) → após salvar,
**reload** (`navigate:false`, padrão da Aparência) para refletir; reativo exigiria mover o avatar pra
um componente.

## Prévia "fiel" que simula mobile/desktop → CONTAINER QUERY, não `lg:` (viewport)
Para a prévia da Aparência renderizar **o mesmo** componente do login real (`x-portal.auth`) e ainda
**simular** mobile vs desktop mudando só a **largura** da moldura, o componente reflui por
**container query** (`@container` + `@3xl:`), não por breakpoint de viewport (`lg:`).
- Por quê: `lg:` reage à **viewport** do navegador. Se o login (2-col) ficasse dentro da moldura
  estreita da prévia, num navegador desktop (viewport ≥ lg) ele renderizaria 2-col **dentro** da
  moldura de celular → quebrado. Com `@container`, o reflow segue a largura do **próprio shell**: no
  login real o container é a viewport (funciona como `lg:` faria); na prévia o container é a moldura, e
  o toggle celular/desktop só muda essa largura. Sem user-agent sniffing. Tailwind v4 tem `@container`
  nativo (já havia uso no projeto).
- **Altura cheia num grid responsivo:** `min-h-full`/`h-full` num filho de flex **não** resolve de
  forma confiável (o pai flex não tem altura "explícita") → o fundo do root vaza embaixo. Solução:
  root `flex flex-col` + grid `flex-1 grid-rows-1` (a única linha `1fr` preenche a altura → as colunas
  ocupam 100% e nada vaza). O painel de marca é `hidden @3xl:flex`, então no estreito só existe a
  coluna do formulário (1 item, 1 linha 1fr) — sem linha implícita sobrando.
- **Desktop na prévia (coluna estreita do editor):** a moldura larga (50rem) é exibida com
  `transform: scale()` para **caber** na coluna; o `transform` **não** altera a largura de layout, então
  o container query continua lendo 50rem e o login realmente vira 2-col. A escala é medida em JS
  (largura do palco ÷ 50rem) no `x-init`/`@resize`. As telas do portal (não-login) ficam `max-w-md`
  centralizadas mesmo no desktop — fiel ao portal real (mobile-first sempre).
- **Real vs maquete no mesmo componente:** o corpo do formulário entra por **slot** — `flux:input`
  reais no login/registro; campos estáticos (`--cor-*`) na prévia (o dark da prévia é `.ng-previa.is-dark`,
  não a classe `.dark` do Flux, então `flux:input` real não acompanharia o toggle). Mesmo padrão do
  `x-portal.tela-inicio`.

## Build NÃO pode depender de rede externa → fontes locais (@fontsource), não bunny()
No deploy de produção (D48) o `npm run build` deu **`ECONNRESET`**: o `vite.config.js` usava
`bunny('Instrument Sans', { weights: [400,500,600] })` (`laravel-vite-plugin/fonts`), que **baixa a
fonte de `fonts.bunny.net` durante o build**. Build que depende de rede externa é frágil (firewall,
rede instável, CI offline). Correção: **empacotar a fonte localmente** com `@fontsource`.
- **Pacote ESTÁTICO, não o `-variable`:** usar `@fontsource/instrument-sans` (família registrada =
  exatamente **`Instrument Sans`**), e **não** `@fontsource-variable/instrument-sans` (família
  `Instrument Sans Variable`). Motivo: o nome `'Instrument Sans'` está embutido em **dados de tenant**
  (`configuracoes.aparencia.fonte` + catálogo `Aparencia::FONTES/PADRAO/TEMPLATES`); o `-variable`
  obrigaria a **renomear a família nos dados** (mexer em banco) para o visual não cair no fallback.
  Com o estático, **nada de font-family muda** — `--font-sans` (app.css) e o `fonte` dos tenants
  seguem batendo. Aparência **idêntica**.
- **Como:** `npm install @fontsource/instrument-sans` + no topo do `app.css`
  `@import '@fontsource/instrument-sans/{400,500,600}.css';` + **remover** o `import { bunny }` e o
  bloco `fonts: [...]` do `vite.config.js`. O `@font-face` passa a servir woff2 **do próprio app**
  (`/build/assets/...`), sem host externo.
- **Prova:** `unshare -n npm run build` (namespace de rede **isolado** = "Network unreachable") →
  build **conclui**. `document.fonts.check('400|500|600 16px "Instrument Sans"')` = true no portal e
  no painel. Em produção, o `npm ci` instala o `@fontsource` pelo `package-lock.json` (sem rede no build).

## ⚠️ CRÍTICO: config CACHEADO faz `php artisan test` rodar contra o MySQL de DEV (zera o central)
Sintoma que levou ao incidente: `php artisan test` começou a falhar com
`Tenant cannot be created. Reason: Database tenant_lojaum already exists` e, ao checar, o **banco
central de dev estava VAZIO** (`tenants`/`admins`/`estabelecimentos`/`assinaturas`/`faturas` = 0).
- **Causa:** existia `bootstrap/cache/config.php` (alguém rodou `php artisan config:cache`/`optimize`).
  Com o config CACHEADO, o Laravel **ignora o `<env DB_CONNECTION=sqlite>` do `phpunit.xml`** (o cache
  já tem `mysql` resolvido e não relê env). Então `php artisan test` usou o **MySQL de dev** como
  conexão central, e o `RefreshDatabase` rodou **`migrate:fresh`** → **dropou e recriou vazias** todas
  as tabelas do `nextgest_central`.
- **O que sobrevive:** os bancos `tenant_*` (ex.: `tenant_barbeariateste`) **não** são tocados pelo
  `migrate:fresh` do central — os dados internos de cada tenant (users/clientes/agendamentos) ficam
  intactos. Perde-se só o **registro central** (lista de tenants + admin + cobrança).
- **Prevenção (regra dura):**
  1. **NUNCA** manter `config:cache`/`optimize` em DEV. Se for testar build de prod, rodar
     `php artisan optimize:clear` **antes** de qualquer `php artisan test`.
  2. Antes de `php artisan test`, garantir que **não** há `bootstrap/cache/config.php`
     (`php artisan config:clear`).
  3. Idealmente, um **guard** no `tests/TestCase`/`Pest.php` que **aborta** a suíte se a conexão
     central não for sqlite (`config('database.default') !== 'sqlite'` → `throw`), para o teste
     **nunca** tocar MySQL mesmo com config cacheado.
- **Recuperação:** como os `tenant_*` sobrevivem, dá pra **re-registrar** os tenants no central
  (INSERT em `tenants` — sem `Tenant::create`, que tentaria recriar o banco já existente), recriar o
  super-admin (`php artisan` de criação) e re-provisionar assinaturas (comando idempotente). O `data`
  (segmento/recursos/plano) e `estabelecimentos`/`faturas` são reconstruídos por melhor-esforço/UI.
