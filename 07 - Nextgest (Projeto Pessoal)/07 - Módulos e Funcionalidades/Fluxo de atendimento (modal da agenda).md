---
projeto: Nextgest
tipo: mapeamento
status: implementado (Fatia 5 — Parte 2 aplicada; ver D70)
criado: 2026-06-27
tags: [nextgest, agenda, atendimento, comanda, auditoria]
---

# Fluxo de atendimento (modal da agenda) — mapeamento

> **Parte 1** foi auditoria só-leitura; **Parte 2 (D70) já aplicada** — ver a seção no fim.
> Arquivos: `app/Livewire/Painel/Agenda/Index.php`, `resources/views/livewire/painel/agenda/index.blade.php`,
> `app/Models/Agendamento.php`, `app/Services/Agendamento/Agendador.php`, `app/Services/Venda/Comanda.php`.

> [!info] Atualização Parte 2 (D70)
> Decisão do Fabio: **todo concluído gera comanda**. O botão **"Concluído" foi removido** e
> `Agenda\Index::mudarStatus()` passou a **rejeitar** `'concluido'` (whitelist `STATUS_VIA_MUDAR`):
> concluir é **só** pelo "Finalizar atendimento". Botões reorganizados: status grandes (largura cheia)
> **acima**, Cancelar em vermelho, "Finalizar" primary embaixo. `Agendador::mudarStatus()` inalterado
> (o Finalizar ainda conclui). Status `concluido` **preservado** (avaliações/previsão/métricas seguem).
> Detalhes na seção "Parte 2 — o que mudou" no fim.

## 1. O modal (slide-over) e seus botões
O "modal" é um **flyout à direita** (`flux:modal variant="flyout"`) aberto por `abrirDetalhe(id)` ao
clicar num cartão da agenda. Tem três blocos de ação, cada um com sua **gate de permissão**:

**Bloco A — Finalizar (gate `podeFinalizar`):** aparece para quem tem `criar_venda`/`gerir_agenda`,
ou o **próprio profissional** com `finalizar_atendimento_proprio`. Um único botão, rótulo conforme o
estado:
- status ≠ concluído e sem comanda → **"Finalizar atendimento"** (ícone check-circle).
- status = concluído e sem comanda → **"Gerar comanda"**.
- já existe comanda (não cancelada) → **"Abrir comanda"**.
- Todos chamam o **mesmo** método `finalizarAtendimento()`.

**Bloco B — Ações de status (gate `podeGerir` = `gerir_agenda`):** renderiza um botão **por transição
permitida** de `Agendamento::TRANSICOES[status]` (chama `mudarStatus($proximo)`), exceto "cancelado"
que vira **"Cancelar"** (danger → abre modal de confirmação `pedirCancelar`). Mais o botão **"Remarcar"**
(`iniciarRemarcacao`), só quando o status **não** é livre nem concluído.

**Bloco C — Remarcar:** lista de slots livres (reusa `MotorDisponibilidade::slots`), confirma com
`confirmarRemarcacao(hora)` → `Agendador::remarcar`.

### Tabela dos botões
| Botão (UI) | Método | Status resultante | Efeitos colaterais |
|---|---|---|---|
| **Finalizar atendimento** | `finalizarAtendimento()` | → `concluido` (se já não for) e depois cria/abre comanda | Conclui via `Agendador::mudarStatus`; **gera a Venda** (`Comanda::apartirDeAgendamento`, copia serviços como itens, profissional travado); **redireciona** para a comanda. Idempotente (reusa venda existente). |
| **Gerar comanda** / **Abrir comanda** | `finalizarAtendimento()` (mesmo) | mantém `concluido` | Idem acima sem reconcluir (já está concluído). "Abrir" só leva à venda já existente. |
| **Concluído** | `mudarStatus('concluido')` | → `concluido` | **Só muda o status.** NÃO gera comanda, NÃO trava nada, NÃO dispara avaliação. (Aparece como transição quando o status é pendente/confirmado/em_andamento.) |
| **Confirmado** | `mudarStatus('confirmado')` | → `confirmado` | só status (transição de `pendente`). |
| **Em andamento** | `mudarStatus('em_andamento')` | → `em_andamento` | só status. |
| **Cancelar** | `pedirCancelar()` → `cancelarAgendamento()` | → `cancelado` | confirma em modal; **libera o horário** (status livre, `scopeOcupantes` exclui). |
| **Não compareceu** | `mudarStatus('nao_compareceu')` | → `nao_compareceu` | só status; libera o horário. |
| **Remarcar** | `iniciarRemarcacao` → `confirmarRemarcacao(hora)` | mantém status | revalida slot com lock e move data/hora (`Agendador::remarcar`). |

Transições válidas (`Agendamento::TRANSICOES`): `pendente`→{confirmado, em_andamento, concluido,
cancelado, nao_compareceu}; `confirmado`→{em_andamento, concluido, cancelado, nao_compareceu};
`em_andamento`→{concluido, cancelado}; **`concluido`/`cancelado`/`nao_compareceu` são terminais ([])**.

## 2. "Finalizar atendimento" × "Concluído"
- **Concluído** (`mudarStatus('concluido')`): muda só o status do agendamento. Nada mais.
- **Finalizar atendimento** (`finalizarAtendimento`): **faz o que o "Concluído" faz e mais** — conclui
  (se preciso) **e** gera/abre a **comanda** (Venda), redirecionando para ela. Cliente e profissional
  vêm travados do agendamento.
- **São quase redundantes:** "Finalizar" é um superset de "Concluído". A única coisa que o "Concluído"
  oferece e o "Finalizar" não é **concluir sem criar comanda**.
- **Existe caso de uso para concluir sem comanda?** Em tese sim: atendimento de cortesia, ou 100%
  coberto pelo clube em que não se queira abrir venda. **A confirmar com o Fabio** se isso é desejado
  — hoje a comanda nasce com itens snapshot, e a cobertura do clube/desconto é aplicada **dentro da
  comanda** (ver `Comanda`/UsoClube), então mesmo cortesia/clube normalmente passam por comanda.
  Se nunca se quer "concluído puro", o "Concluído" é de fato redundante.

## 3. Consumidores do status `concluido` (quem lê/depende)
- **Avaliações (D51) — depende fortemente:**
  - `Avaliacoes\Index::escopo()` filtra `where('status','concluido')` — a aba "Últimos serviços" e o
    termômetro (resumo: concluídos/avaliados/taxa) **só contam concluídos**.
  - Portal `Home::avaliacaoAvaliavel()` e o **popup de avaliação** (`Home` ~l.57): só o atendimento
    **concluído** é avaliável / candidato a popup. `Avaliacao::create` (Home l.101) exige passar pela
    gate de concluído.
- **Faturamento / previsão (Fatia 3 / D68):** `Metricas::baseAReceberSemana()` **exclui** `concluido`
  (junto de cancelado/no-show) do "a receber" — ou seja, concluído **sai** da previsão (presume-se já
  realizado/à parte). Se "Concluído" sumir, o efeito é o mesmo desde que se chegue a concluído por
  outro caminho.
- **Dashboard / indicadores:**
  - `Metricas::profissionaisDesempenho()` conta **só `concluido`** (ranking + valor estimado).
  - `Metricas::comparecimento()` usa a contagem de `concluido` vs no-show/cancelado → **taxa de
    comparecimento** (gráfico do Dashboard, `Dashboard.php` l.134/233).
- **Comanda / venda / comissões — NÃO dependem do status:** `Comanda` não lê `agendamento.status`
  (confirmado por grep). A venda/baixa de estoque/comissão rodam no fluxo da própria comanda
  (`pagarPresencial`→`finalizar`), independentes de o agendamento estar "concluído". *Observação:*
  `finalizarAtendimento` **conclui antes** de gerar a comanda, então na prática toda comanda de
  agendamento nasce de um agendamento concluído — mas é o **botão** que garante isso, não a Comanda.
- **Portal/Home (cliente):** "histórico" agrupa concluído/cancelado/no-show; só exibição.
- **Seeders** (`SemearDemo`, `SemearVolume`): geram agendamentos `concluido` para popular avaliações/
  métricas. Não são produção.
- **Sem observer/evento** ligado a `Agendamento` (grep confirmou) — nada reage automaticamente à
  mudança para `concluido`. Os efeitos são todos via os métodos acima.

## 4. Impacto de remover o botão "Concluído"
- **Não quebra dados nem regras:** o status `concluido` continua existindo e sendo alcançável pelo
  **"Finalizar atendimento"** (que conclui + gera comanda). Avaliações, ranking e taxa de
  comparecimento continuam funcionando, pois passam a depender do concluído gerado pelo "Finalizar".
- **Única perda:** a forma de marcar **concluído SEM abrir comanda**. Hoje isso é o que diferencia os
  dois botões. Se esse caso (cortesia / 100% clube sem venda) **não** for necessário, remover é seguro.
  Se **for** necessário, manter um caminho (renomear "Concluído" para algo como "Concluir sem comanda",
  ou tratar cortesia/clube dentro da comanda).
- **Atenção (taxa de comparecimento):** se um atendimento que "aconteceu" deixar de ser marcado
  concluído (porque o operador não quis abrir comanda e não tem mais o botão), ele não entra na taxa
  de comparecimento nem no ranking. Decidir o caminho para esses casos.
- **Transição terminal:** `concluido` é terminal — uma vez finalizado, não há "desfazer" pela UI
  (comportamento atual, manter em mente na Parte 2).

## 5. Layout atual dos botões (para a reorganização)
- **Finalizar** (Bloco A): botão **`variant="primary"` largura cheia** (coluna `flex flex-col gap-2`),
  com texto auxiliar abaixo ("Conclui o atendimento e abre a comanda..."). É o destaque atual.
- **Status** (Bloco B): botões **`size="sm"`** em `flex flex-wrap gap-2` (pequenos, lado a lado),
  um por transição; "Cancelar" é `variant="danger"`. "Remarcar" é `size="sm" variant="subtle"` abaixo.
- Separadores `flux:separator` entre os blocos.
- Objetivo da Parte 2 (a confirmar): **botões grandes** com o "Finalizar" no topo; possivelmente
  remover/realocar "Concluído". Hoje os de status são pequenos — promover os relevantes a grandes
  é viável sem mexer em lógica (só a view), **exceto** a decisão sobre "Concluído" (lógica do modal).

## Conclusão da auditoria
"Concluído" é **praticamente redundante** com "Finalizar atendimento" (este é superset: conclui +
comanda). A **única** função exclusiva do "Concluído" é **concluir sem gerar comanda**. A decisão da
Parte 2 depende de **uma pergunta de negócio**: *existe o caso "atendimento concluído sem venda"
(cortesia / 100% clube)?* Se não, remover "Concluído" é seguro (status segue acessível pelo
"Finalizar"); se sim, manter/renomear um caminho. Sem impacto em comanda/comissão/estoque (não leem o
status). Avaliações, ranking e taxa de comparecimento **dependem** de existir agendamento concluído —
e continuariam ok desde que o "Finalizar" siga concluindo.

## Parte 2 — o que mudou (D70)
Resposta de negócio: **não existe** "concluído sem comanda". Implementado:
- **Removido** o botão "Concluído" do flyout.
- **`Agenda\Index::mudarStatus()`** valida contra `STATUS_VIA_MUDAR =
  ['confirmado','em_andamento','cancelado','nao_compareceu']` — `'concluido'` (e qualquer outro fora
  da lista) é rejeitado com toast "Para concluir, use 'Finalizar atendimento'". Brecha fechada.
- **`Agendador::mudarStatus()` intacto** — `finalizarAtendimento()` continua concluindo + gerando
  comanda (único caminho até `concluido`).
- **Layout:** status grandes (largura cheia) empilhados acima (Confirmado/Em andamento/Não compareceu/
  Remarcar), Cancelar em vermelho (risco, com a confirmação em modal do D65), "Finalizar" primary
  embaixo. `MotorDisponibilidade` e a lógica de cada ação não foram tocados.
- **Preservado:** o status `concluido` segue existindo e alimentando Avaliações (D51), Previsão (D68)
  e métricas. Comanda/venda/comissão/estoque não leem o status.
- **Cobertura:** `AgendaTest` (+2 testes), `FinalizarAtendimentoTest` verde, suíte **563/563**;
  verificado por HTTP (Playwright). **Sem migration. Sem deploy.**
