# Nextgest — Prompt: Guia de testes (como rodar e testar localmente)

> Cole para o Claude root do servidor em `/srv/www/nextgest`. Objetivo: me ensinar
> a rodar e testar o sistema **nesta máquina local**, do zero ao clique, com
> comandos e URL reais (não genéricos). Sem comandos destrutivos autônomos.
> Não exponha segredos do `.env` em docs.

---

## Objetivo
Produzir `docs/GUIA-DE-TESTES.md` (pt-BR) e me explicar no relatório, passo a
passo, como subir e testar tudo localmente. Inclua dados de demonstração para eu
não precisar cadastrar tudo na mão.

## Tarefas
1. **Ambiente de teste confortável.** Hoje o `.env` está com `APP_ENV=production`
   e `APP_DEBUG=false`, o que esconde erros — ruim para testar. Recomende e
   explique como usar um modo local para desenvolvimento (ex.: `APP_ENV=local`,
   `APP_DEBUG=true`) e como voltar. Se preferir manter production, explique onde
   ver os erros (logs). Diga claramente como alternar.
2. **Serviços necessários.** Como verificar/iniciar MySQL e Redis (a sessão, fila
   e cache usam Redis). Se a fila for necessária para algo, explique
   `php artisan queue:work`.
3. **Como servir e acessar.** Diga o comando real para subir o app nesta máquina
   (nginx já habilitado? ou `php artisan serve`?) e a **URL exata** para abrir no
   navegador, incluindo o slug do tenant — ex.: `http://localhost:8000/barbeariateste`.
   Explique a diferença entre a rota central (`/admin`) e a do tenant (`/{slug}`).
4. **Dados de demonstração.** Crie um seeder/comando `php artisan nextgest:demo
   {tenant}` que popula um cenário realista no tenant: 1 unidade, alguns serviços
   (com duração/preço), 2–3 profissionais (com serviços e horários de trabalho),
   alguns clientes e alguns agendamentos em status variados. Assim eu testo o
   fluxo imediatamente. Documente como rodar (e como criar admin/dono).
5. **Roteiro de teste manual** (passo a passo, com a URL real e o que esperar):
   - Admin: `/admin/login` → painel central.
   - Equipe: login no painel, ver os cadastros já populados, criar/editar/inativar
     algo, ver toasts e modais.
   - Cliente: registrar no portal, fazer um agendamento (filial→serviço→
     profissional→dia→horário), ver em "Próximos", cancelar.
   - Agenda da equipe: ver dia/semana, criar manual, mudar status, remarcar.
   - Papéis: logar como Profissional e confirmar que só vê a própria agenda.
   - Dark mode: alternar Claro/Escuro/Sistema.
6. **E-mails.** Como `MAIL_MAILER=log`, explique onde os e-mails "enviados"
   aparecem (`storage/logs/laravel.log`).
7. **Testes automatizados.** Como rodar a suíte (`php artisan test` ou
   `./vendor/bin/pest`) e um resumo do que ela cobre.
8. **Reset seguro dos dados de teste.** Explique como recriar/limpar o cenário de
   demonstração de forma segura — **sem** comandos destrutivos executados por você;
   se algo precisar de DROP, apenas instrua o passo para eu executar manualmente.

## Ao terminar
No relatório, me dê o "começo rápido": a sequência mínima de comandos (subir
serviços → migrar/seed demo → servir) e a URL para abrir agora, mais o usuário/
senha de teste que eu devo criar. Mantenha o guia salvo em `docs/GUIA-DE-TESTES.md`.
