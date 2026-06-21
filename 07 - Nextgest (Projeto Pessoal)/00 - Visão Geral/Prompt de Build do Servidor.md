# Nextgest — Prompt de Build do Servidor (do zero)

> Cole este documento inteiro para o Claude conectado como **root** no servidor.
> Ele é a especificação executável do projeto. Trabalhe em **fases**, parando e
> reportando ao fim de cada uma. Ver [[Decisões de Arquitetura]] e os modelos em
> `02 - Modelo de Dados`.

---

## 0. Missão e contexto

Você é o agente que vai instalar e estruturar o **Nextgest**, um SaaS de
agendamento multi-tenant (barbearias, salões, autônomos), do zero, em:

- **Servidor:** Ubuntu 24.04 LTS recém-instalado, **nada** instalado ainda.
- **Acesso:** root.
- **Diretório do projeto:** `/srv/www/nextgest`.
- **Domínio:** `nextgest.com.br`, com cada estabelecimento em
  `nextgest.com.br/{slug}` (multi-tenancy por **caminho**).

### Stack alvo
- PHP (versão estável mais recente, alvo 8.4), Laravel 13.
- Livewire (versão estável mais recente compatível, 3.x ou 4.x) + Alpine.js.
- MySQL 8, Nginx, Redis, Composer, Node 22 LTS, Vite, Tailwind CSS.
- Multi-tenancy: `stancl/tenancy` (banco por tenant).
- Permissões: `spatie/laravel-permission`.
- Pagamentos: arquitetura plugável; primeiro provedor Mercado Pago (stub agora).

---

## 1. Regras de segurança (obrigatórias, valem o tempo todo)

1. **Nunca** rode comando destrutivo de forma autônoma (DROP, TRUNCATE,
   DELETE/UPDATE sem WHERE, `migrate:fresh`, `migrate:reset`, `db:wipe`,
   `rm -rf` fora de build dirs, `git push --force`, `git reset --hard`). Em um
   servidor novo não deve haver necessidade; se surgir, **pare e peça revisão
   humana** com a frase: "Comando recusado. Operação destrutiva precisa de
   revisão humana."
2. **Nunca** faça hardcode de senha, token, segredo ou chave. Gere-os e grave em
   `.env` (permissão `600`). `.env` **nunca** entra no git (confirme o
   `.gitignore`). Em documentação, mascare: `[senha do banco]`, `[token do gateway]`.
3. Use git com commits por fase (mensagens claras). Sem force push.
4. Comandos idempotentes quando possível. Ao fim de cada fase, **reporte** o que
   foi feito e o que confirmar antes de seguir.
5. Se um pacote não suportar Laravel 13 / Livewire 4, **não force**: fixe a maior
   versão compatível e avise no relatório da fase.
6. PHP-FPM e Nginx rodam como `www-data`. O app não roda como root em runtime.

---

## 2. Fases de execução

### Fase 1 — Provisionamento do sistema
- `apt update && apt -y upgrade`.
- Pacotes base: `curl git unzip zip software-properties-common ca-certificates
  supervisor redis-server ufw`.
- PHP 8.4 (repositório `ppa:ondrej/php`) + extensões: `php8.4-cli php8.4-fpm
  php8.4-mysql php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip php8.4-bcmath
  php8.4-gd php8.4-intl php8.4-redis php8.4-opcache`.
- Composer (instalação oficial, binário global em `/usr/local/bin/composer`).
- Node 22 LTS (NodeSource) + npm.
- MySQL 8 (`mysql-server`). Defina senha forte de root **gerada** e guardada
  fora do git (ex.: `/root/.nextgest_db` com permissão `600`). Remova usuários
  anônimos e o banco de teste (equivalente ao `mysql_secure_installation`, de
  forma não interativa).
- Nginx.
- `ufw`: permitir `OpenSSH`, `80`, `443`; habilitar.
- Reporte versões instaladas.

### Fase 2 — Banco de dados
- Crie o banco **central**: `nextgest_central`.
- Crie um usuário de aplicação dedicado (`nextgest_app`) com senha forte gerada,
  com privilégios para criar/usar bancos com prefixo `tenant_` (o stancl cria um
  banco por tenant). Grave as credenciais só no `.env`.
- Não crie tabelas ainda (as migrations farão isso).

### Fase 3 — Projeto Laravel 13
- `composer create-project laravel/laravel:^13 /srv/www/nextgest` (ou o instalador
  oficial). Se 13 não estiver disponível ainda, use a maior versão e avise.
- Permissões: dono `www-data`, `storage/` e `bootstrap/cache/` graváveis.
- `.env`: `APP_NAME=Nextgest`, `APP_ENV=production`, `APP_URL=https://nextgest.com.br`,
  `APP_KEY` via `php artisan key:generate`, conexão MySQL central, `CACHE`/`QUEUE`
  via Redis.
- Inicialize git, primeiro commit.

### Fase 4 — Pacotes
- `composer require stancl/tenancy` (cheque compatibilidade).
- `composer require spatie/laravel-permission`.
- `composer require livewire/livewire`.
- Front: `npm install`, Tailwind + Vite configurados.
- Mercado Pago SDK PHP: instale só o pacote (`mercadopago/dx-php`), sem integrar
  ainda.

### Fase 5 — Multi-tenancy (stancl), identificação por caminho
- `php artisan tenancy:install`; publique config e migrations centrais.
- Configure **identificação por path**: middleware `InitializeTenancyByPath` e
  `PreventAccessFromUnwantedDomains`; rotas do tenant em `routes/tenant.php` sob o
  parâmetro `{tenant}` (primeiro segmento da URL).
- Configure domínios/rotas **centrais** (landing, `/admin`, login do super-admin,
  webhooks) que NÃO passam pela resolução de tenant.
- **Slugs reservados** (Apêndice C): impeça que virem tenant.
- Sessão escopada por tenant (cookie/path) para não vazar login entre
  estabelecimentos.
- Criação automática do banco do tenant ao criar um tenant.

### Fase 6 — Migrations centrais (Apêndice A)
- Ajuste a migration de `tenants` para incluir `nome` e `slug` (único). Mantenha
  `domains` do stancl (útil para domínio próprio no futuro).
- Crie `admins` (super-admin central).
- `php artisan migrate` no banco central. (NUNCA `migrate:fresh`.)

### Fase 7 — Migrations de tenant (Apêndice B)
- Crie as migrations de tenant na pasta de migrations de tenant do stancl
  (`database/migrations/tenant`).
- Inclua a migration do `spatie/laravel-permission` no contexto de tenant.
- Crie todas as tabelas do Apêndice B (agendamento, produtos/vendas, clube,
  pagamentos, kanban, whatsapp, configuracoes, clientes).
- Defina chaves estrangeiras e índices (FKs indexadas; índice em
  `agendamentos(profissional_id, data_hora_inicio)` para checar conflito de
  horário; índice em `usos_clube(assinatura_id, periodo_referencia)`).

### Fase 8 — Autenticação e papéis
- Dois guards: `web` (equipe = `users`, com spatie) e `cliente` (`clientes`,
  portal de agendamento).
- No evento de criação de tenant, rode as migrations do tenant e **semeie** os
  papéis e permissões padrão (Apêndice D). Financeiro é permissão separada,
  padrão só Dono.
- Crie uma `configuracao` inicial `confirmacao_automatica = true`.

### Fase 9 — Esqueleto do gateway de pagamento
- Interface `App\Services\Pagamentos\GatewayPagamento` com métodos: `cobrar`,
  `estornar`, `criarAssinaturaRecorrente`, `tratarWebhook`.
- `MercadoPagoGateway implements GatewayPagamento` como stub (sem lógica real
  ainda). Resolva o gateway pelo registro `gateways_pagamento` do tenant.
- **Não** armazene dados de cartão; `gateways_pagamento.credenciais` usa cast
  `encrypted`.

### Fase 10 — Documentação no repositório
- Crie `/srv/www/nextgest/docs/` com um `.md` por bloco
  (`01-arquitetura.md`, `02-agendamento.md`, `03-produtos-vendas.md`,
  `04-clube.md`, `05-pagamentos.md`, `06-kanban.md`, `07-whatsapp.md`) e um
  `README.md` raiz com visão geral e como rodar. Baseie-se neste documento.

### Fase 11 — Verificação e relatório final
- `php artisan migrate` central OK.
- Crie um **tenant de teste** (`barbeariateste`), confirme criação do banco e
  execução das migrations de tenant e do seed de papéis.
- Liste o que falta o humano fornecer/decidir: DNS apontando para o servidor,
  emissão de SSL (certbot) quando o DNS propagar, credenciais reais do Mercado
  Pago, e os pontos "A confirmar" deste documento.
- **Não** configure SSL/Nginx de produção sem o domínio resolvendo; deixe um
  server block pronto e avise.

---

## Apêndice A — Esquema do banco CENTRAL (`nextgest_central`)

**tenants** (base stancl + custom): `id`, `nome`, `slug` (único), `ativo` (bool),
campos internos do stancl, `created_at/updated_at`.
**domains** (stancl): para domínio próprio futuro.
**admins** (super-admin): `id`, `name`, `email` (único), `password`, `ativo`,
timestamps.
*(Planos do SaaS e cobrança dos estabelecimentos: adiados — não criar agora.)*

---

## Apêndice B — Esquema do banco do TENANT (uma por estabelecimento)

> Tipos: PK = bigint auto. FK = bigint indexada. `null` = nullable.
> snapshot = valor copiado no momento (histórico imutável).

### Agendamento
**unidades**: id, nome, endereco null, telefone null, ativo, timestamps.
**users** (equipe; guard web): id, name, email único, password, e_profissional
(bool), ativo, timestamps. (+ tabelas do spatie)
**clientes** (guard cliente): id, nome, email único null, telefone, password null,
timestamps.
**servicos**: id, nome, descricao null, duracao_minutos (int), preco
decimal(10,2), ativo, timestamps.
**servico_unidade** (pivô): id, servico_id FK, unidade_id FK.
**servico_user** (pivô): servico_id FK, user_id FK.
**user_unidade** (pivô): user_id FK, unidade_id FK.
**horarios_trabalho**: id, user_id FK, unidade_id FK, dia_semana (tinyint 0-6),
hora_inicio (time), hora_fim (time), timestamps.
**bloqueios**: id, user_id FK, inicio (datetime), fim (datetime), motivo null,
timestamps.
**agendamentos**: id, unidade_id FK, cliente_id FK, profissional_id FK (users),
data_hora_inicio (datetime), data_hora_fim (datetime), status (enum: pendente,
confirmado, em_andamento, concluido, cancelado, nao_compareceu), origem (enum:
cliente, equipe), criado_por_user_id FK null, valor_total decimal(10,2),
observacoes null, timestamps.
**agendamento_servico** (itens): id, agendamento_id FK, servico_id FK, preco
decimal(10,2) snapshot, duracao_minutos (int) snapshot.
**configuracoes**: id, chave (string), valor (string/json). Semear
`confirmacao_automatica=true`.

### Produtos e Vendas
**categorias_produto**: id, nome, ativo, timestamps.
**produtos**: id, categoria_id FK null, nome, descricao null, sku null,
preco_venda decimal(10,2), preco_custo decimal(10,2) null, controla_estoque
(bool), percentual_comissao decimal(5,2) null, ativo, timestamps.
**produto_unidade** (estoque por filial): id, produto_id FK, unidade_id FK,
quantidade (int).
**movimentacoes_estoque**: id, produto_id FK, unidade_id FK, tipo (enum: entrada,
saida, ajuste), quantidade (int), motivo null, venda_id FK null, user_id FK null,
created_at.
**vendas**: id, unidade_id FK, cliente_id FK null, agendamento_id FK null, status
(enum: aberta, paga, cancelada), valor_bruto decimal(10,2), desconto
decimal(10,2), valor_total decimal(10,2), criado_por_user_id FK null, data
(datetime), timestamps.
**venda_itens**: id, venda_id FK, tipo (enum: servico, produto), servico_id FK
null, produto_id FK null, descricao (string) snapshot, quantidade (int),
preco_unitario decimal(10,2) snapshot, subtotal decimal(10,2), profissional_id FK
null, percentual_comissao decimal(5,2) null snapshot, valor_comissao decimal(10,2)
null snapshot, coberto_por_assinatura (bool), assinatura_id FK null, timestamps.
**comissoes_profissional**: id, user_id FK, servico_id FK null, produto_id FK
null, percentual decimal(5,2).

### Clube de Assinatura
**planos_clube**: id, nome, descricao null, preco_mensal decimal(10,2),
periodicidade (enum: mensal), ativo, timestamps.
**plano_beneficios**: id, plano_id FK, servico_id FK, tipo (enum: ilimitado,
cota), cota_quantidade (int) null, dias_semana_permitidos (json) null, hora_inicio
(time) null, hora_fim (time) null, timestamps.
**plano_descontos**: id, plano_id FK, aplica_em (enum: servico, produto,
categoria, todos), servico_id FK null, produto_id FK null, categoria_id FK null,
tipo_desconto (enum: percentual, valor), valor decimal(10,2), timestamps.
**assinaturas_clube**: id, cliente_id FK, plano_id FK, status (enum: ativa,
suspensa, cancelada, inadimplente), preco_contratado decimal(10,2) snapshot,
data_inicio (date), data_fim (date) null, proxima_cobranca (date) null, gateway_id
FK null, gateway_assinatura_id (string) null, timestamps.
**usos_clube**: id, assinatura_id FK, plano_beneficio_id FK, servico_id FK,
agendamento_id FK null, venda_item_id FK null, periodo_referencia (string), data
(datetime), timestamps. Ciclo da cota conta a partir da **data de adesão**.

### Pagamentos
**gateways_pagamento**: id, provedor (enum: mercadopago, asaas, ...), apelido null,
credenciais (text, cast **encrypted**), modo (enum: sandbox, producao), ativo
(bool), padrao (bool), timestamps.
**pagamentos**: id, venda_id FK null, assinatura_id FK null, gateway_id FK null,
metodo (enum: pix, cartao_credito, cartao_debito, dinheiro, maquininha), valor
decimal(10,2), status (enum: pendente, aprovado, recusado, estornado, cancelado),
gateway_transacao_id (string) null, pix_copia_cola (text) null, link_pagamento
(string) null, pago_em (datetime) null, criado_por_user_id FK null, observacao
null, timestamps.
**cartoes_tokenizados**: id, cliente_id FK, gateway_id FK, token (string),
bandeira null, ultimos4 null, validade_mes (tinyint) null, validade_ano (smallint)
null, padrao (bool), timestamps. (Somente token — nunca o cartão real.)
**webhooks_pagamento**: id, gateway_id FK null, evento (string), payload (json),
processado (bool), recebido_em (datetime).

### Kanban (dois quadros: atendimento e CRM)
**kanban_quadros**: id, nome, tipo (enum: atendimento, crm), unidade_id FK null,
ativo, timestamps.
**kanban_colunas**: id, quadro_id FK, nome, ordem (int), timestamps.
**kanban_cartoes**: id, coluna_id FK, titulo, descricao null, ordem (int),
cliente_id FK null, agendamento_id FK null, responsavel_user_id FK null,
valor_estimado decimal(10,2) null, prazo (date) null, timestamps.
*(atendimento: cartão liga a agendamento; crm: lead/tarefa com responsável e prazo.)*

### WhatsApp (API oficial — Meta Cloud)
**whatsapp_config**: id, telefone null, phone_number_id (string) null,
business_account_id (string) null, token (text, cast **encrypted**), verificado
(bool), ativo (bool), timestamps.
**whatsapp_templates**: id, nome, conteudo (text), categoria null, idioma
(string, ex pt_BR), status_aprovacao (enum: pendente, aprovado, rejeitado),
timestamps.
**whatsapp_automacoes**: id, evento (enum: lembrete_agendamento,
confirmacao_agendamento, cancelamento_agendamento, aniversario_cliente),
template_id FK, antecedencia_minutos (int) null, ativo (bool), timestamps.
**whatsapp_mensagens**: id, cliente_id FK null, telefone (string), template_id FK
null, automacao_id FK null, conteudo (text), status (enum: enviado, entregue,
lido, falhou), gateway_message_id (string) null, enviado_em (datetime) null,
timestamps.

---

## Apêndice C — Slugs reservados (não podem ser tenant)
`admin`, `api`, `login`, `logout`, `register`, `webhooks`, `assets`, `storage`,
`vendor`, `livewire`, `nextgest`, `app`, `painel`, `super-admin`.

---

## Apêndice D — Papéis e permissões padrão (seed por tenant)
Papéis: **Dono** (todas), **Gerente** (tudo menos config sensível de tenant),
**Recepção** (agenda, clientes, vendas), **Profissional** (ver a própria agenda).
Permissões (ação_módulo), exemplos: `ver_agenda`, `ver_agenda_propria`,
`criar_agendamento`, `editar_agendamento`, `ver_clientes`, `criar_servico`,
`editar_servico`, `criar_produto`, `editar_produto`, `criar_usuario`,
`editar_permissoes`, `ver_financeiro`, `criar_venda`, `gerenciar_clube`,
`gerenciar_pagamentos`, `gerenciar_whatsapp`, `usar_kanban`.
`ver_financeiro` por padrão só no Dono. O Dono pode editar permissões de papéis e
criar papéis novos.

---

## Apêndice E — Decisões de arquitetura (resumo)
D01 banco por tenant (stancl). D02 identificação por path. D03 guards separados
(users/clientes). D04 RBAC flexível (spatie). D05 multi-unidade. D06
autoagendamento pelo cliente. D07 múltiplos serviços, um profissional, snapshot.
D08 serviço por unidade via pivô. D09/D21 segurança de pagamento (token,
credenciais criptografadas, webhook). D10 portal mobile-first. D11 confirmação
configurável. D12 estoque por unidade. D13 venda/comanda unificada. D14 comissão
por profissional (+override). D15 benefício ilimitado/cota. D16 restrição de
dias/horário no benefício. D17 descontos/cupons do clube. D18 ciclo da cota por
data de adesão. D19 gateway plugável, Mercado Pago primeiro. D20 métodos online +
presencial. D22 kanban com dois quadros (atendimento e CRM). D23 WhatsApp API
oficial com automações de lembrete, confirmação/cancelamento e aniversário.

---

## Pontos "A confirmar" (decidir com o humano antes de implementar a lógica)
- Recorrência Mercado Pago: preapproval nativo vs cobrança mensal por job.
- Estorno parcial / regras de reembolso.
- Pagamento dividido (vários pagamentos por venda) no MVP ou depois.
- Troca/upgrade de plano do clube.
- Credenciamento da API oficial do WhatsApp (número, verificação, templates).
