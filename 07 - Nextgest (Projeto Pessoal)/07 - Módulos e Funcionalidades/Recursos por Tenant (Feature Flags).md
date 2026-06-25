# Recursos por Tenant (Feature Flags "à la carte")

> Projeto: [[Nextgest - Visão Geral]] · Fase **0a** · Ver decisão [[Decisões de Arquitetura#D37]]

O super-admin liga/desliga **módulos por estabelecimento** (clube, whatsapp, gateway).
A flag mora no **banco central** (registro do tenant), e o código consulta isso. **Não há
`.env` por tenant** — esse é o ponto. As credenciais criptografadas dos gateways (token
Mercado Pago etc.) são da **Fase 0b**, fora daqui.

## Onde a flag mora
- Tabela central `tenants`, dentro do JSON **`data`** do stancl, chave **`recursos`** =
  array de slugs ligados (ex.: `["whatsapp"]`). **Sem migração** (o `data` já é usado
  pelo `segmento`). Default: **tudo desligado** (chave ausente → `[]`).

## Fonte única
- `App\Enums\Recurso` (backed enum): `clube`, `whatsapp`, `gateway`. Métodos:
  `rotulo()`/`descricao()` (UI), `valores()` (slugs válidos), `valido()` (sem lançar).
  Para criar um recurso novo, **adicione um case aqui** — é a única lista.

## Como consultar
- Helper global **`tenant_tem_recurso('whatsapp'): bool`** (em `app/Support/helpers.php`,
  registrado no `composer.json` → `autoload.files`). Resolve o tenant do contexto atual.
- Métodos no model: `App\Models\Tenant::temRecurso($slug)` e `recursosAtivos()`.
- **Robustez (de graça):**
  - sem contexto de tenant (ex.: central) → `false`, **não lança**;
  - slug desconhecido (fora do enum) → `false` + aviso no log;
  - `recursosAtivos()` é o **ponto único de leitura**: normaliza o `data` e intersecta
    com os valores do enum (descarta `null`/lixo/slug morto). Dado antigo/estranho nunca
    quebra nem liga recurso.

## Como ligar/desligar (admin)
- Tela **Editar** do estabelecimento (`App\Livewire\Admin\TenantDetalhe`,
  `/admin/estabelecimentos/{tenantId}`). Desde D55, os recursos vêm de **duas vias**:
  - **Plano** (seção no topo): aplicar um plano redefine os recursos para o padrão do plano.
    Ver [[Planos (catálogo e aplicação)]].
  - **"Ajuste fino de recursos"** (a antiga seção "Recursos"): `flux:switch` por recurso +
    **"Salvar recursos"** (ação explícita, guard `admin`) para um ligar/desligar pontual,
    independente do plano. **Atenção:** trocar o plano redefine tudo de volta ao padrão do plano.
- **CRÍTICO:** a escrita grava **só os atributos virtuais** (`$tenant->recursos = [...]`,
  `$tenant->plano = '...'`), recarregando o tenant completo antes do `save()`. Nunca reatribuir
  `$tenant->data` inteiro — isso apagaria o `segmento`/`plano` (todos dividem o mesmo JSON `data`).

## Gating (esconder/bloquear recurso desligado)
- **Rota:** middleware `recurso:{slug}` (`App\Http\Middleware\VerificaRecurso`, alias em
  `bootstrap/app.php`). Recurso off → **404**. Ex.:
  ```php
  Route::get('whatsapp/lembretes', ...)->middleware(['...', 'recurso:whatsapp']);
  ```
- **Blade:** diretiva `@recurso('whatsapp') ... @endrecurso` (registrada no
  `AppServiceProvider`, reusa o mesmo helper) para esconder blocos/menu.

## Convenção (vale a partir de agora)
Todo recurso futuro **nasce embrulhado na sua flag**: rota com `recurso:{slug}` +
blocos Blade com `@recurso(...)`. Assim "recurso desligado nem aparece" passa a valer
automaticamente. Nesta fase **não há UI/menu novo** — clube/whatsapp/gateway ainda não
têm tela; entregamos só o **mecanismo + a convenção** e as flags já criadas, prontas para
quando cada módulo for construído.

## Custo / infra
Roda 100% no dev: sem VPS, DNS ou custo. Ter `gateway`/`whatsapp` já como flags **prepara
o terreno** para a Fase 1 (VPS, possivelmente com budget). VPS, Redis, fila, cron, SSL e
sandbox de gateway ficam fora desta fase.

## Testes
`tests/Feature/Admin/RecursosTenantTest.php` — efeito, não existência:
- admin liga recurso → persiste no central → isolado por tenant (helper no contexto de
  cada um);
- estabelecimento novo nasce com tudo desligado;
- **liga recurso preserva o `segmento`** (mesmo `data`);
- helper sem contexto → `false`; chave desconhecida → `false` + log;
- leitura normaliza lixo/slug morto;
- middleware `recurso:whatsapp` por **HTTP autenticado no contexto do tenant**: off →
  404, on → 200 (rota de prova registrada só no teste). Sobre o porquê de dois testes
  separados em vez de toggle no mesmo, ver [[Gotchas e Aprendizados do Projeto]].
