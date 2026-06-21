# Nextgest — Regras para o Claude Code

## ANTES DE TUDO — leia o contexto do projeto (obrigatório)
- ANTES de qualquer tarefa, LEIA os documentos da pasta "07 - Nextgest (Projeto Pessoal)/"
  (na raiz do projeto). É a fonte de verdade: visão geral, decisões de arquitetura
  (D01–D31), modelo de dados, fluxos, regras de negócio, gotchas e bugs já resolvidos.
  Não comece a mexer sem ter lido o que for relevante à tarefa em questão.
- Roteiro mínimo de leitura: "00 - Visão Geral", "01 - Decisões de Arquitetura",
  "10 - Aprendizados do Projeto" (gotchas) e "09 - Bugs e Correções". Para tarefas de
  banco/tabelas, leia também "02 - Modelo de Dados".
- Esta pasta é a documentação VIVA do projeto: leia o que for relevante antes de
  agir e ATUALIZE-A ao final de cada tarefa relevante.

## Idioma
- Responda e documente em português do Brasil (pt-BR).

## Autonomia (ampla — pode agir sem pedir permissão)
O Fabio autoriza você a trabalhar e TESTAR livremente, sem confirmar a cada passo:
- Rodar testes, `npm run build`, `php artisan` (migrate, seeders, demo, tinker de leitura),
  servir a aplicação, limpar caches, instalar dependências do projeto (composer/npm).
- Criar e popular tenants de teste, criar usuários de demonstração, gerar dados fictícios.
- Editar código, criar arquivos, refatorar, commitar em pequenos passos.
- Reverter o SEU próprio trabalho não publicado quando fizer sentido.
Trabalhe com iniciativa; não peça permissão para tarefas reversíveis e de rotina.

## Ambiente (dev)
- Servidor de desenvolvimento COMPARTILHADO com outros projetos (192.168.11.210).
  NUNCA toque em arquivos, bancos ou serviços fora de /srv/www/nextgest e dos bancos
  `nextgest_central` e `tenant_*`. Outros projetos do servidor são intocáveis.
- Ao servir/testar, use SEMPRE portas altas aleatórias e `--host=0.0.0.0`. Nunca assuma a 8000.
- Stack: Laravel 13, Livewire 4 + Flux, Tailwind v4 (Vite), MySQL. Multi-tenancy stancl
  por caminho: banco central `nextgest_central` + um banco `tenant_{slug}` por estabelecimento.

## Build e testes (regra dura — já causou travamento)
- Rode build e testes em FOREGROUND e mostre a saída. NUNCA use `npm run dev`; use `npm run build`.
- PROIBIDO esperar processo com loops `until/sleep` ou `pgrep node` (o vite roda em node e o loop
  nunca termina). Rode UM build por vez (builds concorrentes corrompem os assets).
- Rode `php artisan test` e mantenha a suíte verde. Há testes de fumaça HTTP (Smoke) — não os quebre.

## Limites (precisa de um humano — NÃO faça sozinho)
Estas ações são irreversíveis ou perigosas no servidor compartilhado. Descreva o que
seria feito e PEÇA para o Fabio executar ou confirmar explicitamente:
- Apagar dados/estrutura: DROP, TRUNCATE, DELETE/UPDATE sem WHERE,
  `migrate:fresh`/`migrate:reset`/`db:wipe`, apagar bancos.
- Git destrutivo/público: `git reset --hard`, `git clean -fd`, `push --force`,
  apagar branch remota, reescrever history.
- Qualquer coisa fora do escopo do projeto (outros bancos, serviços do sistema, usuários do SO).
- Operar contra produção (quando existir) — aqui é só dev.
Soft delete (inativar) é livre; exclusão definitiva precisa de humano.

## Segredos
- O `.env` já é lido pelo framework automaticamente — use as ferramentas do Laravel,
  que acessam as credenciais internamente. NÃO copie senhas/tokens do `.env` para
  comandos, logs, commits ou saídas. NUNCA exponha segredos em texto.
- Arquivos por tenant: gere URLs com `tenant_asset()`.

## Git
- `git add` em arquivos específicos, nunca `git add .` cego. Commits pequenos e descritivos.
- `git pull --no-rebase` antes de `push`. Nunca `--force`.
- Não versione bancos sqlite de tenant (database/tenant_*).
- A pasta "07 - Nextgest (Projeto Pessoal)/" PASSA A SER VERSIONADA (é a doc viva e
  não contém segredos), para todo clone/sessão já vir com o contexto. Inclua-a nos
  commits ao atualizá-la.

## UI/UX (D27)
- Nada de tela crua: estados de loading/vazio/erro/sucesso, responsivo, dark, acessível.

## Reuso
- Reaproveite `MotorDisponibilidade`/`Agendador` (regras de agenda/concorrência),
  `App\Support\Aparencia` (tema) e o componente de prévia. Não reimplemente essas regras.

## Documentação
- Ao concluir uma tarefa relevante, atualize a documentação em
  "07 - Nextgest (Projeto Pessoal)/" nas pastas certas. O Fabio sincroniza essa pasta
  com o Obsidian dele; não tente escrever no Obsidian diretamente.
