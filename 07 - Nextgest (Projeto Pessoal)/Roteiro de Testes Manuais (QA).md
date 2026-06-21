---
projeto: Nextgest
tipo: qa-roteiro
status: vivo
criado: 2026-06-22
tags: [nextgest, qa, testes-manuais, checklist]
---

# Nextgest — Roteiro de Testes Manuais (QA)

> Checklist para testar **no navegador** tudo o que existe hoje, caçando **bugs**,
> **erros/feiúras visuais (prioridade)** e **erros de lógica**. Baseado no código atual.
> Marque `[x]` o que passou e anote o que falhar na **tabela de bugs** (seção F).
> Foco visual: olhar **claro E escuro**, **mobile E desktop**, e os **estados**.

---

## A. Preparação

### A.1 Subir o sistema
1. No servidor (`/srv/www/nextgest`):
   - `php artisan serve --host=0.0.0.0 --port=51735` (porta alta; abra o firewall se o ufw
     estiver ativo: `sudo ufw allow 51735/tcp`).
   - Garanta os assets: `npm run build` (uma vez).
2. **URL base:** `http://192.168.11.210:51735`
3. Dados de demonstração (idempotente): `php artisan nextgest:demo barbeariateste` e
   `php artisan nextgest:demo salaoteste`.

### A.2 Tenants e acessos
- **`/barbeariateste`** — tema **barbearia (âmbar)**. **`/salaoteste`** — tema padrão.
- **Senha de TODOS os logins de demo: `password`.**

| Papel | Acesso | E-mail |
|---|---|---|
| Dono | `/{slug}/painel/login` | `dono@demo.test` |
| Gerente | `/{slug}/painel/login` | `gerente@demo.test` |
| Recepção | `/{slug}/painel/login` | `recepcao@demo.test` |
| Profissional | `/{slug}/painel/login` | `jorge@demo.test` (ou `ana@`, `bruno@`) |
| Cliente (portal) | `/{slug}/login` | `maria@cliente.test` (ou `carlos@`, `paula@`) |

- **Super-admin (central):** `/admin/login`. Use o super-admin já criado; se precisar de
  um, rode `php artisan nextgest:criar-admin` (NÃO anote senha em lugar nenhum).
- **O que esperar dos dados:** ~90 dias de **agendamentos** (status variados),
  **produtos** com estoque (Shampoo masculino propositalmente **esgotado**), **vendas
  pagas** no histórico com **formas de pagamento variadas**, override de comissão
  (Jorge 50% no "Corte masculino"), e cartões no **kanban**.

### A.3 Tenant VAZIO (estados vazios)
- Crie um novo pelo **Admin → Estabelecimentos → Novo** (ou `php artisan nextgest:demo
  lojavazia` e **não** popule — ou melhor, crie pelo onboarding e logo depois entre sem
  dados). Use-o para conferir **todos os estados vazios** (listas, dashboard, agenda,
  kanban).

### B. Formato de cada teste
`[ ]` caixa · **Fazer:** passos · **Esperado:** resultado · **Conferir visual:** o que
olhar. Em **todas** as telas, alterne **claro/escuro** pelo seletor e reduza a janela
para **mobile**.

---

## C. Cobertura por área

### C.1 Admin central (`/admin`)
- [ ] **Login admin.** Fazer: abrir `/admin/login`, entrar com o super-admin. Esperado:
  vai ao dashboard do admin. Conferir visual: layout da marca **Nextgest** (não tema de
  tenant), claro/escuro segue o sistema.
- [ ] **Listar estabelecimentos.** Fazer: menu **Estabelecimentos**. Esperado: lista com
  `barbeariateste`, `salaoteste`; busca por nome/slug funciona; paginação. Visual:
  badges de status, tabela legível.
- [ ] **Onboarding (criar + prévia ao vivo).** Fazer: **Estabelecimentos → Novo**;
  percorra as 5 etapas (identidade/segmento, responsável/Dono, horário, aparência,
  revisão). Esperado: ao escolher o **segmento**, sugere um **template**; a **prévia do
  portal** à direita reage às cores; confirmar **provisiona** o tenant e cria o Dono.
  Visual: wizard com progresso, prévia fiel, sem texto cortado.
- [ ] **Detalhe do estabelecimento.** Fazer: abrir um tenant na lista. Esperado: dados,
  criar Dono, impersonação/suporte (se exposto). Visual: consistente.
- [ ] **Borda:** criar com **slug reservado** (ex.: `admin`, `painel`) → deve **recusar**
  com mensagem clara. Slug duplicado → recusa.

### C.2 Portal do cliente (`/{slug}`)
- [ ] **Home deslogada.** Fazer: abrir `/barbeariateste`. Esperado: identidade do
  estabelecimento, "Como funciona", botões **Criar conta e agendar** / **Já tenho
  conta**. Visual: **acento âmbar** da marca, logo/ícone, tudo legível.
- [ ] **Seletor de tema (claro/escuro/sistema)** no cabeçalho. Fazer: alternar os 3.
  Esperado: superfícies trocam; **acento da marca permanece**; nada de texto sumindo.
- [ ] **Registrar.** Fazer: criar conta (nome, telefone, e-mail, senha ≥ 8, confirmar).
  Esperado: cria e entra. Borda: senha curta / e-mail repetido → erro claro.
- [ ] **Login.** Fazer: `maria@cliente.test` / `password`. Esperado: home logada.
- [ ] **Agendar (wizard completo).** Fazer: **Novo agendamento** → (unidade, se houver
  2+) → **serviços** (1+) → **profissional** (ou "Sem preferência") → **dia/horário** →
  **Confirmar**. Esperado: total/duração somam; horários livres aparecem; confirma e
  volta para a home com toast. Visual: cartões temáticos, passos com transição, skeleton
  ao trocar o dia, estado vazio "Sem horários".
- [ ] **Próximos / cancelar.** Fazer: na home, **Cancelar** um próximo (modal). Esperado:
  modal de confirmação (sem confirm nativo), cancela com antecedência; muito em cima da
  hora → recusa com aviso. Visual: faixa de data, badge de status.
- [ ] **Histórico / Meus dados / Clube (em breve).** Conferir presença e legibilidade.

### C.3 Painel — Dashboard (`/{slug}/painel`)
- [ ] **Acesso.** Dono/Gerente entram no dashboard. **Recepção e Profissional** são
  **redirecionados à agenda** (não têm `ver_dashboard`).
- [ ] **KPIs Financeiro:** **Faturamento** (real, com tendência), **Vendas pagas**,
  **Ticket médio**, **Comissão a pagar**. **Operação:** Agendamentos, Comparecimento,
  Clientes novos/recorrentes. Esperado: números coerentes com o período.
- [ ] **Gráficos:** Faturamento por dia, Mais vendidos (R$), Agendamentos por dia, Taxa
  de comparecimento, Serviços mais agendados, Horários. Visual: cores da marca, tooltips,
  legível no escuro.
- [ ] **Filtros** período (hoje/7d/30d/mês/personalizado) e **unidade** (se 2+). Esperado:
  atualiza KPIs e gráficos ao vivo (skeleton/spinner).
- [ ] **Estados:** num **tenant vazio**, ver empty-state "Sem agendamentos/Sem dados" —
  **sem inventar número** (R$ 0,00, gráficos vazios).

### C.4 Painel — Agenda (`/{slug}/painel/agenda`)
- [ ] **Dia/semana + navegação** (anterior/Hoje/próximo, seletor de data).
- [ ] **Filtros** profissional, unidade, status.
- [ ] **Cartões** com **acento de status**, horário, cliente, serviço/profissional.
- [ ] **Modal de detalhe.** Fazer: clicar num agendamento. Esperado: flyout com dados,
  serviços, e ações conforme status. Visual: tokens, sem texto sumindo.
- [ ] **Mudar status / remarcar / cancelar.** Cancelar abre **modal** (sem confirm
  nativo); remarcar mostra horários livres.
- [ ] **Gerar comanda.** Em um **concluído**, botão **Gerar comanda** leva ao detalhe da
  venda pré-preenchida.
- [ ] **Mobile:** dia em lista; **semana com rolagem horizontal (snap)** — não pode
  quebrar. Profissional: vê **só a própria** agenda.

### C.5 Painel — Kanban (`/{slug}/painel/kanban`)
- [ ] **Dois quadros:** **Atendimento** e **CRM** (CRM só para quem tem `gerir_kanban`).
- [ ] **Arrastar-e-soltar + persistência.** Fazer: arrastar um cartão por outra coluna
  (pelo **handle**); **recarregar a página** e conferir que ficou. Esperado: persiste
  coluna+ordem; placeholder/elevação ao arrastar.
- [ ] **Falha → revert.** (Se conseguir simular) mover deve reverter e avisar se o
  servidor recusar — board e banco nunca divergem.
- [ ] **Criar/editar coluna e cartão** (modais). **Arquivar** cartão: some do board mas
  **não é apagado** (modal de confirmação).
- [ ] **Mobile:** rolagem horizontal com snap entre colunas. Menu "Mover para" acessível.

### C.6 Painel — Cadastros
- [ ] **Unidades:** criar/editar (modal), **inativar** (modal de confirmação, não apaga)
  / reativar; estado vazio com CTA.
- [ ] **Serviços:** criar/editar com **preço, duração e % de comissão**; **busca** filtra;
  coluna de comissão aparece; inativar por modal; estado vazio.
- [ ] **Equipe:** criar membro (papel, senha, unidades; se **profissional**, marcar
  serviços que executa); **busca** por nome/e-mail; badge "Profissional"; **Horários**;
  inativar por modal. Borda: não inativar a **própria conta**.
- [ ] **Horários (de um profissional):** adicionar/remover faixas por dia; salvar.
  Esperado: alimenta a disponibilidade da agenda.
- [ ] **Papéis e permissões:** criar papel, marcar permissões; **Dono** mantém todas
  (callout). Visual: grade de permissões legível.
- [ ] **Bloqueios:** criar (profissional, início/fim, motivo); **remover** por modal.
  Borda: fim antes do início → erro.
- [ ] **Consistência:** cabeçalho, tabela, modais e estados **iguais** entre as seis.

### C.7 Painel — Produtos / Estoque (`/{slug}/painel/produtos`)
- [ ] **Lista:** busca, filtro por **categoria** e por **status**; paginação; estados
  loading/vazio.
- [ ] **Criar/editar produto** (modal): nome, categoria, SKU, preço de venda, preço de
  custo, **controla estoque**, % de comissão, ativo.
- [ ] **Categorias** (modal): criar/renomear/alternar ativa.
- [ ] **Estoque por unidade** (modal, para quem controla estoque): ver estoque atual por
  filial; **entrada** (somar) e **ajuste** (definir total); histórico de movimentações.
- [ ] **Inativar** produto (modal). Conferir: **Shampoo masculino** aparece **esgotado**.

### C.8 Painel — Vendas / Comanda (`/{slug}/painel/vendas`)
- [ ] **Lista:** filtros status/período/unidade, busca por cliente, estados.
- [ ] **Comanda avulsa.** Fazer: **Nova comanda** (unidade, cliente opcional/anônimo) →
  adicionar **produtos** (qtd) e **serviços**, definir **profissional por item**,
  **desconto**; conferir **totais ao vivo**.
- [ ] **A partir de agendamento concluído** (ver C.4): serviços vêm copiados; adicionar
  produtos.
- [ ] **Fechar e pagar (presencial).** Fazer: escolher forma(s) e valor(es); **dividir**
  pagamento (somatório precisa **= total**, botão desabilita se ≠); **dinheiro** mostra
  **troco** (não grava acima do total). Esperado: vira **paga**; detalhe lista os
  pagamentos.
- [ ] **Cancelar.** Cancelar uma **paga**: **estorna estoque** e marca pagamentos
  **estornados** (modal). Cancelar aberta: não mexe no estoque.

### C.9 Painel — Comissões (`/{slug}/painel/comissoes`) — **só Dono** (`ver_financeiro`)
- [ ] **Relatório por profissional** no período/unidade (total geral + por pessoa).
- [ ] **Comissões personalizadas (override).** Fazer: escolher profissional, definir % por
  serviço/produto; **deixar em branco** remove o override (volta ao padrão).

---

## D. Checagens transversais (VISUAL — prioridade)

- [ ] **Claro / escuro / sistema** em TODAS as telas (portal, auth, dashboard, agenda,
  kanban, cadastros, produtos, vendas, comissões): **nenhum texto sumindo**, contraste
  adequado, fundo correto, **acento da marca legível** (inclusive texto sobre botões na
  cor principal).
- [ ] **Responsivo no celular** (largura ~375px), com atenção a **agenda** (semana) e
  **kanban** (rolagem horizontal com snap) — nada pode quebrar/transbordar.
- [ ] **Acento + logo da marca**: testar `/barbeariateste` (âmbar) vs `/salaoteste`
  (padrão) — a identidade muda; superfícies seguem o **modo** (não a marca).
- [ ] **Estados** loading (skeleton), vazio (com orientação/CTA) e erro recuperável onde
  existirem (dashboard, agenda, listas com busca).
- [ ] **Modais/toasts**: criar/editar/confirmar em `flux:modal`; **nenhum `confirm`
  nativo** do navegador; toasts de sucesso/erro.

---

## E. Checagens de lógica (devem FALHAR graciosamente)

- [ ] **Agenda — concorrência/regras:** tentar agendar (portal) em **horário ocupado**,
  **fora do expediente** ou em **bloqueio** → não deve permitir; mensagem clara e
  horários recarregam.
- [ ] **Estoque:** vender produto **acima do estoque** da unidade → bloqueia com mensagem
  (ex.: tentar muitas unidades de um produto com pouco estoque).
- [ ] **Totais:** **desconto não passa do total**; **total = bruto − desconto**.
- [ ] **Comissão (snapshot ao pagar):** com o override do Jorge (50% no Corte), uma venda
  paga com ele nesse serviço grava **50%**; sem override, usa o **% padrão** do
  serviço/produto; sem nenhum, **sem comissão**.
- [ ] **Cancelar venda paga:** estoque **volta** (estorno) e pagamentos viram
  **estornado**.
- [ ] **Permissões por papel:**
  - **Profissional:** só **agenda própria**; **não** acessa Dashboard, Produtos, Vendas,
    Comissões, Cadastros (tentar a URL → **403**/redirect).
  - **Recepção:** acessa Agenda, Comandas, Kanban (Atendimento), e **Produtos só para
    Estoque** (não cria/edita catálogo); **não** vê Comissões (financeiro) nem Papéis.
  - **Gerente:** quase tudo, **menos** Comissões/financeiro e Papéis/permissões.
  - **Dono:** tudo.

---

## F. Registro de bugs (preencher e trazer de volta)

| # | Tela / Caminho | O que aconteceu | Esperado | Claro/Escuro? | Mobile? | Severidade |
|---|---|---|---|---|---|---|
| 1 | | | | | | |
| 2 | | | | | | |
| 3 | | | | | | |
| 4 | | | | | | |
| 5 | | | | | | |

> **Severidade:** 🔴 crítico (trava/erro/dado errado) · 🟠 visual grave (texto sumindo,
> quebra de layout) · 🟡 visual leve / polimento · 🔵 ideia/melhoria.

---

## Apêndice — mapa de rotas (para conferência)
- **Central:** `/` (landing) · `/admin/login` · `/admin` · `/admin/estabelecimentos` ·
  `/admin/estabelecimentos/novo` · `/admin/estabelecimentos/{slug}`.
- **Portal:** `/{slug}` · `/{slug}/login` · `/{slug}/registrar` · `/{slug}/agendar`.
- **Painel:** `/{slug}/painel/login` · `/{slug}/painel` (dashboard) · `.../agenda` ·
  `.../servicos` · `.../produtos` · `.../vendas` · `.../vendas/{id}` · `.../comissoes` ·
  `.../bloqueios` · `.../kanban` · `.../unidades` · `.../equipe` ·
  `.../equipe/{id}/horarios` · `.../papeis` · `.../aparencia`.
