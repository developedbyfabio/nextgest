# Performance (auditoria)

> Projeto: [[Nextgest - Visão Geral]] · Auditoria (medir + reportar) · **Otimizações NÃO aplicadas** — plano aguarda aprovação.

## Método (honesto)
No dev (cache/sessão em arquivo, fila `sync`, `artisan serve` sem opcache) **latência
absoluta não transfere**. A métrica é **contagem e forma das queries**. Anti-ilusão:
tenant de **volume** dedicado (`nextgest:semear-volume`, fixture própria — não toca demo)
com 3.000 clientes, 20.000 agendamentos, 8.000 vendas, 10 profissionais. Se a contagem
**cresce com as linhas/cardinalidade → N+1**; contagem **constante** = saudável.
Testes permanentes: `tests/Feature/Performance/ContagemQueriesTest.php`.

## Tabela de achados

| ID | Área / endpoint | Tipo | Severidade | Evidência (contagem) | Teste |
|----|------------------|------|-----------|----------------------|-------|
| **PERF-001** | `MotorDisponibilidade::slots` (sem preferência) | N+1 (sobre profissionais) | **alta** | **3 queries por profissional** (HorarioTrabalho + Agendamento + Bloqueio em loop). Medido no volume: **35 q/dia (10 prof)**, **225 q/semana**. Profissional FIXO: 8 q (constante) | `ContagemQueriesTest` (`skip` PERF-001) |
| **PERF-002** ✅ APLICADA | `agendamentos.data_hora_inicio` | índice (CREATE) | média | Índice simples adicionado. EXPLAIN (volume 20k): agregação por data `index`→**`range`**, key `agendamentos_data_hora_inicio_index`, linhas **20196→1444** | `ContagemQueriesTest` (existência) |
| **PERF-003** ✅ APLICADA | `vendas.data` | índice (CREATE) | média | Índice simples adicionado. EXPLAIN (volume 8k): lista default `ALL`+filesort→**`index`** backward (12); faturamento `ALL`→**`range`** (668); key `vendas_data_index` | `ContagemQueriesTest` (existência) |
| **PERF-004** | busca de `clientes` (nome/telefone) | índice / forma | baixa | `like '%x%'` (curinga à esquerda) não usa B-tree; contagem ok (1 q), latência a escala. Índice ajuda só prefixo; full-text é Fase futura | — |
| **PERF-005** | imagens do portal (logo/cabeçalho/fundo) | imagem/render | baixa-média | Servidas **cruas até 5 MB** via `TenantArquivoController` (`response()->file`), sem resize/otimização nem `Cache-Control` longo. 5 MB/visita é lento independente de infra | — |
| **PERF-006** | `Bloqueios\Index` | paginação | baixa | `Bloqueio::with('user')->orderByDesc('inicio')->get()` — lista **todos** os bloqueios históricos (sem paginação). Cresce com o tempo (volume baixo) | — |
| (info) | `users.e_profissional` | índice | baixíssima | Sem índice; mas `users` é pequeno (equipe) → ganho irrelevante | — |

## Comprovadamente eficiente (com teste/medição)
- **Sem lazy-loading (N+1 por relação): ZERO.** A suíte inteira (328 testes) roda com
  `Model::preventLazyLoading()` ligado **sem nenhuma `LazyLoadingViolation`** — os eager-loads
  (`with([...])`) cobrem todos os caminhos exercitados (agenda, portal, vendas, kanban, produtos).
- **Dashboard:** 16 métricas = **19 queries CONSTANTES** contra 20k agendamentos (agregados
  count/sum/groupBy; `linhaDoTempo` memoizada; `profissionaisDesempenho` = 2 q, não N+1).
  Teste: ≤ 25.
- **Listagens com eager + paginação:** Vendas **4 q** (paginada 12), Agenda semana **5 q** —
  constantes para qualquer nº de linhas. Testes: ≤ 6 e ≤ 8.
- **Motor com profissional fixo:** **8 q** constante. Teste: ≤ 10.
- **Caches de padrão (independem do driver):** spatie permission cache **ativo** — 1ª `can()`
  = 4 q, **40 chamadas seguintes = 0 q**; `tenant_tem_recurso()` x40 = **0 q** (tenant já em
  memória; `recursos` no atributo decodificado — não reconsulta o central).
- **Sem `wire:poll`** em lugar nenhum (nenhum polling martelando o servidor).

## MotorDisponibilidade — diagnóstico (foco)
- **Estrutura:** ~5 queries de setup + **3 por profissional** no laço (janelas, agendamentos
  ocupantes, bloqueios). As queries por profissional são **escopadas por dia** → não crescem
  com o histórico (20k agendamentos), só com **nº de profissionais × dias**.
- **Impacto:** caminho mais quente do cliente. Salão com 10 profissionais montando uma semana =
  **225 queries**. Severidade **alta** pela frequência, ainda que não cresça com o histórico.
- **Fix proposto (núcleo — só com aprovação):** carregar em lote (`whereIn user_id`) as janelas,
  os agendamentos ocupantes e os bloqueios de **todos** os profissionais do dia (3 queries fixas),
  e agrupar em memória. Levaria 1 dia para ~5-6 queries constantes. **Não mexer agora** (núcleo
  verde; refactor cuidadoso depois do ok).

## PERF-002/003 — aplicados (migração aditiva de tenant)
Migração `database/migrations/tenant/2026_06_22_130001_add_indices_performance_agendamentos_vendas.php`
(somente CREATE INDEX): `agendamentos.data_hora_inicio` e `vendas.data`. Aplicada com
`php artisan tenants:migrate` (barbeariateste, salaoteste, volumeteste). EXPLAIN antes×depois
(volume): todas as queries-alvo deixaram de fazer full scan/filesort e passaram a usar o
índice (`range`/backward index scan), com queda grande de linhas varridas. Contagem de queries
**inalterada** (índice muda o plano, não a contagem); suíte verde; comportamento idêntico
(dashboard e vendas carregam normal).
> Definição: índices SIMPLES nas colunas de data — servem o caso padrão (ordenação por data
> sem filtro) e os ranges do dashboard. Compostos `(unidade_id, status, data)` seriam ótimos
> para o caso filtrado+ordenado, mas o simples já elimina os full scans/filesort dos caminhos
> medidos; fica como refino futuro se o EXPLAIN do caso filtrado pedir.

## Plano restante (severidade × esforço — aguarda ok)
1. **PERF-001 (motor N+1, médio esforço, NÚCLEO):** batch `whereIn`. Maior ganho de contagem.
   Tratar como passo dedicado com testes de regressão do agendamento.
3. **PERF-006 (paginação, baixo):** paginar/limitar `Bloqueios`.
4. **PERF-005 (imagem, médio):** resize/otimização no upload (ou na exibição) + `Cache-Control`.
5. **PERF-004 (busca, baixo):** índice de prefixo em `clientes` (ou full-text na Fase futura).

## Checklist de PRODUÇÃO (Fase 1 — não verificável no dev)
- **opcache** (php-fpm) — ganho grande, só em produção.
- **Redis** para cache/sessão/fila (hoje file/sync), chaves isoladas por tenant.
- **Workers de fila** (tirar trabalho lento do `sync`).
- **`config:cache` / `route:cache` / `view:cache` / `event:cache`** no deploy.
- **Build de produção do Vite** + headers de cache de asset / CDN.
- **MySQL tuning** (buffer pool) + revisar os índices propostos sob dado real.
- **Benchmark de latência sob concorrência** (o veredito "roda liso") — aqui.
- **CREATE INDEX em tabela GRANDE (produção):** em dev/volume foi instantâneo (~90-200ms),
  mas em tabela grande de produção o `tenants:migrate` que adiciona índice deve considerar
  **online DDL** (MySQL/InnoDB faz `ADD INDEX` inplace, sem bloquear escrita na maioria dos
  casos) e/ou **janela de manutenção** por tenant — não assumir que será instantâneo lá.

## Ferramenta
`php artisan nextgest:semear-volume {tenant} --clientes= --agendamentos= --profissionais= --vendas=`
— popula um tenant de volume (sob demanda) para remedir. Usar tenant descartável (ex.:
`volumeteste`), nunca demo/produção.
