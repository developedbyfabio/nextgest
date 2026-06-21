# Nextgest — Prompt: tutorial completo para leigo (ligar servidor + testar tudo)

> Rodar **depois** do prompt de correção (login 419 / landing). Cole para o Claude
> root em `/srv/www/nextgest`. Produza `docs/TUTORIAL-COMPLETO.md` em pt-BR,
> escrito para um **leigo**, com comandos exatos, URLs reais e o que esperar ver
> em cada passo. Sem comandos destrutivos.

---

## Parte 1 — Ligar e desligar o servidor de teste (php artisan)
Explique, passo a passo e sem jargão:
- **Ligar** o servidor: o comando exato (`php artisan serve --host=0.0.0.0
  --port=8000`), o que cada parte significa em uma linha, e que ele fica
  "ocupando" aquele terminal.
- **Ligar deixando livre o terminal** (segundo plano): como rodar em background
  (ex.: `nohup ... &` ou via outra sessão/`tmux`), **como saber se está rodando**
  (`ss -ltnp | grep 8000` ou similar) e **como desligar** (encontrar e encerrar o
  processo de forma segura, sem matar nada que não seja o serve).
- **Reiniciar** (quando mexer no `.env` ou no código): parar e ligar de novo, e
  quando rodar `php artisan optimize:clear` / `npm run build`.
- Lembrar de iniciar MySQL e Redis antes (`sudo systemctl start mysql redis-server`).

## Parte 2 — Testar tudo do zero (os 4 acessos), na ordem
Roteiro guiado, com URL e o que esperar em cada tela. Deixe MUITO claro **quem é
quem** e **onde cada um entra**:

1. **Super-admin (você, dono do sistema)** — `/admin`
   - Criar o login: `php artisan nextgest:criar-admin`.
   - Entrar em `http://192.168.3.100:8000/admin/login`.
   - Criar um estabelecimento (ex.: "Barbearia do Jorge", slug `barbeariadojorge`).
   - Criar o **Dono** desse estabelecimento (e-mail + senha) na linha dele.
   - (Se existir) usar o "Entrar no painel" / impersonação para inspecionar.

2. **Dono da barbearia** — `/{slug}/painel`
   - Entrar em `http://192.168.3.100:8000/barbeariadojorge/painel/login` com o
     login criado no passo 1 (ou usar `nextgest:demo {slug}` para já vir tudo
     populado — explicar as duas opções).
   - Cadastrar: unidade → serviços → funcionários (marcar "é profissional",
     vincular serviços) → horários de trabalho.

3. **Funcionário (profissional)** — `/{slug}/painel`
   - Criar um funcionário com papel Profissional e senha, entrar com ele e
     confirmar que vê **só a própria agenda**.

4. **Cliente final** — `/{slug}`
   - Abrir `http://192.168.3.100:8000/barbeariadojorge`, **criar conta**, fazer um
     **agendamento** (serviço → profissional → dia → horário), ver em "Próximos" e
     **cancelar**.

Para cada acesso: a URL, como obter/definir a senha, e a tela que deve aparecer.
Inclua um quadro-resumo dos 4 acessos (quem, URL, como entra, o que faz).

## Parte 3 — Atalho com dados de demonstração
Explique o caminho rápido: criar o tenant, rodar `php artisan nextgest:demo
{slug}` e já entrar testando com os logins de demonstração (senha `password`),
sem cadastrar tudo na mão.

## Parte 4 — Dicas de teste
- Como ver erros (modo local) e onde ficam os logs.
- Como alternar o tema (claro/escuro).
- O que fazer se aparecer "página expirou" (já corrigido) — como limpar cache/
  recarregar.

## Ao terminar
Salvar em `docs/TUTORIAL-COMPLETO.md` e, no relatório, colar o "começo rápido"
(ligar serviços → servir → criar admin → criar tenant → criar dono → URLs) para eu
seguir agora.
