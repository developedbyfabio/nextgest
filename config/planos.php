<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Planos do SaaS (catálogo) — D55
|--------------------------------------------------------------------------
|
| FONTE ÚNICA dos planos nomeados. Um plano é só um NOME que liga um conjunto
| de `recursos` (feature flags da Fase 0a — ver App\Enums\Recurso) de uma vez.
| A chave do array é o slug persistido no tenant (atributo virtual `plano`, no
| JSON `data` central — mesma regra de `segmento`/`recursos`).
|
| `recursos` PRECISA conter só slugs válidos do enum Recurso (clube/whatsapp/
| gateway); a leitura é normalizada por Tenant::recursosAtivos() de qualquer forma.
|
| `preco_mes` é REFERÊNCIA INTERNA do admin (orientar a troca de plano). A landing
| segue independente por ora — unificação de preço é uma fase posterior; isto NÃO é
| fonte única de preço ainda.
|
| Aplicação: Tenant::aplicarPlano($chave) seta `plano` + `recursos` (via atributos
| virtuais, respeitando a regra de ouro do `data`). Como o gating lê `recursos` ao
| vivo, trocar o plano reflete no painel do tenant na hora (menu + rotas).
|
*/

return [
    'basico' => [
        'nome' => 'Básico',
        'preco_mes' => 49.90,
        'recursos' => [],
    ],
    'profissional' => [
        'nome' => 'Profissional',
        'preco_mes' => 99.90,
        'recursos' => ['clube', 'gateway'],
    ],
    'nextgest' => [
        'nome' => 'Nextgest',
        'preco_mes' => 199.90,
        'recursos' => ['clube', 'gateway', 'whatsapp'],
    ],
];
