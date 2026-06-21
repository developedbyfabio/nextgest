# Nextgest — Prompt Dev 1A: Autenticação + Layout base

> Cole para o Claude root do servidor, em `/srv/www/nextgest`. Continua o build já
> concluído. Trabalhe em commits pequenos e reporte ao fim. Sem modo tutorial:
> entregue no maior padrão de qualidade. Ver [[Decisões de Arquitetura]] (D24, D25).

---

## Contexto (já existente)
- Laravel 13, Livewire 4, stancl/tenancy (path-based), spatie/permission, MySQL,
  Flux a instalar. Guards já configurados: `web` (equipe), `cliente`, `admin`.
- Tenant de teste: `barbeariateste` (banco `tenant_barbeariateste`), com papéis e
  `confirmacao_automatica=1` semeados.

## Objetivo da 1A
Login/logout funcionando para os três guards, com layout base usando Flux, e o
portal do cliente já mobile-first. Sem regras de agendamento ainda.

## Decisões fixas
- Autenticação **sob medida por guard** (sem starter kit).
- UI com **Flux** + Tailwind v4. Portal do cliente mobile-first; painel da equipe
  responsivo (sidebar como na referência).

---

## Regras de segurança (mantêm-se)
- Nenhum comando destrutivo autônomo; nenhum segredo hardcoded (senhas via
  comando artisan/operador, nunca no código ou git).
- Throttle de login (ex.: 5 tentativas/min por IP+email), hashing forte,
  mensagens de erro genéricas (sem revelar se o e-mail existe), `session()->regenerate()`
  no login, CSRF ativo (webhooks já isentos).

## Estrutura de rotas
- **Central:** `/` (landing simples), `/admin/login` e `/admin` (guard `admin`),
  `/webhooks/...`.
- **Tenant `{tenant}`:**
  - Portal do cliente (guard `cliente`): `/{tenant}` (home), `/{tenant}/login`,
    `/{tenant}/registrar`, `/{tenant}/sair`.
  - Painel da equipe (guard `web`): `/{tenant}/painel/login`, `/{tenant}/painel`
    (dashboard placeholder), `/{tenant}/painel/sair`.
- Middleware redireciona não autenticado para o login correto da área.

## Tarefas
1. **Flux + layouts:** instalar Flux; criar dois layouts Blade/Livewire — painel
   (sidebar: Início, Agendamentos, Serviços, Equipe, etc. como placeholders) e
   portal do cliente (mobile-first, navegação simples). Tema neutro, marca
   "Nextgest"; logo do tenant fica para depois.
2. **Auth equipe (web):** componente Livewire de login (email+senha), dentro do
   contexto do tenant; após login vai para `/{tenant}/painel`.
3. **Auth cliente:** registro (nome, telefone obrigatório, e-mail obrigatório p/
   login, senha) e login por e-mail; após login vai para `/{tenant}`.
4. **Auth admin (central):** login em `/admin/login`, vai para `/admin`.
5. **Logout** nas três áreas.
6. **Comandos de bootstrap (sem segredo no código):**
   - `php artisan nextgest:criar-admin` — cria super-admin central (senha definida
     pelo operador na execução).
   - `php artisan nextgest:criar-dono {tenant}` — cria usuário com papel Dono no
     tenant.
7. **Testes (Pest/PHPUnit):** login ok/erro por guard; registro+login do cliente;
   isolamento (cliente não acessa `/painel`; login de um tenant não vale em
   outro; admin separado).
8. **Docs:** atualizar `docs/` com a 1A (rotas, guards, comandos).

## Fora de escopo (próximas sub-fatias)
- Recuperação de senha por e-mail (precisa configurar mail) — deixar nota.
- Verificação de e-mail.
- Qualquer CRUD de cadastro (vem na 1B) e o fluxo de agendamento (1C).

## Suposições a confirmar (não bloqueiam)
- Login do cliente por **e-mail** (poderia ser por telefone/OTP no futuro).
- Landing central mínima (placeholder) por enquanto.

## Ao terminar
Reportar: rotas criadas, como criar admin e dono, como testar o login no tenant
`barbeariateste`, e o que ficou fora de escopo.

---

## Plano das próximas sub-fatias
- **1B** — Cadastros do dono: unidade, serviços, profissionais, vínculo
  serviço↔profissional, horários de trabalho.
- **1C** — Portal do cliente: agendar (filial → serviço → profissional → horário)
  com validação de conflito de horário.
- **1D** — Agenda da equipe: visualizar e mudar status dos agendamentos.
