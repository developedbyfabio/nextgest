# Clientes (CRM)

Módulo de relacionamento com o cliente final do estabelecimento. Construído em **fatias**;
esta nota cobre a **Fatia 1** (só leitura) e lista o roadmap das próximas.

> Decisão de arquitetura: [[Decisões de Arquitetura#D87 — Clientes (CRM) Fatia 1: aba "Clientes" em Gestão (só leitura)|D87]].
> Relacionados: [[Indicadores — motor (Fase I)]], [[Indicadores — aba (Fase II)]],
> [[Clube de Assinatura (Fase A)]], [[Papéis e Permissões (RBAC)]],
> [[WhatsApp (Evolution) no Nextgest]].

## Fatia 1 — a tela (listar / ver / buscar / filtrar) — ENTREGUE

Aba **"Clientes"** no grupo **Gestão** (rota `painel.clientes`). **Só visualização**: nenhuma ação
(editar / resetar senha / WhatsApp / campanha) nesta fatia.

### Acesso
- Gate por permissão **`ver_clientes`** (`can()`, nunca `hasRole`): **Dono, Gerente e Recepção**.
  Profissional **não** acessa. (A Recepção já tinha a permissão e lida com clientes no balcão.)
- Item de menu em Gestão protegido por `@can('ver_clientes')`; rota com `middleware('can:ver_clientes')`.

### O que mostra
- Tabela **paginada** (15/pág): **nome** (+ e-mail), **telefone**, **última visita**, **selo Clube**.
- **Última visita = último atendimento CONCLUÍDO** (`agendamentos.status='concluido'`,
  `MAX(data_hora_inicio)`) — mesmo critério da agenda (D70). "Nunca veio" quando não há concluído.
  Badge colorido por recência (≤30 verde / ≤90 âmbar / +90 vermelho).
- **Selo "Assinante"** = titular **ou** dependente **com conta** de uma assinatura **ativa**
  (`beneficiarios_assinatura ⋈ assinaturas_clube status='ativa'`). Coluna/filtro só aparecem com o
  **recurso `clube`** ligado (`tenant_tem_recurso('clube')`).
- **Busca** por nome (server-side, `LIKE`). **Filtros:** faixa de última visita
  (até 30 / 31–90 / +90 dias / nunca) e Clube (assinantes / sem Clube). Combinam entre si.
- **Detalhe** ao clicar em "Ver": linha expansível com os **últimos 8 agendamentos** do cliente
  (data, serviço, profissional, status) — só leitura.

### ⚠️ "Última visita" aqui ≠ "visita" dos Indicadores
- **Clientes (esta aba):** visita = **atendimento concluído** (`agendamentos`). Mede
  presença/comparecimento.
- **Indicadores ([[Indicadores — motor (Fase I)]]):** visita = **comanda paga** (`vendas`). Mede
  hábito de consumo (faturamento).
- São propositalmente diferentes. Não unificar sem decisão explícita.

### Performance (sem N+1)
- Última visita e assinante vêm de **subconsultas agregadas** (`GROUP BY cliente_id`) anexadas com
  `leftJoinSub` → **uma** query para a lista inteira, paginada. Mesmo estilo set-based de
  `IndicadoresClientes`. Os filtros operam sobre as colunas do join (WHERE), não em PHP.
- O detalhe é **uma** query extra, disparada só quando um cliente é expandido.
- Índices reusados: `clientes.nome` (busca/ordenação, PERF-004) e a FK `agendamentos.cliente_id`.

### Arquivos
- `app/Livewire/Painel/Clientes/Index.php` (componente — toda a lógica/queries).
- `resources/views/livewire/painel/clientes/index.blade.php` (view — sem lógica de negócio).
- Rota em `routes/tenant.php` (`painel.clientes`). Item no menu em
  `resources/views/components/layouts/painel.blade.php` (grupo Gestão).
- Testes: `tests/Feature/Painel/ClientesTest.php` (11) + rota no `HttpSmokeTest`.

## Roadmap (próximas fatias — NÃO entregues)
- **Fatia 2:** editar dados do cliente + **WhatsApp avulso** para um cliente (reusa o envio do
  [[WhatsApp (Evolution) no Nextgest]]).
- **Fatia 3 (sensível):** "resetar senha do cliente" — caminho **seguro**: disparar link/processo de
  redefinição para o **próprio cliente** (o dono **não** define/vê a senha). Auditoria-primeiro.
- **Reativação de inativos** ("faz tempo que não vem"): é **campanha segmentada** — encaixa **após** o
  broadcast real (reusa o motor de envio em massa + `Cliente::aceitaMarketing()`/opt-out de marketing
  do D86).
