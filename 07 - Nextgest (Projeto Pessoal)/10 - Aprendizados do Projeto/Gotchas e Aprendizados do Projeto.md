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
