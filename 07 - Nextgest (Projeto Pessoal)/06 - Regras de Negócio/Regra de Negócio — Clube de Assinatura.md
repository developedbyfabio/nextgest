---
projeto: Nextgest
tipo: regra-de-negócio
status: definitiva
criado: 2026-06-24
tags: [nextgest, clube, assinatura, cobertura, beneficiarios, regra-de-negocio]
---

# Regra de Negócio — Clube de Assinatura

> Fonte de verdade da regra do Clube (decisão do Fabio). Vale para o módulo do Clube e para
> o **agendamento de beneficiário** (próximo prompt). Implementação: [[Clube de Assinatura (Fase A)]]
> + cobertura/beneficiários ([[Decisões de Arquitetura#D44]]).

## REGRA DE NEGÓCIO DO CLUBE (definitiva)

- **Gateway de pagamento serve SÓ para duas cobranças recorrentes:** (1) o **clube** cobra o
  **assinante**; (2) o **Nextgest** cobra o **dono do tenant** (assinatura do sistema). **Nada
  mais** passa por gateway.
- **Serviços e produtos têm preço fixo e são pagos no BALCÃO.** A comanda fecha presencialmente e
  registra **como** o cliente pagou (dinheiro/cartão/pix/maquininha) — controle de caixa, não
  cobrança pelo sistema. (Já existe.)
- **Plano** (criado pelo dono): nome; **preço livre**; **serviços cobertos** (1+); **limite de
  uso** = **ilimitado** OU **teto** (N usos por período, ex.: 8/mês); **elegibilidade por dia da
  semana** = todos OU faixas (ex.: seg–qua, qui–sáb); **capacidade de contas** (1 / 2 / N — define
  família). Pode haver **plano personalizado** para um cliente específico.
- **Benefício:** assinante **ativo** → os **serviços cobertos**, **no dia permitido**, **dentro do
  limite**, saem **100%** (zerados) na comanda. **Produto**, serviço **fora do plano**, serviço
  coberto em **dia não permitido**, ou **além do teto** → **pago normalmente no balcão**.
- **Assinatura:** **1 titular pagante ↔ 1 plano ativo**. Status `ativa`/`suspensa`/`cancelada`/
  `inadimplente` (schema Fase A). **Um plano ativo por cliente titular.**
- **Beneficiários:** a assinatura cobre pessoas conforme a **capacidade** do plano. O **titular** é
  também beneficiário (usa o próprio plano). Cada beneficiário é **(a)** um **`Cliente` cadastrado**
  (tem conta, agenda pela própria conta) **ou (b)** um **perfil simples (só nome, sem login)** —
  ex.: criança.
- **Consumo (planos com teto):** contado **por assinatura no período** (ex.: 8 cortes/mês de um
  plano família = a família inteira divide os 8). O **dono define** o número e se é ilimitado.
- **Plano família:** preço livre (2 contas pode custar 180 ou menos, a critério do dono); o limite
  vale para a assinatura (compartilhado), conforme o dono definir.

## Como o código materializa a regra (D44)

- **Plano** (`planos_clube`): `preco_mensal` (livre), `ilimitado`/`limite_usos`/`periodo` (teto),
  `dias_semana` (json de dias 0=dom..6=sáb; null = todos), `capacidade`. Serviços cobertos na pivô
  **`plano_beneficios`** (`plano_id`+`servico_id`).
- **Assinatura** (`assinaturas_clube`): 1 titular (`cliente_id`) ↔ 1 plano. **Beneficiários** em
  `beneficiarios_assinatura` (`cliente_id` com conta **ou** `nome` sem conta; `titular` bool). Trava:
  nº de beneficiários ≤ `capacidade` (em `App\Services\Clube\Assinaturas`).
- **Cobertura na comanda** (`App\Services\Clube\BeneficioClube::aplicarCobertura`): para o
  assinante ativo (titular **ou** beneficiário com conta), zera 100% os itens de **serviço
  coberto**, no **dia permitido**, dentro do **teto** (saldo por assinatura/mês em `usos_clube`);
  o resto é cobrado no balcão. Reusa `Comanda::recalcular` (não reescreve o núcleo).
- **Cobrança recorrente:** ainda **costura manual** (`GatewayRecorrente`); o MP Preapproval entra
  pós-VPS sem mudar a aba.

## Hardening (3 correções isoladas — pós-D44)

- **Plano cobre 1+ serviço:** `salvarPlano` valida `planoServicos` como `required|array|min:1`
  (mensagem pt-BR). Não dá mais para salvar plano de cobertura sem cobrir nada.
- **Teto exige número:** se o plano **não** é ilimitado, `limite_usos` é `required|integer|min:1`.
  Antes, marcar "teto" e deixar o número em branco gravava `null` e o plano virava **ilimitado
  silenciosamente**. Ilimitado segue sem número.
- **Devolução de cota (o uso volta):** **cancelar** a comanda **ou remover** um item coberto
  **estorna o `uso_clube`** vinculado (`Comanda::estornarUsoClubeDosItens` →
  `UsoClube::whereIn('venda_item_id', …)->delete()`), em `Comanda::cancelar` e `Comanda::removerItem`.
  A pessoa **mantém o direito** (saldo restaurado). **Idempotente** (cancelar 2x não devolve em
  dobro — early-return de "já cancelada"; remover apaga o item). Vale para planos **limitados** (no
  ilimitado não há cota). Escopo restrito ao **uso do clube**: NÃO mexe em estoque/pagamento/comissão.
  Não-regressão: comanda **paga** (não cancelada) **mantém** o uso (consumo real conta).

## Fora deste escopo (próximo prompt)
- **Agendar para beneficiário** (toca a agenda/`MotorDisponibilidade`) — isolado de propósito. Esta
  regra é a base para aquele prompt.
- **Penalidade de no-show < 1h:** falta sem cancelar a tempo deveria **manter o uso cobrado** (não
  devolver a cota). Como depende de **horário agendado**, fica para o passo da agenda — hoje a
  devolução base (acima) vale para todo cancelamento/remoção.
