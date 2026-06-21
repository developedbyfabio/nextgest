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
