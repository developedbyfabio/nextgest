# Nextgest — Prompt: corrigir login (419), landing e visibilidade do super-admin

> Cole para o Claude root em `/srv/www/nextgest`. Ambiente de teste local
> (VM, http, sem DNS/SSL). Sem comandos destrutivos; manter a suíte verde; seguir
> o padrão de UI/UX (D27). Ver [[Gotchas e Aprendizados do Projeto]].

---

## Problema 1 (CRÍTICO) — Login de tenant dá 419 "This page has expired"
Sintoma: em `/{slug}/painel/login` e `/{slug}/login`, ao enviar o login, aparece
"This page has expired. Would you like to refresh?" (erro 419 CSRF) e não loga.
O `/admin/login` (central) funciona.

### Diagnóstico provável
O ambiente está como produção com https, mas o teste é em **http** na LAN:
- `APP_ENV=production`, `APP_URL=https://nextgest.com.br`.
- Se o cookie de sessão estiver como **Secure** (https-only), o navegador não o
  envia sobre http → a sessão se perde a cada request → o token CSRF não bate →
  419. Pode afetar mais o tenant por causa do escopo de sessão por caminho.

### Correção
Crie um **ambiente de teste que funcione sobre http** (sem afrouxar a segurança
de produção — produção continua https):
- `APP_ENV=local`, `APP_DEBUG=true`, `APP_URL=http://192.168.3.100:8000`.
- Garantir `SESSION_SECURE_COOKIE=false` no teste; conferir `SESSION_DOMAIN=null`
  e o escopo de sessão por tenant não invalidando o CSRF.
- `php artisan optimize:clear`.
- Documentar como voltar para produção (https, secure cookie on).
Validar: login como **Dono/equipe** (`/{slug}/painel/login`) e como **cliente**
(`/{slug}/login` e cadastro) funciona sem 419, em mais de um tenant.

## Problema 2 — Landing central `/`
Hoje mostra a página padrão do Laravel. Substitua por uma **landing simples da
marca Nextgest** (apresentação do produto + CTA para conhecer/contato), no padrão
de UI. Não precisa ser grande, mas não pode ser a tela do Laravel.

## Problema 3 — Portal público do tenant (deslogado)
A home `/{slug}` deslogada está vazia/estranha (card meio em branco). Deixe-a
completa e bonita (mobile-first): nome/identidade do estabelecimento, uma breve
apresentação e o bloco de **entrar/criar conta para agendar** bem posicionado.

## Problema 4 — Super-admin enxergar os estabelecimentos
Como dono do SaaS, hoje só vejo a lista. Esclareça (no painel e no doc) que os
dados operacionais (clientes/funcionários/agenda) são **privados de cada
estabelecimento** e adicione:
1. **Detalhe do estabelecimento** (`/admin/estabelecimentos/{tenant}`): resumo de
   alto nível — donos/usuários da equipe, contagens (funcionários, clientes,
   serviços, agendamentos), status, data de criação.
2. **Entrar no painel do estabelecimento** (impersonação para suporte): botão que
   me loga como o Dono daquele tenant, com um indicador visível de "modo suporte"
   e registro do acesso, e um jeito de sair da impersonação. Assim eu inspeciono
   sem ter que decorar logins.

## Restrições
- Sem comandos destrutivos; inativar ≠ excluir. Só `admin` acessa o /admin.
- Impersonação registrada e claramente sinalizada na tela.
- Testes (Pest) para: login de tenant sem 419; detalhe do tenant; impersonação
  (entra e sai, e respeita permissões). Suíte toda verde.

## Ao terminar
Reportar: causa real do 419 e o que mudou no `.env`/config; confirmação de login
OK como dono, funcionário e cliente; a nova landing; o detalhe + impersonação no
/admin.
