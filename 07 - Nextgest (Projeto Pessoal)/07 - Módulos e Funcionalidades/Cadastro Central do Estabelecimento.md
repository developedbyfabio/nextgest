---
projeto: Nextgest
tipo: módulo
status: implementado (3a captura + 3b tela Dados)
criado: 2026-06-25
tags: [nextgest, central, estabelecimento, cobranca, onboarding, validadores]
---

# Cadastro Central do Estabelecimento

> Projeto: [[Nextgest - Visão Geral]] · Decisão: [[Decisões de Arquitetura]] (**D56**) ·
> Fundação para faturamento/admin. Ver [[Mapeamento Central x Tenant (auditoria pré-planos)]],
> [[Onboarding Guiado de Estabelecimento]] e [[Painel Super-Admin (Central)]].

## Por quê
O admin/cobrança precisa ler os dados cadastrais **sem entrar no banco de cada tenant**. Criamos uma
tabela **central** `estabelecimentos` (1:1 com `tenants`) como fonte de verdade. O **login do dono
continua no tenant** (`users`); aqui guardamos o contato completo do dono + dados do negócio.

## Tabela `estabelecimentos` (central)
- `id`, `tenant_id` (string, FK → `tenants.id`, **unique**, cascade on delete), timestamps.
- **Estabelecimento:** `nome_fantasia`, `cep`, `logradouro`, `numero`, `complemento`, `bairro`,
  `cidade`, `uf`, `faturamento_mensal` (decimal nullable), `documento_tipo` (`cpf`|`cnpj`),
  `documento`.
- **Contato do dono:** `dono_nome`, `dono_sobrenome`, `dono_email`, `dono_celular`, `dono_cpf`.
- Quase tudo **nullable**: a obrigatoriedade é do **formulário** (onboarding), não do banco — assim a
  criação rápida e a 3b (preencher depois) não esbarram em NOT NULL.
- Documentos/celular/CPF/CEP gravados **só com dígitos** (`Estabelecimento::soDigitos()`).

Migration: `database/migrations/2026_06_25_120001_create_estabelecimentos_table.php` (CENTRAL —
roda com `php artisan migrate`, **não** `tenants:migrate`).

## Model
`App\Models\Estabelecimento` — usa o trait **`CentralConnection`** do stancl (pina a conexão central:
mesmo consultado dentro de um tenant, lê/grava no central). `Tenant::estabelecimento()` (hasOne) —
**pode ser null** em tenants antigos (criados antes da 3a; a 3b cria sob demanda).

## Validadores BR (in-house)
Sem pacote novo (rede de build restrita). Implementam `ValidationRule`, aceitam máscara e normalizam:
- `App\Rules\Cpf` — 11 dígitos + 2 verificadores; rejeita repetidos.
- `App\Rules\Cnpj` — 14 dígitos + 2 verificadores (pesos 5..2 / 6..2).
- `App\Rules\CelularBr` — DDD (11–99) + 8 ou 9 dígitos; para 11 dígitos exige o 9.

## Onde é gravado
- **Onboarding** (`OnboardingEstabelecimento::confirmar`): cria o tenant + Dono (no tenant) e o registro
  central com todos os campos capturados. Ver [[Onboarding Guiado de Estabelecimento]] (7 etapas).
- **Criação rápida** (`Tenants::criar`): central mínimo (`tenant_id` + `nome_fantasia`). `criarDono`
  faz **backfill** leve do contato (só campos vazios).

## Observação (fora de escopo da 3a)
O e-mail de login mora em 2 lugares: `users.email` (tenant) e `dono_email` (central). Se o login mudar
depois, pode divergir — reconciliação fica para uma fase futura.

## Tela "Dados" (3b — D57, implementada)
`App\Livewire\Admin\EstabelecimentoDados` (rota **`admin.tenant.dados`** =
`/admin/estabelecimentos/{tenantId}/dados`, `auth:admin`). Lê/edita o registro central; usa
**`firstOrNew(['tenant_id' => $id])`** no `mount` e no `salvar`, então:
- tenant **com** registro → abre preenchido (máscaras formatam os dígitos guardados);
- tenant **antigo sem** registro → abre vazio e a linha é **criada sob demanda** no 1º save (não
  duplica — `tenant_id` é unique); reabrir e salvar atualiza a mesma linha.
Salva **normalizado** (`soDigitos`). Botão **"Dados"** em cada linha da lista (`Tenants`) + atalho no
`TenantDetalhe`. **Nota na UI:** edita o contato CADASTRAL — o e-mail/senha de **login** do dono são
gerenciados à parte (no tenant); editar aqui não altera o acesso.

## Testes
- `tests/Feature/Admin/EstabelecimentoTest.php` (validadores; captura central no onboarding; etapa
  Estabelecimento exige nome fantasia + valida documento; criação rápida; backfill; relação/normalização).
- `tests/Feature/Admin/EstabelecimentoDadosTest.php` (guard; carrega existente; edita/persiste
  normalizado; **cria sob demanda** sem duplicar; barra CPF/celular inválidos; valida documento; botão
  na lista).
Validado no dev: `fase3ademo` (com registro) e `barbeariateste` (antigo, criado pela tela). Suíte
**497/497**. **Dev apenas — sem deploy.**
