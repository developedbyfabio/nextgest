---
projeto: Nextgest
tipo: módulo
status: implementado
criado: 2026-06-27
tags: [nextgest, avaliacoes, operacao, rbac, anonimato]
---

# Últimos serviços (Avaliações)

> Projeto: [[Nextgest - Visão Geral]] · Decisões: [[Decisões de Arquitetura]] (**D51** coleta/painel,
> **D67** filtro por profissional) · Padrão de UI: [[Padrao de UI-UX (Design System)]].

## O que é
Aba em **Operação** que lista os atendimentos **concluídos** e a avaliação de cada um (estrelas +
comentário, ou "sem avaliação"), com um **resumo** (média, nº de avaliações, concluídos, taxa) e
**filtros**. Componente: `App\Livewire\Painel\Avaliacoes\Index` (+ `livewire/painel/avaliacoes/index`).

## RBAC e anonimato (regra crítica — no servidor)
Por **permissão**, nunca por papel:
- **`ver_avaliacoes`** (Dono/Gerente) = `podeVerTudo()`: vê **todos** os atendimentos do tenant **com o
  nome do cliente** e os filtros de gestão (cliente, profissional).
- **`ver_avaliacoes_proprias`** (Profissional): vê **só os dele**, **anônimo** — a query **nem carrega**
  a relação `cliente` (o nome não sai do banco) e força `profissional_id = auth id`.
- Sem nenhuma das duas → 403 (a aba nem aparece no menu).

O anonimato é **real na query** (`escopo()`): o `with('cliente')` só é adicionado quando `podeVerTudo()`;
o escopo do profissional é forçado no servidor. **Não dá para burlar pelo request** (lição 8).

## Filtros
- **Cliente** (busca por nome) — só Dono.
- **Profissional** (select) — **só Dono** (D67); a lista é coerente com a unidade selecionada; trocar
  a unidade limpa o profissional. Mantém o nome do cliente (visão de gestão).
- **Período** (hoje/semana/mês), **Estrelas** (1–5), **Comentário** (com/sem), **Unidade** (se houver 2+).
- Período/cliente/profissional/unidade afetam **lista e resumo**; estrelas e comentário afetam **só a
  lista** (para a média/taxa do termômetro seguirem significativas).

### Blindagem do filtro de profissional (D67)
Aplicado só quando `podeVerTudo()`:
`->when($this->podeVerTudo() && $this->filtroProfissional, fn ($q) => $q->where('profissional_id', …))`.
Para o profissional o gate é false → o `filtroProfissional` recebido é **ignorado** e o escopo segue
forçado nele; o select **não é renderizado**. Mandar outro `profissional_id` não vaza nada.

## Testes
`tests/Feature/Painel/AvaliacoesPainelTest.php`: RBAC (Dono vê nome/todos; profissional só os dele,
anônimo; Recepção 403); anonimato real (a rota não traz o nome do cliente p/ profissional); filtros
(estrelas, comentário, cliente, período); e D67 — Dono filtra por profissional (mantém cliente), o
select não renderiza p/ profissional, e **SEGURANÇA**: profissional forçando outro `profissional_id`
segue só com os dele e anônimo. Suíte verde (550/550).

## Limites
Só leitura/apresentação + filtros. Não toca `MotorDisponibilidade`, fluxo de atendimento, faturamento
nem cobrança. **Dev — sem deploy** nesta fatia.
