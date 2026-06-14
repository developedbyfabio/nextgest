# Nextgest — Tutorial completo (ligar o servidor e testar tudo)

Guia para quem **não é programador**. Comandos exatos, endereços reais e o que
você deve ver em cada passo. Tudo roda dentro da VM, no diretório do projeto:

```bash
cd /srv/www/nextgest
```

> Endereço para abrir no navegador do seu computador (host): **http://192.168.3.100:8000**
> Dentro da própria VM também vale `http://127.0.0.1:8000`.

---

## Começo rápido (cole e siga agora)

```bash
cd /srv/www/nextgest

# 1. Liga os serviços de apoio (banco e cache)
sudo systemctl start mysql redis-server

# 2. Cria seu login de super-admin (vai pedir nome, e-mail e senha)
php artisan nextgest:criar-admin

# 3. Liga o site
php artisan serve --host=0.0.0.0 --port=8000
```

Agora, no navegador do seu computador, abra **http://192.168.3.100:8000/admin/login**
e entre com o e-mail/senha que você acabou de criar. Pronto para testar.

> Quer pular o cadastro manual? Crie um estabelecimento no /admin e rode
> `php artisan nextgest:demo barbeariadojorge` (ver a Parte 3).

---

## Parte 1 — Ligar e desligar o servidor de teste

### 1.1 Antes de tudo: ligar banco e cache
O sistema precisa do **MySQL** (banco de dados) e do **Redis** (sessão/cache)
ligados:

```bash
sudo systemctl start mysql redis-server
```

Para conferir se estão de pé:

```bash
systemctl is-active mysql redis-server     # deve responder "active" duas vezes
```

### 1.2 Ligar o site (modo simples)

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

O que cada parte significa:
- `php artisan serve` — liga o servidor web embutido do projeto.
- `--host=0.0.0.0` — aceita acesso de **outras máquinas** (o seu computador), não
  só de dentro da VM.
- `--port=8000` — usa a porta 8000 (o endereço fica `...:8000`).

Esse terminal fica **ocupado** mostrando os acessos. Para **desligar**, aperte
**Ctrl + C** nesse terminal. Enquanto ele estiver aberto, o site funciona.

### 1.3 Ligar deixando o terminal livre (segundo plano)
Se você quer continuar usando o mesmo terminal:

```bash
nohup php artisan serve --host=0.0.0.0 --port=8000 > storage/logs/serve.log 2>&1 &
```

- `nohup ... &` — roda "solto", em segundo plano; o site continua mesmo se você
  fechar o terminal.
- A saída vai para o arquivo `storage/logs/serve.log` (pode ignorar).

**Como saber se está rodando:**

```bash
ss -ltnp | grep :8000
```
Se aparecer uma linha com `:8000` e `php`, está no ar. Se não aparecer nada, está
desligado.

**Como desligar (em segundo plano):**

```bash
pkill -f "php artisan serve"
```
Isso encerra **apenas** o servidor do Nextgest. Confirme depois com
`ss -ltnp | grep :8000` (não deve aparecer nada).

> Alternativa segura: descubra o número do processo (PID) na saída do
> `ss -ltnp | grep :8000` (algo como `pid=12345`) e rode `kill 12345`.

### 1.4 Reiniciar (depois de mexer em algo)
- Mexeu no arquivo de configuração **`.env`** ou no código PHP:
  1. desligue o servidor (Ctrl + C, ou `pkill -f "php artisan serve"`);
  2. limpe os caches: `php artisan optimize:clear`;
  3. ligue de novo (passo 1.2).
- Mexeu em **aparência** (telas/cores/CSS): rode `npm run build` e recarregue a
  página no navegador.

---

## Parte 2 — Testar tudo do zero (os 4 acessos)

Existem **quatro tipos de acesso**. Entenda quem é quem:

| Quem | Onde entra | Como obtém a senha | O que faz |
|---|---|---|---|
| **Super-admin** (você, dono do sistema) | `http://192.168.3.100:8000/admin/login` | `php artisan nextgest:criar-admin` | Cria/gerencia estabelecimentos |
| **Dono** (do estabelecimento) | `http://192.168.3.100:8000/{slug}/painel/login` | criado por você no /admin | Cadastra unidade, serviços, equipe, horários; vê a agenda |
| **Funcionário** (profissional) | `http://192.168.3.100:8000/{slug}/painel/login` | definida pelo Dono ao cadastrar | Vê **só a própria agenda** |
| **Cliente** | `http://192.168.3.100:8000/{slug}` | o próprio cliente cria no cadastro | Agenda e cancela horários |

> `{slug}` é o "apelido" do estabelecimento na URL. No exemplo usamos
> `barbeariadojorge`.

### Passo 1 — Super-admin (você)
1. Crie seu login (uma vez):
   ```bash
   php artisan nextgest:criar-admin
   ```
   Informe nome, e-mail e senha quando pedir. (A senha não aparece na tela ao
   digitar — é normal.)
2. Abra **http://192.168.3.100:8000/admin/login** e entre.
   - **Você deve ver:** o painel do super-admin, com o número de estabelecimentos
     e um botão **Gerenciar**.
3. Vá em **Estabelecimentos** → **Novo estabelecimento**. Preencha:
   - Nome: `Barbearia do Jorge`
   - Slug: `barbeariadojorge`
   - Clique **Criar**. Aparece um aviso verde ("Estabelecimento criado").
4. Na linha do estabelecimento, clique **Criar dono** e informe nome, e-mail e
   senha do dono (ex.: `jorge@barbearia.com` / uma senha à sua escolha).
5. (Opcional) Clique **Detalhes** para ver o resumo (contagens e donos) e o botão
   **Entrar no painel (suporte)** — ele te loga como o Dono para inspecionar, com
   uma faixa amarela **"Modo suporte"** no topo e um botão **Sair do suporte**.

### Passo 2 — Dono da barbearia
1. Abra **http://192.168.3.100:8000/barbeariadojorge/painel/login** e entre com o
   login do Dono criado no passo anterior.
   - **Você deve ver:** o painel com o menu lateral (Início, Agendamentos,
     Serviços, Bloqueios, Unidades, Equipe, Papéis…).
2. Cadastre, nesta ordem (menu lateral):
   - **Unidades** → *Nova unidade* (ex.: "Matriz Centro"). Salve.
   - **Serviços** → *Novo serviço* (ex.: "Corte", 30 min, R$ 45). Marque a unidade.
   - **Equipe** → *Novo membro*: nome, e-mail, papel, **senha inicial**; ligue
     **"É profissional"** e marque os **serviços** que ele faz (e a unidade).
   - Na linha do profissional, clique **Horários** e adicione as faixas de
     trabalho (ex.: Segunda 09:00–12:00 e 13:00–18:00). Salve.

> Atalho: em vez de cadastrar tudo à mão, rode `php artisan nextgest:demo
> barbeariadojorge` (Parte 3) para já vir tudo pronto.

### Passo 3 — Funcionário (profissional)
1. Se ainda não criou, cadastre um membro com papel **Profissional** e uma senha
   (Equipe → Novo membro).
2. Abra **http://192.168.3.100:8000/barbeariadojorge/painel/login** e entre com o
   e-mail/senha desse profissional.
   - **Você deve ver:** ele cai direto na **Agenda**, mostrando **apenas os
     próprios** agendamentos (sem o filtro de "profissional", que só aparece para
     gerentes/dono).

### Passo 4 — Cliente final
1. Abra **http://192.168.3.100:8000/barbeariadojorge**.
   - **Você deve ver:** a página do estabelecimento com "Criar conta e agendar".
2. Clique **Criar conta e agendar**, preencha nome, telefone, e-mail e senha.
3. Já logado, clique **Novo agendamento** e siga: **serviço → profissional (ou
   "sem preferência") → dia → horário → Confirmar**.
   - **Você deve ver:** o agendamento em **"Próximos agendamentos"**.
4. Para testar o cancelamento, clique **Cancelar** num agendamento futuro (ele
   respeita a antecedência mínima de 2 horas).

---

## Parte 3 — Atalho com dados de demonstração

Para não cadastrar tudo na mão, depois de **criar o estabelecimento** (Passo 1):

```bash
php artisan nextgest:demo barbeariadojorge
```

Isso cria, no estabelecimento: 1 unidade, 5 serviços, 3 profissionais (com
serviços e horários), um Gerente, uma Recepção, 3 clientes e vários agendamentos
de exemplo.

**Logins de demonstração (senha `password` para todos):**

| Papel | E-mail |
|---|---|
| Gerente | `gerente@demo.test` |
| Recepção | `recepcao@demo.test` |
| Profissional | `jorge@demo.test` (ou `ana@demo.test`, `bruno@demo.test`) |
| Cliente | `maria@cliente.test` (ou `carlos@cliente.test`, `paula@cliente.test`) |

Use, por exemplo, `gerente@demo.test` / `password` em
`http://192.168.3.100:8000/barbeariadojorge/painel/login` para já entrar com tudo
populado. (O **Dono** continua sendo criado por você — pelo /admin ou pelo
comando `nextgest:criar-dono barbeariadojorge`.)

> O comando pode ser rodado de novo sem duplicar nada.

---

## Parte 4 — Dicas de teste

### Ver erros / logs
- Em modo de teste o `.env` já está com `APP_DEBUG=true`, então os erros aparecem
  na própria tela (com detalhes).
- O histórico de erros fica no arquivo de log:
  ```bash
  tail -n 50 storage/logs/laravel.log
  ```

### Tema claro/escuro
No painel (ou no /admin), clique no seu **nome/avatar** (canto da tela) → **Tema**
→ escolha **Claro**, **Escuro** ou **Sistema**.

### "Página expirou" (erro 419)
Isso já foi corrigido. Se mesmo assim aparecer:
1. Confirme no `.env` (modo de teste) que estão assim:
   ```
   APP_URL=http://192.168.3.100:8000
   SESSION_SECURE_COOKIE=false
   ```
2. Rode `php artisan optimize:clear`.
3. Recarregue a página no navegador (se persistir, limpe os cookies do site ou
   abra uma aba anônima).

### Acessar do seu computador
Se a página não abrir no host, confirme que o servidor foi ligado com
`--host=0.0.0.0` (Parte 1.2) e que a porta está liberada:
```bash
sudo ufw allow 8000/tcp     # ambiente de teste; para remover depois: sudo ufw delete allow 8000/tcp
```

---

## Quadro-resumo (cola rápida)

```bash
# Ligar
sudo systemctl start mysql redis-server
php artisan serve --host=0.0.0.0 --port=8000        # Ctrl+C para desligar

# Criar acessos
php artisan nextgest:criar-admin                     # super-admin (/admin)
php artisan nextgest:criar-dono barbeariadojorge     # dono do estabelecimento
php artisan nextgest:demo barbeariadojorge           # dados de demonstração

# Endereços
http://192.168.3.100:8000/                           # site (landing)
http://192.168.3.100:8000/admin/login                # super-admin
http://192.168.3.100:8000/barbeariadojorge           # cliente (portal)
http://192.168.3.100:8000/barbeariadojorge/painel/login   # dono / equipe
```

Mais detalhes técnicos: `docs/GUIA-DE-TESTES.md`.
