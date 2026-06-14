# 09 — Cadastros do dono (fatia 1B)

Cadastros que o agendamento precisa, no painel da equipe: unidades, serviços,
equipe (profissionais), vínculos e horários de trabalho — tudo gateado por
permissões (spatie) no servidor.

## Telas / rotas (sob `/{tenant}/painel`, guard `web`)

| Tela | Rota (nome) | Permissão (página) |
|---|---|---|
| Unidades | `painel.unidades` | `gerir_unidades` |
| Serviços | `painel.servicos` | `editar_servico` |
| Equipe | `painel.equipe` | `editar_usuario` |
| Horários do profissional | `painel.equipe.horarios` | `editar_usuario` |
| Papéis e permissões | `painel.papeis` | `editar_permissoes` |

A navegação na sidebar só mostra o item se o usuário tiver a permissão (`@can`).
As páginas são protegidas por middleware `can:` (servidor) e as ações de
criar/editar/inativar são **reconferidas** dentro de cada componente Livewire
(`$this->authorize(...)`), não só escondendo botões.

## Permissões (atualização do seed)

Adicionadas em `TenantDatabaseSeeder`: `gerir_unidades` e `editar_usuario`
(além das já existentes `criar_servico`/`editar_servico`, `criar_usuario`,
`editar_permissoes`). Distribuição padrão:
- **Dono**: todas.
- **Gerente**: todas menos `ver_financeiro` e `editar_permissoes` (gerencia
  unidades, serviços e equipe).
- **Recepção**: agenda, clientes, vendas, kanban (não gerencia cadastros).
- **Profissional**: `ver_agenda_propria`.

> Tenants já existentes precisam de re-seed: `php artisan tenants:seed --tenants={slug}`.

## Regras aplicadas

- **Inativar, não excluir**: "excluir" seta `ativo=false` em unidades, serviços e
  equipe. Nada é apagado fisicamente. Reativação disponível.
- **Multi-unidade**: com 1 unidade ativa, a seleção de filial é omitida e a
  unidade já vem vinculada; com 2+, aparecem os checkboxes de vínculo.
- **Equipe**: papel (Dono/Gerente/Recepção/Profissional ou customizado),
  `e_profissional`, senha inicial definida pelo gestor, vínculo de unidades
  (`user_unidade`); para profissionais, serviços que executa (`servico_user`).
  Não é possível inativar a própria conta.
- **Horários**: por profissional e unidade, faixas semanais com **múltiplas
  faixas por dia** (ex.: 09:00–12:00 e 13:00–18:00 para o almoço). Valida fim >
  início. Salvar substitui o conjunto de faixas do profissional.
- **Papéis**: editar permissões de cada papel e criar papéis personalizados. O
  papel **Dono** mantém todas as permissões automaticamente (evita lockout).

## Models e relações (banco do tenant)

- `App\Models\Unidade` — `servicos()`, `users()` (N:N).
- `App\Models\Servico` — `unidades()`, `profissionais()` (N:N).
- `App\Models\HorarioTrabalho` — `user()`, `unidade()`.
- `App\Models\User` — `unidades()`, `servicos()`, `horariosTrabalho()`.

## Como cadastrar um profissional completo no `barbeariateste`

1. `php artisan nextgest:criar-dono barbeariateste` (defina nome/e-mail/senha) e
   entre em `/barbeariateste/painel/login`.
2. **Unidades** → crie ao menos uma unidade.
3. **Serviços** → crie os serviços (marque as unidades onde são oferecidos).
4. **Equipe** → **Novo membro**: nome, e-mail, papel **Profissional**, senha
   inicial, marque **É profissional** e selecione os serviços que ele executa
   (e as unidades, se houver mais de uma).
5. Na linha do profissional, **Horários** → adicione faixas por dia (ex.: manhã
   e tarde) e salve.

## Testes

`tests/Feature/Painel/` (Pest): CRUDs felizes e de validação; inativação (não
exclui); horários com múltiplas faixas e validação de intervalo; e checagem de
permissão (Profissional/Recepção recebem **403**; Gerente sem `editar_permissoes`
na tela de papéis). 42 testes no total (1A + 1B).

```bash
php artisan test
```

## Fora de escopo (1C)

- Bloqueios pontuais (folga/feriado) e o **cálculo de disponibilidade**, junto do
  fluxo de agendamento.
- Convite por e-mail da equipe (requer `MAIL_*`).
