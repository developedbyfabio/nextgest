<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Cobrança da assinatura SaaS (salão → Nextgest) — D58
|--------------------------------------------------------------------------
|
| Parâmetros do modelo central de cobrança. NÃO confundir com o Clube (assinatura
| cliente → salão, que vive no banco do tenant). Aqui é a mensalidade do
| estabelecimento ao Nextgest.
|
| - carencia_dias: dias APÓS o vencimento em que o acesso continua (situação
|   "atrasada"). Passado esse prazo sem pagamento → "suspensa". Regra do Fabio: 20.
| - trial_padrao_dias: teste grátis padrão ao provisionar uma assinatura nova.
|
| Lido por App\Models\Assinatura::situacaoAcesso() (fonte única do estado) e pelo
| comando nextgest:provisionar-assinaturas.
|
*/

return [
    'carencia_dias' => 20,
    'trial_padrao_dias' => 30,
];
