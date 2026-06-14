# Guia de testes — rodar e testar o Nextgest localmente

Passo a passo para subir e testar o sistema nesta máquina, do zero ao clique.
Diretório do projeto: `/srv/www/nextgest`. Comandos rodam de lá.

> Sem segredos aqui: as senhas dos **logins de demonstração** são locais e de
> teste (padrão `password`). Nunca use isso em produção.

---

## 0. Começo rápido (TL;DR)

```bash
cd /srv/www/nextgest

# 1. Serviços de apoio (se não estiverem ativos)
sudo systemctl start mysql redis-server

# 2. Migrações centrais (idempotente) e dados de demonstração no tenant
php artisan migrate --force
php artisan nextgest:demo barbeariateste

# 3. Crie seu super-admin e seu Dono (você define a senha no prompt)
php artisan nextgest:criar-admin
php artisan nextgest:criar-dono barbeariateste

# 4. Suba o app
php artisan serve --host=0.0.0.0 --port=8000
```

Abra no navegador:

- **Portal do cliente:** http://192.168.3.100:8000/barbeariateste
- **Painel da equipe:** http://192.168.3.100:8000/barbeariateste/painel/login
- **Admin (central):** http://192.168.3.100:8000/admin/login

Logins de demonstração (senha **`password`**):

| Papel | E-mail |
|---|---|
| Gerente | `gerente@demo.test` |
| Recepção | `recepcao@demo.test` |
| Profissional | `jorge@demo.test` (ou `ana@demo.test`, `bruno@demo.test`) |
| Cliente (portal) | `maria@cliente.test` (ou `carlos@`, `paula@cliente.test`) |

O **super-admin** (`/admin`) e o **Dono** do painel são criados por você nos
comandos do passo 3 (senha que você digitar).

> A VM é acessada pelo host em `192.168.3.100`. Dentro da própria VM,
> `127.0.0.1`/`localhost` também valem. Detalhes na seção 3.1.

---

## 1. Modo de desenvolvimento (ver erros) x produção

Hoje o `.env` está com `APP_ENV=production` e `APP_DEBUG=false` — bom para
"produção", mas **esconde erros** na tela. Para testar confortavelmente:

**Ativar modo local (mostra erros detalhados):** edite `.env`:

```
APP_ENV=local
APP_DEBUG=true
```

Depois limpe os caches de config:

```bash
php artisan optimize:clear
```

**Voltar para produção:** reverta para `APP_ENV=production` e `APP_DEBUG=false` e
rode `php artisan optimize:clear` de novo.

**Mantendo produção?** Os erros não aparecem na tela; veja no log:

```bash
tail -f storage/logs/laravel.log
```

---

## 2. Serviços necessários

A sessão, o cache e a fila usam **Redis**; os dados ficam no **MySQL**.

```bash
# Conferir
systemctl is-active mysql redis-server

# Iniciar, se preciso
sudo systemctl start mysql redis-server
```

**Fila:** a criação de tenant e os fluxos atuais rodam de forma **síncrona** — não
é preciso worker para testar. Se no futuro algo for para a fila (QUEUE via Redis),
rode em outro terminal:

```bash
php artisan queue:work
```

---

## 3. Como servir e acessar

O Nginx de produção **não** está habilitado (sem DNS/SSL). Para testar, use o
servidor embutido:

```bash
php artisan serve --host=0.0.0.0 --port=8000
# Servindo em http://192.168.3.100:8000
```

URLs reais:

- **Central** (SaaS): http://192.168.3.100:8000/ (landing) e
  http://192.168.3.100:8000/admin/login (super-admin).
- **Tenant** (estabelecimento): tudo sob o **slug** na URL —
  http://192.168.3.100:8000/barbeariateste (portal do cliente) e
  http://192.168.3.100:8000/barbeariateste/painel (painel da equipe).

Ou seja: **`/admin` é o painel central do SaaS**; **`/{slug}` é o
estabelecimento** (multi-tenant por caminho). Os domínios centrais reconhecidos
incluem `192.168.3.100`, `localhost` e `127.0.0.1`, então o slug funciona direto.

> **Dentro da VM** (terminal/navegador da própria VM), `http://127.0.0.1:8000`
> e `http://localhost:8000` também valem. **Do navegador do host**, use o IP da
> VM: `http://192.168.3.100:8000`.

### 3.1 Acesso pelo navegador do host (VM VirtualBox)

A VM tem IP `192.168.3.100` (adaptador **Bridge** ou **Host-only**). Para o host
enxergar o app:

- Servir em todas as interfaces (não só no loopback):
  `php artisan serve --host=0.0.0.0 --port=8000`.
- Liberar a porta na `ufw` (ambiente de teste em LAN):
  `sudo ufw allow 8000/tcp` — para remover depois: `sudo ufw delete allow 8000/tcp`.
- Abrir no host: `http://192.168.3.100:8000`.

> Se o adaptador da VM fosse **NAT** (sem IP de LAN), a alternativa seria
> **port forwarding** no VirtualBox (host 8000 → guest 8000) e acessar por
> `http://127.0.0.1:8000` no host.

---

## 4. Dados de demonstração

O comando popula um cenário realista no tenant (idempotente — pode rodar de novo
sem duplicar):

```bash
php artisan nextgest:demo barbeariateste
# senha de login customizável: --senha=outrasenha
```

Cria: **1 unidade** (Matriz Centro), **5 serviços** (Corte, Barba, Corte+Barba,
Sobrancelha, Coloração), **3 profissionais** com serviços e horários (seg–sex
09–12 e 13–18, sáb 09–13), **Gerente** e **Recepção** de apoio, **3 clientes** e
**7 agendamentos** em status variados (confirmado, pendente, em_andamento,
concluído, cancelado, não compareceu).

Para criar os logins de acesso (você define a senha):

```bash
php artisan nextgest:criar-admin                 # super-admin do /admin
php artisan nextgest:criar-dono barbeariateste   # Dono do painel da equipe
```

### Criar estabelecimentos pelo /admin

Logado no `/admin` → **Estabelecimentos**: liste, **crie** (nome + slug, com
validação de slug único/formato/reservado), **ative/inative** (sem apagar banco),
**crie o Dono** inicial e **abra** o estabelecimento (`/{slug}`). Criar pelo
painel já provisiona o banco/migrations/seed do tenant.

Pela linha de comando (alternativa), para testar isolamento com outro tenant:

```bash
php artisan tinker --execute="App\Models\Tenant::create(['id'=>'salaodaana','nome'=>'Salão da Ana','slug'=>'salaodaana','ativo'=>true]);"
php artisan nextgest:demo salaodaana
```

---

## 5. Roteiro de teste manual

> Dica: ative o **modo local** (seção 1) para ver erros enquanto explora.

### 5.1 Admin (central)
1. http://192.168.3.100:8000/admin/login → entre com o super-admin que você criou.
2. Cai no painel central (`/admin`) com o total de estabelecimentos.
3. Menu do perfil → **Sair**. Tente abrir `/admin` deslogado → redireciona ao login.

### 5.2 Equipe (painel)
1. http://192.168.3.100:8000/barbeariateste/painel/login → entre como **Dono**
   (criado por você) ou **Gerente** (`gerente@demo.test` / `password`).
2. Navegue por **Unidades, Serviços, Equipe, Bloqueios, Papéis** — já vêm
   populados. Crie/edite um serviço (modal + toast), **inative** e **reative**.
3. Em **Equipe → (profissional) → Horários**, veja as faixas (almoço entre elas).

### 5.3 Cliente (portal)
1. http://192.168.3.100:8000/barbeariateste → **Criar conta** (ou entre como
   `maria@cliente.test` / `password`).
2. **Novo agendamento**: serviço(s) → profissional (ou "sem preferência") → dia →
   horário → **Confirmar**. Veja em **Próximos agendamentos** e **cancele** um
   (respeita a antecedência mínima de 2h).

### 5.4 Agenda da equipe
1. No painel, **Agendamentos**: alterne **Dia/Semana**, navegue nas datas, filtre.
2. **Novo agendamento** (manual): busque/cadastre um cliente e siga o wizard.
3. Clique num agendamento → slide-over: **mude o status**, **remarque** e veja o
   horário liberar ao cancelar.

### 5.5 Papéis (escopo por profissional)
1. Entre como **`jorge@demo.test`** (`password`). Ele cai direto na **agenda** e
   vê **só os próprios** agendamentos (sem o filtro de profissional).
2. Compare com **Gerente/Recepção**, que veem a agenda de todos.

### 5.6 Dark mode
No menu de perfil (painel/admin) → **Tema**: alterne **Claro / Escuro / Sistema**.

---

## 6. E-mails

`MAIL_MAILER=log`: nenhum e-mail sai de verdade. O conteúdo "enviado" é gravado
em:

```bash
tail -n 100 storage/logs/laravel.log
```

(Recuperação de senha por e-mail ainda não está ativa — depende de configurar
`MAIL_*` para um SMTP real.)

---

## 7. Testes automatizados

```bash
php artisan test          # ou ./vendor/bin/pest
```

Cobrem (74 testes): autenticação dos 3 guards e isolamento entre tenants;
cadastros (unidades, serviços, equipe, horários, papéis) com checagem de
permissão (403); motor de disponibilidade (janelas, almoço, bloqueios, passado,
"sem preferência"); agendador (snapshots, status, **concorrência/anti-duplicidade**,
cancelamento); portal (agendar, cancelar, ownership); agenda da equipe (escopo
por papel, status, remarcar, agendamento manual).

> Os testes usam SQLite em memória (não tocam o MySQL local).

---

## 8. Reset seguro dos dados de teste

- **Refazer só os agendamentos de demo** (não toca em dados reais):

  ```bash
  php artisan nextgest:demo barbeariateste --recriar
  ```

- **Repopular o catálogo** (idempotente): rode `php artisan nextgest:demo
  barbeariateste` de novo — não duplica.

- **Zerar completamente um tenant** (recriar do zero): isso exige **apagar o banco
  do tenant**, uma operação **destrutiva** que deve ser executada **por você**,
  manualmente, com cuidado. Um agente não executa isso. Passo a passo:

  1. Confira o que vai apagar:
     `mysql -e "SHOW TABLES FROM tenant_barbeariateste;"`
  2. Apague o registro central e o banco do tenant (revise antes de rodar):
     `php artisan tinker --execute="App\Models\Tenant::find('barbeariateste')?->delete();"`
     — ao excluir o tenant, o stancl remove o banco `tenant_barbeariateste`.
  3. Recrie e repopule:
     `php artisan tinker --execute="App\Models\Tenant::create(['id'=>'barbeariateste','nome'=>'Barbearia Teste','slug'=>'barbeariateste','ativo'=>true]);"`
     `php artisan nextgest:demo barbeariateste`

  > Nunca use `migrate:fresh`/`db:wipe` no banco central — apagaria todos os
  > tenants. Operações destrutivas são sempre manuais e suas.
