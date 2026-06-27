---
projeto: Nextgest
tipo: módulo
status: implementado
criado: 2026-06-23
tags: [nextgest, clube, assinatura, recorrencia, gateway, indicadores, tenant]
---

# Clube de Assinatura (Fase A)

> Projeto: [[Nextgest - Visão Geral]] · Modelo: [[Modelo de Dados - Clube de Assinatura]]
> (D15–D18) · Flags: [[Recursos por Tenant (Feature Flags)]] (D37) · RBAC: [[Papéis e Permissões (RBAC)]]
> (D39) · Decisão: [[Decisões de Arquitetura#D42]].

## O que é (e o que NÃO é)
Fundação do Clube **pronta para plugar o gateway recorrente** quando houver VPS. Entrega
**modelos + aba + indicadores + relatórios**, gated pela flag `clube`. **NÃO cobra de verdade**
(cobrança recorrente real = Mercado Pago **Preapproval** + webhook = Fase 2/3, com VPS). O status
do assinante é **manual** por enquanto, atrás de uma **costura trocável**.

## Schema (reconciliação importante)
O schema rico (D15–D18) — `planos_clube`, `plano_beneficios` (ilimitado/cota + restrição),
`plano_descontos`, `assinaturas_clube`, `usos_clube` + colunas `venda_itens.coberto_por_assinatura`
/`assinatura_id` — **JÁ EXISTIA** desde `2026_06_14_190003_create_produtos_clube_pagamentos_tables`
(não havia models/UI, só as tabelas). A Fase A:
- **Adiciona** `eventos_assinatura_clube` (migração `2026_06_23_100001`): histórico de mudanças de
  status (`criada`/`renovada`/`pagamento_ok`/`pagamento_falhou`/`cancelada`/`reativada`), **fonte da
  evolução/churn**. Índices `(tipo, ocorrido_em)` e `(ocorrido_em)`.
- **Adiciona** índice `assinaturas_clube.status` (migração `2026_06_23_100002`) — o índice existente
  era `(cliente_id, status)` (líder errado para filtro só por status). EXPLAIN (volumeteste):
  `ativos`/`inadimplentes` → `type=ref key=assinaturas_clube_status_idx [Using index]`.
- **status real** (enum de 190003): `ativa | suspensa | cancelada | inadimplente` (default `ativa`)
  — **não** há `pendente`. Os models/serviços/UI seguem esse enum.

Models: `PlanoClube`, `PlanoBeneficio`, `PlanoDesconto`, `AssinaturaClube` (status + scopes
`ativas`/`inadimplentes`), `UsoClube`, `EventoAssinaturaClube`.

## Costura do gateway recorrente
`App\Services\Clube\GatewayRecorrente` (interface) + `GatewayRecorrenteManual` (impl. atual: **não
cobra, não chama API, sem webhook** — status manual). Binding em `AppServiceProvider`. O Mercado
Pago Preapproval será **outra implementação** desta interface no futuro, **sem mudar a aba**.
`AssinaturaClube.gateway_assinatura_id` e `proxima_cobranca` já existem para o id do Preapproval.

## Ciclo de vida (eventos)
`App\Services\Clube\Assinaturas`: `criar()` (snapshot do preço, `proxima_cobranca = +1 mês`, evento
`criada`) e `alterarStatus()` (→ `ativa` reativada / `inadimplente` pagamento_falhou / `cancelada`
cancelada + `data_fim` + cancela recorrência). **Toda** mudança de status grava um
`EventoAssinaturaClube` (sem duplicar se o status não mudou). O webhook futuro chamará o mesmo
`alterarStatus()`.

## Benefício (v1 = desconto percentual)
`App\Services\Clube\BeneficioClube`: o plano dá **X% de desconto** (um `plano_desconto`
`percentual`/`todos`). Aplica na comanda do **assinante ATIVO** reusando **`Comanda::definirDesconto`**
(não reescreve o núcleo) — botão "Aplicar X% do clube" no detalhe da comanda, só com a flag ligada
e cliente com assinatura ativa. Inadimplente/cancelado **não** recebe. Cota/serviços inclusos
(`plano_beneficios`/`usos_clube`, cobrir item a preço 0) ficam para fase futura — **schema já pronto**.

## A aba (gated `recurso:clube` + `can:gerenciar_clube`)
`App\Livewire\Painel\Clube\Index` (rota `painel.clube`, item em **Gestão** dentro de
`@recurso('clube') @can('gerenciar_clube')`). Flag off → some do menu e rota **404**; sem permissão
→ **403**. Permissão **reusada `gerenciar_clube`** (já existia, Dono+Gerente; sem novo seeder).
Seções: **Visão geral** (cards + evolução + inadimplentes), **Planos** (CRUD + desconto %; "excluir"
= inativar), **Assinantes** (filtros + adicionar + mudar status manual → gera evento), **Relatórios**
(filtros período/plano/status + **export CSV**). Aviso explícito de "cobrança automática pendente".

## Indicadores (set-based, contagem constante)
`App\Services\Clube\IndicadoresClube` — tudo SQL agregado, **nunca loop por assinante**:
- **Assinantes ativos** = `status='ativa'` (1 query, índice).
- **Novos no mês** = eventos `criada` no mês; **Cancelamentos no mês** = eventos `cancelada` (churn).
- **Inadimplentes** = `status='inadimplente'` paginado (cliente+plano eager) — lista de cobrança.
- **Evolução** = entradas (`criada`+`reativada`) × saídas (`cancelada`) por mês (UMA query, mês
  driver-aware `DATE_FORMAT`/`strftime`; meses vazios preenchidos em PHP).
- Teste de teto (`ContagemQueriesTest`): 5 vs 45 assinaturas → contagem **igual** (`n45 == n5`).

## Estado "pronto para o gateway"
Campos (`gateway_assinatura_id`, `proxima_cobranca`, `gateway_id`) + interface `GatewayRecorrente`
**existem e estão prontos**. Plugar o MP Preapproval = nova impl. da interface + webhook chamando
`alterarStatus()`. **Sem refactor da aba.**

## Testes (suíte 394 → 404)
`tests/Feature/Painel/ClubeTest.php` (9): flag off→404 + menu sem item; on+Dono→200; sem permissão→403
cru; indicadores leem eventos (3 novas/1 cancelada → ativos=2/novos=3/cancelados=1); mudança de status
gera evento (sem duplicar); benefício só p/ ativo (10% de 60 → total 54; inadimplente null); isolamento
por tenant; aba renderiza + **CSV** (`assertFileDownloaded`). `ContagemQueriesTest` (+1): indicadores
do clube com contagem constante.

## Verificação
EXPLAIN (volumeteste, 1500 assinaturas/1668 eventos): agregações usam índice (`status_idx`,
`tipo_data_idx`, `data_idx`). Contagem de query: ativos/novos/cancelados/evolução = 1, inadimplentes
= 4 (paginação). `nextgest:semear-volume` estendido (`--assinaturas`, planos/assinaturas/eventos;
blocos com guard `>0`). `laravel.log` vazio.

## Evolução D44 — cobertura (100%) + família/beneficiários (substitui % desconto)
> Regra definitiva: [[Regra de Negócio — Clube de Assinatura]] · Decisão: [[Decisões de Arquitetura#D44]].

O benefício deixou de ser **% desconto** e passou a ser **cobertura de serviços (100%)**:
- **Plano** ganhou `ilimitado`/`limite_usos`/`periodo`/`dias_semana`/`capacidade` (aditivo em
  `planos_clube`). **Serviços cobertos** reusam `plano_beneficios`. Limite/dias/capacidade são **do
  plano** (a assinatura/família compartilha o teto). `plano_descontos` (% ) ficou **depreciado**.
- **Beneficiários** (`beneficiarios_assinatura`): com conta (`cliente_id`) ou sem conta (`nome`); o
  titular é beneficiário; trava `≤ capacidade`. UI na aba (gerir beneficiários do assinante).
- **Cobertura na comanda** (`BeneficioClube::aplicarCobertura`): zera 100% os serviços cobertos do
  assinante ativo (titular ou beneficiário com conta), no dia permitido e dentro do teto; registra
  `uso_clube`; o resto é pago no balcão. Reusa `Comanda::recalcular`. Botão "Aplicar cobertura" no
  `Vendas\Detalhe` (substitui o de %).
- **Aba:** planos mostram serviços cobertos + limite + dias + capacidade; assinantes mostram
  beneficiários. Indicadores set-based seguem **constantes** (`ClubeTest` + `ContagemQueriesTest`).
- `nextgest:semear-volume` semeia planos de cobertura (Mensal/Premium/Família) + beneficiários (titular).

## Fora de escopo (futuro)
**Agendar para beneficiário** (toca a agenda/`MotorDisponibilidade`) = **próximo prompt**. Cobrança
recorrente real (MP Preapproval + webhook, Fase 2/3) — segue costura manual. Cota por-serviço (vs
por-plano) e item-pai "Financeiro"/billing da plataforma ficam para depois. **Gancho Fase 4:**
inadimplentes/risco → disparo de WhatsApp.

## Correção — modal "Adicionar assinante" (D66)
Bug: na aba **Assinantes**, o modal de novo assinante **abria sozinho** e o botão ficava
não-determinístico. Causa: o gatilho misturava a magia Alpine `$flux` dentro de um `wire:click`
(Livewire) — `wire:click="$set(...); $flux.modal('novo-assinante').show()"` —, malformado: ao
renderizar a aba o `.show()` disparava. Correção: método server-side `novoAssinante()` (reseta +
`Flux::modal('novo-assinante')->show()`, padrão de `novoPlano`/`gerirBeneficiarios`) + botão
`wire:click="novoAssinante"`. Regra de negócio do Clube **intacta**. Testes em `ClubeTest`.
